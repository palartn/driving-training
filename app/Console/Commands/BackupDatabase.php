<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\MysqlClientConfig;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class BackupDatabase extends Command
{
    use MysqlClientConfig;

    protected $signature = 'app:backup-database';

    protected $description = 'Create a compressed, checksummed MySQL backup and prune old ones';

    public function handle(): int
    {
        $connection = config('database.connections.'.config('database.default'));

        if (($connection['driver'] ?? null) !== 'mysql') {
            $this->error('Only the mysql driver is supported.');

            return self::FAILURE;
        }

        $directory = rtrim((string) config('backup.directory'), '\\/');
        File::ensureDirectoryExists($directory);

        $base = $directory.DIRECTORY_SEPARATOR.$connection['database'].'-'.now()->format('Ymd-His');
        $raw = $base.'.sql';
        $archive = $raw.'.gz';

        try {
            $this->dump($connection, $raw);
            $this->assertComplete($raw);
            $this->compress($raw, $archive);
        } catch (Throwable $e) {
            @unlink($raw);
            @unlink($archive);
            Log::error('Database backup failed: '.$e->getMessage());
            $this->error('Backup failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $hash = hash_file('sha256', $archive);
        file_put_contents($archive.'.sha256', $hash.'  '.basename($archive).PHP_EOL);

        $pruned = $this->prune($directory);

        Log::info('Database backup created', ['file' => $archive, 'bytes' => filesize($archive), 'pruned' => $pruned]);
        $this->info("Backup created: {$archive}");
        $this->line('Size: '.number_format(filesize($archive) / 1024, 1).' KB | SHA-256: '.$hash);
        $this->line("Old backups removed: {$pruned}");

        return self::SUCCESS;
    }

    private function dump(array $connection, string $target): void
    {
        $config = $this->writeClientConfig($connection);

        try {
            $process = new Process([
                (string) config('backup.mysqldump_path'),
                "--defaults-extra-file={$config}",
                '--single-transaction',
                '--quick',
                '--no-tablespaces',
                '--default-character-set=utf8mb4',
                "--result-file={$target}",
                $connection['database'],
            ]);
            $process->setTimeout(3600);
            $process->run();

            if (! $process->isSuccessful()) {
                throw new RuntimeException(trim($process->getErrorOutput()) ?: 'mysqldump failed');
            }
        } finally {
            @unlink($config);
        }
    }

    /** mysqldump يختم ملفه بسطر «Dump completed»؛ غيابه يعني نسخة مبتورة. */
    private function assertComplete(string $file): void
    {
        $size = (int) filesize($file);

        if ($size === 0) {
            throw new RuntimeException('The dump file is empty.');
        }

        $handle = fopen($file, 'rb');
        fseek($handle, max(0, $size - 512));
        $tail = (string) fread($handle, 512);
        fclose($handle);

        if (! str_contains($tail, 'Dump completed')) {
            throw new RuntimeException('The dump looks truncated (missing completion marker).');
        }
    }

    private function compress(string $source, string $archive): void
    {
        $in = fopen($source, 'rb');
        $out = gzopen($archive, 'wb6');

        while (! feof($in)) {
            gzwrite($out, (string) fread($in, 1024 * 1024));
        }

        fclose($in);
        gzclose($out);
        unlink($source);
    }

    /** يحذف نسخًا أقدم من مدة الاحتفاظ؛ لا يمس إلا ملفات النسخ (.sql.gz و.sha256). */
    private function prune(string $directory): int
    {
        $limit = now()->subDays(max(1, (int) config('backup.retention_days', 30)))->getTimestamp();
        $removed = 0;

        foreach (File::files($directory) as $file) {
            if (preg_match('/\.sql\.gz(\.sha256)?$/', $file->getFilename()) && $file->getMTime() < $limit) {
                File::delete($file->getPathname());
                $removed++;
            }
        }

        return $removed;
    }
}
