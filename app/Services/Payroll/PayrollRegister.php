<?php

namespace App\Services\Payroll;

use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Services\Reports\SpreadsheetFormulaGuard;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The payroll register of a run: one row per employee, by office then
 * name, with totals and the employer shares for remittance.
 */
class PayrollRegister
{
    /**
     * Money columns of the register: item attribute => heading.
     *
     * @var array<string, string>
     */
    public const MONEY_COLUMNS = [
        'basic_pay' => 'Basic pay',
        'allowances' => 'Allowances',
        'attendance_deductions' => 'Absences w/o pay',
        'gross_pay' => 'Gross pay',
        'gsis' => 'GSIS',
        'philhealth' => 'PhilHealth',
        'pagibig' => 'Pag-IBIG',
        'withholding_tax' => 'Withholding tax',
        'other_deductions' => 'Loans and others',
        'total_deductions' => 'Total deductions',
        'net_pay' => 'Net pay',
        'gsis_employer' => 'GSIS (employer)',
        'philhealth_employer' => 'PhilHealth (employer)',
        'pagibig_employer' => 'Pag-IBIG (employer)',
    ];

    /**
     * @return array{items: Collection<int, PayrollItem>, totals: array<string, int>}
     */
    public function data(PayrollRun $run): array
    {
        $items = $run->items()
            ->with('employee')
            ->get()
            ->sortBy(fn (PayrollItem $item): string => $item->department_name.'|'.$item->employee->sortable_name)
            ->values();

        $totals = [];

        foreach (array_keys(self::MONEY_COLUMNS) as $column) {
            $totals[$column] = $items->sum(fn (PayrollItem $item): int => Money::toCentavos($item->{$column}));
        }

        return ['items' => $items, 'totals' => $totals];
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return ['No.', 'Employee No.', 'Employee', 'Position', 'Office', 'Rate', ...array_values(self::MONEY_COLUMNS)];
    }

    public function rateLabel(PayrollItem $item): string
    {
        return $item->daily_rate !== null
            ? __(':rate/day', ['rate' => Money::format($item->daily_rate)])
            : __('SG :grade-:step, :rate/mo', ['grade' => $item->salary_grade, 'step' => $item->salary_step, 'rate' => Money::format($item->monthly_rate)]);
    }

    public function csv(PayrollRun $run, string $filename): StreamedResponse
    {
        $data = $this->data($run);

        return response()->streamDownload(function () use ($run, $data): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, SpreadsheetFormulaGuard::csvRow(['Payroll register', $run->title(), $run->status->label()]));
            fputcsv($out, []);
            fputcsv($out, SpreadsheetFormulaGuard::csvRow($this->headings()));

            foreach ($data['items'] as $index => $item) {
                fputcsv($out, SpreadsheetFormulaGuard::csvRow([
                    $index + 1,
                    $item->employee->employee_number,
                    $item->employee->sortable_name,
                    $item->position_title,
                    $item->department_name,
                    $this->rateLabel($item),
                    ...array_map(fn (string $column): string => $item->{$column}, array_keys(self::MONEY_COLUMNS)),
                ]));
            }

            fputcsv($out, SpreadsheetFormulaGuard::csvRow(['', '', 'Total', '', '', '', ...array_map(fn (int $total): string => Money::toDecimal($total), array_values($data['totals']))]));
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function xlsx(PayrollRun $run, string $filename): StreamedResponse
    {
        $data = $this->data($run);
        $spreadsheet = new Spreadsheet;
        $spreadsheet->setValueBinder(SpreadsheetFormulaGuard::valueBinder());
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Payroll register');

        $sheet->setCellValue('A1', 'Municipality of Cagdianao, Province of Dinagat Islands — Payroll register');
        $sheet->setCellValue('A2', $run->title().' ('.$run->status->label().')');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);

        $headerRow = 4;
        $sheet->fromArray($this->headings(), null, 'A'.$headerRow);
        $sheet->getStyle('A'.$headerRow.':'.$sheet->getHighestColumn().$headerRow)->getFont()->setBold(true);

        $row = $headerRow + 1;

        foreach ($data['items'] as $index => $item) {
            $sheet->fromArray([
                $index + 1,
                $item->employee->employee_number,
                $item->employee->sortable_name,
                $item->position_title,
                $item->department_name,
                $this->rateLabel($item),
                ...array_map(fn (string $column): float => (float) $item->{$column}, array_keys(self::MONEY_COLUMNS)),
            ], null, 'A'.$row);
            $row++;
        }

        $sheet->fromArray(['', '', 'Total', '', '', '', ...array_map(fn (int $total): float => $total / 100, array_values($data['totals']))], null, 'A'.$row);
        $sheet->getStyle('A'.$row.':'.$sheet->getHighestColumn().$row)->getFont()->setBold(true);

        $firstMoneyColumn = 'G';
        $sheet->getStyle($firstMoneyColumn.($headerRow + 1).':'.$sheet->getHighestColumn().$row)
            ->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);

        foreach (range('A', $sheet->getHighestColumn()) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
