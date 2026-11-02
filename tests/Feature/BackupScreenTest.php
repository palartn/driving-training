<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Services\BackupCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Support\TrainingFixtures;
use Tests\TestCase;

class BackupScreenTest extends TestCase
{
    use RefreshDatabase;
    use TrainingFixtures;

    private const NAME = 'driving-20260101-020000.sql.gz';

    private string $directory = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();

        $this->directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'dt-bk-'.uniqid();
        File::ensureDirectoryExists($this->directory);
        config(['backup.directory' => $this->directory]);

        file_put_contents($this->path(), 'backup-bytes');
        file_put_contents($this->path().'.sha256', hash('sha256', 'backup-bytes').'  '.self::NAME.PHP_EOL);
    }

    protected function tearDown(): void
    {
        if ($this->directory !== '') {
            File::deleteDirectory($this->directory);
        }

        parent::tearDown();
    }

    private function path(): string
    {
        return $this->directory.DIRECTORY_SEPARATOR.self::NAME;
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('backups.download', ['file' => self::NAME]))->assertRedirect(route('login'));
    }

    public function test_only_users_with_the_download_permission_can_download(): void
    {
        $employee = $this->makeUser('employee', $this->makeCenter());
        $manager = $this->makeUser('manager', $this->makeCenter('M'));

        $this->actingAs($employee)->get(route('backups.download', ['file' => self::NAME]))->assertForbidden();
        $this->actingAs($manager)->get(route('backups.download', ['file' => self::NAME]))->assertForbidden();
    }

    public function test_general_manager_downloads_a_verified_backup_and_it_is_audited(): void
    {
        $gm = $this->makeUser('general_manager');

        $this->actingAs($gm)
            ->get(route('backups.download', ['file' => self::NAME]))
            ->assertOk()
            ->assertDownload(self::NAME);

        $log = AuditLog::where('event', 'backup.downloaded')->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertSame($gm->id, $log->user_id);
        $this->assertSame(self::NAME, $log->new_values['file']);
    }

    public function test_a_tampered_backup_is_not_served(): void
    {
        $gm = $this->makeUser('general_manager');

        file_put_contents($this->path(), 'tampered', FILE_APPEND);

        $this->actingAs($gm)->get(route('backups.download', ['file' => self::NAME]))->assertStatus(409);
        $this->assertSame(0, AuditLog::where('event', 'backup.downloaded')->count());
    }

    public function test_unknown_and_traversal_names_are_not_found(): void
    {
        $gm = $this->makeUser('general_manager');

        $this->actingAs($gm)->get('/backups/missing-20200101-000000.sql.gz')->assertNotFound();
        $this->actingAs($gm)->get('/backups/..%2F.env')->assertNotFound();
        $this->actingAs($gm)->get('/backups/..%2F..%2Fsecret.sql.gz')->assertNotFound();
    }

    public function test_catalog_lists_only_backup_files_newest_first(): void
    {
        $older = $this->directory.DIRECTORY_SEPARATOR.'driving-20250101-020000.sql.gz';
        file_put_contents($older, 'x');
        touch($older, time() - 3 * 86400);
        file_put_contents($this->directory.DIRECTORY_SEPARATOR.'notes.txt', 'x');

        $names = array_column(app(BackupCatalog::class)->all(), 'name');

        $this->assertSame([self::NAME, 'driving-20250101-020000.sql.gz'], $names);
        $this->assertNull(app(BackupCatalog::class)->find('notes.txt'));
        $this->assertNull(app(BackupCatalog::class)->find('../x.sql.gz'));
    }
}
