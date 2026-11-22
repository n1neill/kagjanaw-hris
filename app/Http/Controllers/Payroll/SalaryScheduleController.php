<?php

namespace App\Http\Controllers\Payroll;

use App\Enums\ChangeAction;
use App\Http\Controllers\Concerns\SendsChangesForApproval;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\NewEffectivityRequest;
use App\Http\Requests\Payroll\SalaryTrancheRequest;
use App\Models\SalaryGrade;
use App\Services\Approvals\ChangeRequestService;
use App\Services\Payroll\PayrollSettings;
use App\Services\Payroll\SalarySchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The national Salary Schedule by tranche, with the LGU rates at the
 * income-class percentage. HR adds a tranche (starting as a copy of the one
 * in force) and edits its amounts when DBM issues a new schedule.
 */
class SalaryScheduleController extends Controller
{
    use SendsChangesForApproval;

    public function __construct(
        private SalarySchedule $schedule,
        private PayrollSettings $settings,
        private ChangeRequestService $changes,
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

        $outcome = $this->changes->perform($request->user(), ChangeAction::CreateSalaryTranche, ['effective_from' => $effectiveFrom]);

        if ($outcome->isQueued()) {
            return $this->sentForApproval(route('payroll.salary-schedule.index'));
        }

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

        $outcome = $this->changes->perform($request->user(), ChangeAction::UpdateSalaryTranche, [
            'effective_from' => $effectiveFrom,
            'amounts' => $request->validated('amounts'),
            'legal_basis' => $request->validated('legal_basis'),
        ]);

        if ($outcome->isQueued()) {
            return $this->sentForApproval(route('payroll.salary-schedule.index', ['effective' => $effectiveFrom]));
        }

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
