<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\MysqlClientConfig;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

class BackupRestoreCheck extends Command
{
    use MysqlClientConfig;

    protected $signature = 'app:backup-restore-check
        {--file= : Backup archive to test (default: the latest)}
        {--target= : Scratch database name ending in _restore_test (default: <database>_restore_test)}';

    protected $description = 'Prove a backup is restorable by restoring it into a scratch database and comparing it with the live one';

    /** جداول رئيسية تُقارن أعداد صفوفها. */
    private const KEY_TABLES = ['students', 'enrollments', 'enrollment_subjects', 'payments', 'exemptions', 'exam_attempts', 'audit_logs'];

    public function handle(): int
    {
        $connection = config('database.connections.'.config('database.default'));
        $live = (string) $connection['database'];
        $target = (string) ($this->option('target') ?: $live.'_restore_test');

        // حماية: لا استعادة فوق القاعدة الحية أبدًا.
        if ($target === $live || ! str_ends_with($target, '_restore_test')) {
            $this->error("Refusing: the target must be a scratch database ending in _restore_test (got [{$target}], live is [{$live}]).");

            return self::FAILURE;
        }

        $file = (string) ($this->option('file') ?: $this->latestArchive());

        if ($file === '' || ! is_file($file)) {
            $this->error('No backup archive found. Run app:backup-database first.');

            return self::FAILURE;
        }

        $expected = trim(explode(' ', (string) @file_get_contents($file.'.sha256'))[0]);

        if ($expected === '' || ! hash_equals($expected, hash_file('sha256', $file))) {
            $this->error('Checksum mismatch or missing .sha256 file: the archive is corrupted or was modified.');

            return self::FAILURE;
        }

        $exists = DB::selectOne('select count(*) as c from information_schema.schemata where schema_name = ?', [$target])->c > 0;

        if (! $exists) {
            $this->error("The scratch database [{$target}] does not exist. Create it first, as an administrator:");
            $this->line("  CREATE DATABASE `{$target}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
            $this->line("  GRANT ALL PRIVILEGES ON `{$target}`.* TO '{$connection['username']}'@'localhost';");

            return self::FAILURE;
        }

        $this->info("Restoring {$file} into [{$target}] ...");

        $config = $this->writeClientConfig($connection);
        $stream = null;

        try {
            $stream = fopen('compress.zlib://'.$file, 'rb');

            $process = new Process([
                (string) config('backup.mysql_path'),
                "--defaults-extra-file={$config}",
                '--default-character-set=utf8mb4',
                $target,
            ]);
            $process->setTimeout(3600);
            $process->setInput($stream);
            $process->run();
        } finally {
            @unlink($config);

            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if (! $process->isSuccessful()) {
            $this->error('Restore failed: '.trim($process->getErrorOutput()));

            return self::FAILURE;
        }

        $liveTables = $this->tables($live);
        $restoredTables = $this->tables($target);

        if ($liveTables !== $restoredTables) {
            $this->error('The restored database has different tables from the live one.');
            $this->line('Missing: '.implode(', ', array_diff($liveTables, $restoredTables)));
            $this->line('Extra: '.implode(', ', array_diff($restoredTables, $liveTables)));

            return self::FAILURE;
        }

        $rows = [];
        foreach (self::KEY_TABLES as $table) {
            if (! in_array($table, $liveTables, true)) {
                continue;
            }

            $rows[] = [
                $table,
                DB::selectOne("select count(*) as c from `{$live}`.`{$table}`")->c,
                DB::selectOne("select count(*) as c from `{$target}`.`{$table}`")->c,
            ];
        }

        $this->table(['Table', 'Live rows', 'Restored rows'], $rows);
        $this->info('Restore check passed: '.count($restoredTables).' tables restored. (Restored counts may be lower than live if data changed after the backup.)');

        return self::SUCCESS;
    }

    private function latestArchive(): ?string
    {
        $files = glob(rtrim((string) config('backup.directory'), '\\/').DIRECTORY_SEPARATOR.'*.sql.gz') ?: [];

        usort($files, fn (string $a, string $b) => filemtime($b) <=> filemtime($a));

        return $files[0] ?? null;
    }

    private function tables(string $database): array
    {
        return array_map(
            fn ($row) => $row->t,
            DB::select(
                'select table_name as t from information_schema.tables where table_schema = ? and table_type = ? order by table_name',
                [$database, 'BASE TABLE'],
            ),
        );
    }
}
