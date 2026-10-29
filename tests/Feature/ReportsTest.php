<?php

namespace Tests\Feature;

use App\Services\PaymentService;
use App\Services\Reports\ReportBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\TrainingFixtures;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;
    use TrainingFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_enrollment_roster_lists_students_with_their_unpaid_subject_count(): void
    {
        $center = $this->makeCenter();
        $employee = $this->makeUser('employee', $center);
        $subjects = [$this->makeSubject('أ'), $this->makeSubject('ب')];
        $course = $this->makeCourse($center, $subjects);

        $paid = $this->enroll($this->makeStudent($center), $course, $employee);
        $this->payAll($paid, $employee);
        $this->enroll($this->makeStudent($center), $course, $employee);

        $report = app(ReportBuilder::class)->enrollmentRoster($employee, $course->id);

        $this->assertCount(2, $report['rows']);
        $this->assertSame([0, 2], collect($report['rows'])->pluck(5)->sort()->values()->all());
        $this->assertSame(2, $report['meta']['عدد الطلاب']);
    }

    public function test_payments_report_totals_subject_payments_and_retake_fees(): void
    {
        $center = $this->makeCenter();
        $employee = $this->makeUser('employee', $center);
        $subjects = [$this->makeSubject('أ', '100.00'), $this->makeSubject('ب', '60.00')];
        $enrollment = $this->enroll($this->makeStudent($center), $this->makeCourse($center, $subjects), $employee);

        foreach ($enrollment->enrollmentSubjects()->orderBy('id')->get() as $i => $unit) {
            app(PaymentService::class)->recordSubjectPayment(
                $unit,
                $this->payData(number_format($unit->dueCents() / 100, 2, '.', ''), 'P'.$i),
                $employee,
            );
        }

        $report = app(ReportBuilder::class)->payments($employee, null, null, null);

        $this->assertCount(2, $report['rows']);
        $this->assertEquals(160.0, $report['totals']['إجمالي المبالغ']);
    }

    public function test_unpaid_report_totals_the_due_amount_after_exemption(): void
    {
        $center = $this->makeCenter();
        $employee = $this->makeUser('employee', $center);
        $head = $this->makeUser('department_head', $center);
        $enrollment = $this->enroll(
            $this->makeStudent($center),
            $this->makeCourse($center, [$this->makeSubject('أ', '100.00'), $this->makeSubject('ب', '50.00')]),
            $employee,
        );

        app(PaymentService::class)->addExemption($enrollment->enrollmentSubjects()->orderBy('id')->first(), '40.00', 'سبب', $head);

        $report = app(ReportBuilder::class)->unpaidSubjects($employee, null, null);

        $this->assertCount(2, $report['rows']);
        $this->assertEquals(110.0, $report['totals']['إجمالي المستحق']);
    }

    public function test_a_center_bound_user_cannot_report_on_another_centers_course(): void
    {
        $centerA = $this->makeCenter('A');
        $centerB = $this->makeCenter('B');
        $outsider = $this->makeUser('employee', $centerB);
        $course = $this->makeCourse($centerA, [$this->makeSubject('أ')]);

        $this->expectException(HttpException::class);

        app(ReportBuilder::class)->enrollmentRoster($outsider, $course->id);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('reports.payments'))->assertRedirect(route('login'));
    }

    public function test_print_page_shows_the_ministry_letterhead(): void
    {
        $center = $this->makeCenter();
        $employee = $this->makeUser('employee', $center);

        $this->actingAs($employee)
            ->get(route('reports.payments'))
            ->assertOk()
            ->assertSee('دولة فلسطين')
            ->assertSee('State of Palestine')
            ->assertSee('Ministry of Labor');
    }

    public function test_excel_export_returns_an_xlsx_file(): void
    {
        $center = $this->makeCenter();
        $employee = $this->makeUser('employee', $center);

        $response = $this->actingAs($employee)->get(route('reports.payments', ['format' => 'excel']));

        $response->assertOk();
        $this->assertStringContainsString('spreadsheetml', (string) $response->headers->get('content-type'));
        $this->assertStringStartsWith('PK', $response->streamedContent());
    }
}
