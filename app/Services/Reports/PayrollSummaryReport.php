<?php

namespace App\Services\Reports;

use App\Enums\PayBasis;
use App\Enums\PayrollRunStatus;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Services\Payroll\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Totals of the released payrolls in a year or month, by office and by
 * payroll, with the employer shares due for remittance.
 */
class PayrollSummaryReport
{
    /**
     * Amount columns: payroll item attribute => heading.
     *
     * @var array<string, string>
     */
    public const AMOUNTS = [
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
     * @return array{
     *     byRun: Collection<int, array{run: PayrollRun, amounts: array<string, int>}>,
     *     byOffice: Collection<int, array{office: string, employees: int, amounts: array<string, int>}>,
     *     totals: array{employees: int, amounts: array<string, int>},
     * }
     */
    public function data(int $year, ?int $month, ?PayBasis $payBasis): array
    {
        $runs = PayrollRun::query()
            ->with('period')
            ->where('status', PayrollRunStatus::Released)
            ->when($payBasis, fn (Builder $query) => $query->where('pay_basis', $payBasis))
            ->whereHas('period', fn (Builder $periods) => $periods
                ->where('year', $year)
                ->when($month, fn (Builder $query) => $query->where('month', $month)))
            ->get()
            ->sortBy(fn (PayrollRun $run): string => $run->period->start_date->format('Ymd').$run->pay_basis->value)
            ->values();

        $items = PayrollItem::query()
            ->whereIn('payroll_run_id', $runs->pluck('id'))
            ->get(['payroll_run_id', 'employee_id', 'department_name', ...array_keys(self::AMOUNTS)]);

        $sum = fn (Collection $group): array => collect(self::AMOUNTS)
            ->keys()
            ->mapWithKeys(fn (string $column): array => [$column => $group->sum(fn (PayrollItem $item): int => Money::toCentavos($item->{$column}))])
            ->all();

        $byOffice = $items->groupBy('department_name')
            ->sortKeys()
            ->map(fn (Collection $group, string $office): array => [
                'office' => $office,
                'employees' => $group->pluck('employee_id')->unique()->count(),
                'amounts' => $sum($group),
            ])
            ->values();

        $itemsByRun = $items->groupBy('payroll_run_id');

        return [
            'byRun' => $runs->map(fn (PayrollRun $run): array => ['run' => $run, 'amounts' => $sum($itemsByRun->get($run->id, collect()))]),
            'byOffice' => $byOffice,
            'totals' => ['employees' => $items->pluck('employee_id')->unique()->count(), 'amounts' => $sum($items)],
        ];
    }

    public function subtitle(int $year, ?int $month, ?PayBasis $payBasis): string
    {
        return collect([
            $month === null ? (string) $year : CarbonImmutable::create($year, $month)->format('F Y'),
            $payBasis?->payrollLabel() ?? __('All payrolls'),
            __('released payrolls only'),
        ])->join(' · ');
    }

    /**
     * @param  array{byRun: Collection<int, array{run: PayrollRun, amounts: array<string, int>}>, byOffice: Collection<int, array{office: string, employees: int, amounts: array<string, int>}>, totals: array{employees: int, amounts: array<string, int>}}  $data
     */
    public function report(array $data, string $subtitle, string $filename): Report
    {
        $amountColumns = range(2, count(self::AMOUNTS) + 1);
        $decimals = fn (array $amounts): array => array_map(fn (int $centavos): string => Money::toDecimal($centavos), array_values($amounts));

        return new Report(
            title: __('Payroll Summary'),
            subtitle: $subtitle,
            sections: [
                new ReportSection(
                    columns: ['Office', 'Employees', ...array_values(self::AMOUNTS)],
                    rows: $data['byOffice']->map(fn (array $row): array => [$row['office'], $row['employees'], ...$decimals($row['amounts'])])->all(),
                    totals: ['Total', $data['totals']['employees'], ...$decimals($data['totals']['amounts'])],
                    moneyColumns: $amountColumns,
                    numberColumns: [1],
                    heading: __('By office'),
                ),
                new ReportSection(
                    columns: ['Payroll', 'Employees', 'Gross pay', 'Total deductions', 'Net pay'],
                    rows: $data['byRun']->map(fn (array $row): array => [
                        $row['run']->title(),
                        $row['run']->employee_count,
                        Money::toDecimal($row['amounts']['gross_pay']),
                        Money::toDecimal($row['amounts']['total_deductions']),
                        Money::toDecimal($row['amounts']['net_pay']),
                    ])->all(),
                    moneyColumns: [2, 3, 4],
                    numberColumns: [1],
                    heading: __('By payroll'),
                ),
            ],
            filename: $filename,
        );
    }
}
