<?php

namespace App\Http\Controllers;

use App\Services\Reports\ReportBuilder;
use App\Services\Reports\ReportExporter;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportBuilder $builder,
        private readonly ReportExporter $exporter,
    ) {
    }

    public function enrollmentRoster(Request $request)
    {
        $this->authorizeReports($request);

        $data = $request->validate([
            'course_id' => ['required', 'integer'],
            'format' => ['nullable', 'in:html,excel'],
        ]);

        return $this->respond($request, $this->builder->enrollmentRoster($request->user(), (int) $data['course_id']));
    }

    public function examResults(Request $request)
    {
        $this->authorizeReports($request);

        $data = $request->validate([
            'exam_id' => ['required', 'integer'],
            'format' => ['nullable', 'in:html,excel'],
        ]);

        return $this->respond($request, $this->builder->examResults($request->user(), (int) $data['exam_id']));
    }

    public function payments(Request $request)
    {
        $this->authorizeReports($request);

        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'until' => ['nullable', 'date'],
            'center_id' => ['nullable', 'integer'],
            'format' => ['nullable', 'in:html,excel'],
        ]);

        return $this->respond($request, $this->builder->payments(
            $request->user(),
            $data['from'] ?? null,
            $data['until'] ?? null,
            isset($data['center_id']) ? (int) $data['center_id'] : null,
        ));
    }

    public function unpaidSubjects(Request $request)
    {
        $this->authorizeReports($request);

        $data = $request->validate([
            'center_id' => ['nullable', 'integer'],
            'course_id' => ['nullable', 'integer'],
            'format' => ['nullable', 'in:html,excel'],
        ]);

        return $this->respond($request, $this->builder->unpaidSubjects(
            $request->user(),
            isset($data['center_id']) ? (int) $data['center_id'] : null,
            isset($data['course_id']) ? (int) $data['course_id'] : null,
        ));
    }

    private function authorizeReports(Request $request): void
    {
        abort_unless($request->user()?->can('reports.view'), 403);
    }

    private function respond(Request $request, array $report)
    {
        if ($request->query('format') === 'excel') {
            return $this->exporter->download($report);
        }

        return view('reports.print', [
            'report' => $report,
            'issuedBy' => $request->user()->name,
        ]);
    }
}
