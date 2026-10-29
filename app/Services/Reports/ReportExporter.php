<?php

namespace App\Services\Reports;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** تصدير التقرير إلى Excel بنفس بيانات الطباعة (مكتبة openspout). */
class ReportExporter
{
    public function download(array $report): StreamedResponse
    {
        $filename = 'report-'.$report['key'].'-'.now()->format('Ymd-His').'.xlsx';

        return response()->streamDownload(function () use ($report) {
            $bold = (new Style())->setFontBold();

            $writer = new Writer();
            $writer->openToFile('php://output');

            $writer->addRow(Row::fromValues(['دولة فلسطين - وزارة العمل | State of Palestine - Ministry of Labor'], $bold));
            $writer->addRow(Row::fromValues([$report['title']], $bold));

            foreach ($report['meta'] as $label => $value) {
                $writer->addRow(Row::fromValues([(string) $label, (string) $value]));
            }

            $writer->addRow(Row::fromValues(['']));

            $writer->addRow(Row::fromValues(
                array_merge(['م'], array_column($report['columns'], 'label')),
                $bold,
            ));

            foreach ($report['rows'] as $index => $row) {
                $cells = [$index + 1];

                foreach ($row as $i => $value) {
                    $type = $report['columns'][$i]['type'] ?? 'text';
                    $cells[] = $value === null
                        ? ''
                        : (in_array($type, ['money', 'number'], true) ? $value + 0 : (string) $value);
                }

                $writer->addRow(Row::fromValues($cells));
            }

            foreach ($report['totals'] as $label => $value) {
                $writer->addRow(Row::fromValues([(string) $label, $value], $bold));
            }

            $writer->close();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
