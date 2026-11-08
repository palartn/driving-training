<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use App\Services\Reports\AttendanceReportBuilder;
use App\Services\Reports\ReportExporter;
use Illuminate\Http\Request;

class AttendanceReportController extends Controller
{
    public function __construct(
        private readonly AttendanceReportBuilder $builder,
        private readonly ReportExporter $exporter,
    ) {
    }

    public function session(Request $request)
    {
        $this->authorizeReports($request);

        $data = $request->validate([
            'session_id' => ['required', 'integer'],
            'format' => ['nullable', 'in:html,excel'],
        ]);

        return $this->respond($request, $this->builder->sessionAttendance($request->user(), (int) $data['session_id']));
    }

    public function course(Request $request)
    {
        $this->authorizeReports($request);

        $data = $request->validate([
            'course_id' => ['required', 'integer'],
            'format' => ['nullable', 'in:html,excel'],
        ]);

        return $this->respond($request, $this->builder->courseAttendance($request->user(), (int) $data['course_id']));
    }

    private function authorizeReports(Request $request): void
    {
        abort_unless(
            $request->user()?->can('reports.view') && $request->user()->can('attendance.view'),
            403,
        );
    }

    private function respond(Request $request, array $report)
    {
        $format = $request->query('format') === 'excel' ? 'excel' : 'html';

        // الكشوف تحوي بيانات طلاب، فيُسجَّل من أصدرها ومتى وبأي معايير.
        AuditLogger::record('report.generated', $request->user(), null, [
            'report' => $report['key'],
            'format' => $format,
            'filters' => $request->except(['format']),
            'rows' => count($report['rows']),
        ]);

        if ($format === 'excel') {
            return $this->exporter->download($report);
        }

        return view('reports.print', [
            'report' => $report,
            'issuedBy' => $request->user()->name,
        ]);
    }
}
