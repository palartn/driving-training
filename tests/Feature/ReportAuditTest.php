<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TrainingFixtures;
use Tests\TestCase;

class ReportAuditTest extends TestCase
{
    use RefreshDatabase;
    use TrainingFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_generating_a_report_is_logged_with_who_what_and_filters(): void
    {
        $employee = $this->makeUser('employee', $this->makeCenter());

        $this->actingAs($employee)
            ->get(route('reports.payments', ['from' => '2026-01-01', 'until' => '2026-01-31']))
            ->assertOk();

        $log = AuditLog::where('event', 'report.generated')->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertSame($employee->id, $log->user_id);
        $this->assertSame('payments', $log->new_values['report']);
        $this->assertSame('html', $log->new_values['format']);
        $this->assertSame('2026-01-01', $log->new_values['filters']['from']);
    }

    public function test_excel_export_is_logged_with_its_format(): void
    {
        $employee = $this->makeUser('employee', $this->makeCenter());

        $this->actingAs($employee)
            ->get(route('reports.unpaid-subjects', ['format' => 'excel']))
            ->assertOk();

        $log = AuditLog::where('event', 'report.generated')->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertSame('unpaid-subjects', $log->new_values['report']);
        $this->assertSame('excel', $log->new_values['format']);
    }
}
