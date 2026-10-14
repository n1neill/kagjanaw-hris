<?php

namespace App\Services\Reports;

use App\Enums\AuditEvent;
use App\Services\Audit\AuditLogger;
use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exports a report as Excel (.xlsx), CSV or PDF, and records the export in
 * the audit log.
 */
class ReportExporter
{
    public const FORMATS = ['xlsx', 'csv', 'pdf'];

    public function __construct(private AuditLogger $audit) {}

    public function download(Report $report, string $format): Response
    {
        $this->audit->record(AuditEvent::Exported, $report->title.' — '.$report->subtitle.' ('.$format.')');

        return match ($format) {
            'xlsx' => $this->xlsx($report),
            'csv' => $this->csv($report),
            'pdf' => $this->pdf($report),
        };
    }

    public function csv(Report $report): StreamedResponse
    {
        return response()->streamDownload(function () use ($report): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, SpreadsheetFormulaGuard::csvRow([$report->title, $report->subtitle]));

            foreach ($report->sections as $section) {
                fputcsv($out, []);

                if ($section->heading !== null) {
                    fputcsv($out, SpreadsheetFormulaGuard::csvRow([$section->heading]));
                }

                fputcsv($out, SpreadsheetFormulaGuard::csvRow($section->columns));

                foreach ($section->rows as $row) {
                    fputcsv($out, SpreadsheetFormulaGuard::csvRow($row));
                }

                if ($section->totals !== null) {
                    fputcsv($out, SpreadsheetFormulaGuard::csvRow($section->totals));
                }
            }

            fclose($out);
        }, $report->filename.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function xlsx(Report $report): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->setValueBinder(SpreadsheetFormulaGuard::valueBinder());
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(mb_substr($report->title, 0, 31));

        $sheet->setCellValue('A1', 'Municipality of Cagdianao, Province of Dinagat Islands — '.$report->title);
        $sheet->setCellValue('A2', $report->subtitle);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);

        $row = 4;
        $widest = 1;

        foreach ($report->sections as $section) {
            $widest = max($widest, count($section->columns));

            if ($section->heading !== null) {
                $sheet->setCellValue('A'.$row, $section->heading);
                $sheet->getStyle('A'.$row)->getFont()->setBold(true);
                $row++;
            }

            $sheet->fromArray($section->columns, null, 'A'.$row);
            $sheet->getStyle('A'.$row.':'.Coordinate::stringFromColumnIndex(count($section->columns)).$row)->getFont()->setBold(true);
            $firstDataRow = $row + 1;
            $row++;

            foreach ([...$section->rows, ...($section->totals === null ? [] : [$section->totals])] as $values) {
                $sheet->fromArray($this->spreadsheetValues($section, $values), null, 'A'.$row, true);
                $row++;
            }

            if ($section->totals !== null) {
                $sheet->getStyle('A'.($row - 1).':'.Coordinate::stringFromColumnIndex(count($section->columns)).($row - 1))->getFont()->setBold(true);
            }

            foreach (array_keys($section->columns) as $index) {
                $column = Coordinate::stringFromColumnIndex($index + 1);

                if ($section->isMoney($index) && $row > $firstDataRow) {
                    $sheet->getStyle($column.$firstDataRow.':'.$column.($row - 1))
                        ->getNumberFormat()
                        ->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
                }

                if ($section->isNumeric($index)) {
                    $sheet->getStyle($column.($firstDataRow - 1).':'.$column.max($firstDataRow - 1, $row - 1))
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }
            }

            $row++;
        }

        for ($index = 1; $index <= $widest; $index++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index))->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $report->filename.'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function pdf(Report $report): Response
    {
        return Pdf::loadView('reports.pdf', ['report' => $report])
            ->setPaper($report->landscape ? 'legal' : 'a4', $report->landscape ? 'landscape' : 'portrait')
            ->setOption('isFontSubsettingEnabled', true)
            ->stream($report->filename.'.pdf');
    }

    /**
     * Numbers as numbers, so Excel can total them; everything else as text.
     *
     * @param  list<string|int|float|null>  $values
     * @return list<string|int|float|null>
     */
    private function spreadsheetValues(ReportSection $section, array $values): array
    {
        return array_map(
            fn (string|int|float|null $value, int $index): string|int|float|null => $section->isNumeric($index) && is_numeric($value) ? $value + 0 : $value,
            $values,
            array_keys($values),
        );
    }
}
