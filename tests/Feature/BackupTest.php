<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Tests\TestCase;
use Throwable;

class BackupTest extends TestCase
{
    use RefreshDatabase;

    private string $directory = '';

    protected function setUp(): void
    {
        parent::setUp();

        $probe = new Process([(string) config('backup.mysqldump_path'), '--version']);

        try {
            $probe->run();
        } catch (Throwable) {
            // يُعامل كأداة غير متاحة.
        }

        if (! $probe->isSuccessful()) {
            $this->markTestSkipped('mysqldump is not available. Set MYSQLDUMP_PATH in .env.testing to run the backup tests.');
        }

        $this->directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'dt-backup-'.uniqid();
        config(['backup.directory' => $this->directory]);
    }

    protected function tearDown(): void
    {
        if ($this->directory !== '') {
            File::deleteDirectory($this->directory);
        }

        parent::tearDown();
    }

    private function archives(): array
    {
        return glob($this->directory.DIRECTORY_SEPARATOR.'*.sql.gz') ?: [];
    }

    public function test_backup_creates_a_complete_checksummed_archive(): void
    {
        $this->artisan('app:backup-database')->assertSuccessful();

        $archives = $this->archives();
        $this->assertCount(1, $archives);

        $file = $archives[0];
        $recorded = trim(explode(' ', (string) file_get_contents($file.'.sha256'))[0]);
        $this->assertSame(hash_file('sha256', $file), $recorded);

        $sql = '';
        $handle = gzopen($file, 'rb');
        while (! gzeof($handle)) {
            $sql .= gzread($handle, 1024 * 1024);
        }
        gzclose($handle);

        $this->assertStringContainsString('CREATE TABLE `students`', $sql);
        $this->assertStringContainsString('CREATE TABLE `payments`', $sql);
        $this->assertStringContainsString('Dump completed', $sql);
    }

    public function test_old_backups_are_pruned_but_other_files_are_left_alone(): void
    {
        File::ensureDirectoryExists($this->directory);

        $old = $this->directory.DIRECTORY_SEPARATOR.'old-20200101-000000.sql.gz';
        $unrelated = $this->directory.DIRECTORY_SEPARATOR.'notes.txt';

        foreach ([$old, $old.'.sha256', $unrelated] as $path) {
            file_put_contents($path, 'x');
            touch($path, time() - 40 * 86400);
        }

        $this->artisan('app:backup-database')->assertSuccessful();

        $this->assertFileDoesNotExist($old);
        $this->assertFileDoesNotExist($old.'.sha256');
        $this->assertFileExists($unrelated);
        $this->assertCount(1, $this->archives());
    }

    public function test_restore_check_refuses_the_live_database_and_unsafe_names(): void
    {
        $live = (string) config('database.connections.'.config('database.default').'.database');

        $this->artisan('app:backup-restore-check', ['--target' => $live])->assertFailed();
        $this->artisan('app:backup-restore-check', ['--target' => 'some_other_db'])->assertFailed();
    }

    public function test_restore_check_detects_a_tampered_archive(): void
    {
        $this->artisan('app:backup-database')->assertSuccessful();
        $file = $this->archives()[0];

        file_put_contents($file, 'tampered', FILE_APPEND);

        $this->artisan('app:backup-restore-check', ['--file' => $file, '--target' => 'scratch_restore_test'])->assertFailed();
    }
}
