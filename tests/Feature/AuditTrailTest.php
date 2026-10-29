<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TrainingFixtures;
use Tests\TestCase;

class AuditTrailTest extends TestCase
{
    use RefreshDatabase;
    use TrainingFixtures;

    public function test_creating_and_updating_a_student_is_logged_with_old_and_new_values(): void
    {
        $student = $this->makeStudent($this->makeCenter());

        $created = AuditLog::where('auditable_type', Student::class)
            ->where('auditable_id', $student->id)
            ->where('event', 'student.created')
            ->first();

        $this->assertNotNull($created);
        $this->assertSame($student->national_id, $created->new_values['national_id']);

        $student->update(['phone' => '0599111222']);

        $updated = AuditLog::where('auditable_type', Student::class)
            ->where('auditable_id', $student->id)
            ->where('event', 'student.updated')
            ->first();

        $this->assertNotNull($updated);
        $this->assertSame('0599000000', $updated->old_values['phone']);
        $this->assertSame('0599111222', $updated->new_values['phone']);
    }

    public function test_user_audit_never_contains_the_password(): void
    {
        $user = User::create([
            'name' => 'مستخدم',
            'username' => 'audited',
            'password' => 'secret-pass-123',
            'is_active' => true,
        ]);

        $log = AuditLog::where('auditable_type', User::class)
            ->where('auditable_id', $user->id)
            ->where('event', 'user.created')
            ->first();

        $this->assertNotNull($log);
        $this->assertArrayNotHasKey('password', $log->new_values);
        $this->assertSame('audited', $log->new_values['username']);
    }

    public function test_an_update_without_real_changes_writes_no_log(): void
    {
        $student = $this->makeStudent($this->makeCenter());
        $before = AuditLog::count();

        $student->update(['phone' => $student->phone]);

        $this->assertSame($before, AuditLog::count());
    }
}
