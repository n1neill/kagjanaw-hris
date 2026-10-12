<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\NewEffectivityRequest;
use App\Http\Requests\Payroll\SalaryTrancheRequest;
use App\Models\SalaryGrade;
use App\Services\Payroll\Money;
use App\Services\Payroll\PayrollSettings;
use App\Services\Payroll\SalarySchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The national Salary Schedule by tranche, with the LGU rates at the
 * income-class percentage. HR adds a tranche (starting as a copy of the one
 * in force) and edits its amounts when DBM issues a new schedule.
 */
class SalaryScheduleController extends Controller
{
    public function __construct(
        private SalarySchedule $schedule,
        private PayrollSettings $settings,
    ) {}

    public function index(Request $request): View
    {
        $dates = $this->schedule->effectivityDates();
        $selected = in_array($request->query('effective'), $dates->all(), true)
            ? $request->query('effective')
            : ($this->schedule->effectiveDateOn(now()) ?? $dates->first());

        return view('payroll.salary-schedule.index', [
            'dates' => $dates,
            'selected' => $selected,
            'inForce' => $this->schedule->effectiveDateOn(now()),
            'grid' => $selected === null ? collect() : $this->grid($selected),
            'legalBasis' => $selected === null ? null : SalaryGrade::query()->whereDate('effective_from', $selected)->value('legal_basis'),
            'percentage' => $this->settings->percentages()[$this->settings->incomeClass()] ?? '100.00',
            'incomeClass' => $this->settings->incomeClass(),
            'isConfirmed' => $this->settings->isIncomeClassConfirmed(),
            'schedule' => $this->schedule,
        ]);
    }

    /**
     * Start a new tranche as a copy of the one in force on its effectivity date.
     */
    public function store(NewEffectivityRequest $request): RedirectResponse
    {
        $effectiveFrom = $request->effectiveFrom()->toDateString();

        if ($this->schedule->effectivityDates()->contains($effectiveFrom)) {
            throw ValidationException::withMessages(['effective_from' => __('A tranche effective on that date already exists.')]);
        }

        $source = $this->schedule->effectiveDateOn($request->effectiveFrom()) ?? $this->schedule->effectivityDates()->first();
        $now = now();

        SalaryGrade::insert(SalaryGrade::query()
            ->when($source, fn ($query) => $query->whereDate('effective_from', $source))
            ->get()
            ->map(fn (SalaryGrade $cell): array => [
                'effective_from' => $effectiveFrom,
                'grade' => $cell->grade,
                'step' => $cell->step,
                'amount' => $cell->amount,
                'legal_basis' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->all());

        return redirect()->route('payroll.salary-schedule.edit', $effectiveFrom)
            ->with('status', __('New tranche created as a copy of the schedule in force. Enter the new amounts.'));
    }

    public function edit(string $effectiveFrom): View
    {
        abort_unless($this->schedule->effectivityDates()->contains($effectiveFrom), 404);

        return view('payroll.salary-schedule.edit', [
            'effectiveFrom' => $effectiveFrom,
            'grid' => $this->grid($effectiveFrom),
            'legalBasis' => SalaryGrade::query()->whereDate('effective_from', $effectiveFrom)->value('legal_basis'),
        ]);
    }

    public function update(SalaryTrancheRequest $request, string $effectiveFrom): RedirectResponse
    {
        abort_unless($this->schedule->effectivityDates()->contains($effectiveFrom), 404);

        DB::transaction(function () use ($request, $effectiveFrom): void {
            SalaryGrade::query()->whereDate('effective_from', $effectiveFrom)->delete();
            $now = now();
            $rows = [];

            foreach ($request->validated('amounts') as $grade => $steps) {
                foreach ($steps as $step => $amount) {
                    if ($amount === null || $amount === '' || (int) $grade < 1 || (int) $grade > 33 || (int) $step < 1 || (int) $step > 8) {
                        continue;
                    }

                    $rows[] = [
                        'effective_from' => $effectiveFrom,
                        'grade' => (int) $grade,
                        'step' => (int) $step,
                        'amount' => Money::toDecimal(Money::toCentavos((string) $amount)),
                        'legal_basis' => $request->validated('legal_basis'),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            SalaryGrade::insert($rows);
        });

        return redirect()->route('payroll.salary-schedule.index', ['effective' => $effectiveFrom])->with('status', __('Salary Schedule saved.'));
    }

    /**
     * @return Collection<int, Collection<int, string>> Grade => step => national amount.
     */
    private function grid(string $effectiveFrom): Collection
    {
        return SalaryGrade::query()
            ->whereDate('effective_from', $effectiveFrom)
            ->orderBy('grade')
            ->orderBy('step')
            ->get()
            ->groupBy('grade')
            ->map(fn (Collection $cells): Collection => $cells->mapWithKeys(fn (SalaryGrade $cell): array => [$cell->step => $cell->amount]));
    }
}
