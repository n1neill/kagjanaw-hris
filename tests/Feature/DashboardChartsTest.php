<?php

use App\Enums\DtrStatus;
use App\Enums\PayrollRunStatus;
use App\Models\DailyTimeRecord;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Services\DashboardChartService;

beforeEach(function () {
    $this->travelTo('2026-10-15 09:00');
    $this->charts = app(DashboardChartService::class);
});

/**
 * @param  array{labels: list<string>, series: list<array{label: string, values: list<int|float>}>}  $chart
 * @return array<string, array<string, int|float>>
 */
function chartValues(array $chart): array
{
    return collect($chart['series'])
        ->mapWithKeys(fn (array $series): array => [$series['label'] => array_combine($chart['labels'], $series['values'])])
        ->all();
}

it('counts employees on time, late, on leave and absent on each working day of the month', function () {
    DailyTimeRecord::factory()->count(2)->create(['work_date' => '2026-10-01']);
    DailyTimeRecord::factory()->late()->create(['work_date' => '2026-10-01']);
    DailyTimeRecord::factory()->absent()->create(['work_date' => '2026-10-01']);
    DailyTimeRecord::factory()->create(['work_date' => '2026-10-02', 'status' => DtrStatus::OnLeave]);
    DailyTimeRecord::factory()->missingPunch()->late(15)->create(['work_date' => '2026-10-02']);
    DailyTimeRecord::factory()->create(['work_date' => '2026-10-03', 'status' => DtrStatus::RestDay]);
    DailyTimeRecord::factory()->create(['work_date' => '2026-09-30']);

    $chart = $this->charts->attendanceTrend();

    expect($chart['type'])->toBe('column-stacked')
        ->and($chart['labels'])->toBe(['Oct 1', 'Oct 2'])
        ->and(chartValues($chart))->toBe([
            'On time' => ['Oct 1' => 2, 'Oct 2' => 0],
            'Late' => ['Oct 1' => 1, 'Oct 2' => 1],
            'On leave' => ['Oct 1' => 0, 'Oct 2' => 1],
            'Absent' => ['Oct 1' => 1, 'Oct 2' => 0],
        ]);
});

it('limits the attendance trend to one department', function () {
    $office = Department::factory()->create();
    DailyTimeRecord::factory()->create([
        'work_date' => '2026-10-01',
        'employee_id' => Employee::factory()->create(['department_id' => $office->id]),
    ]);
    DailyTimeRecord::factory()->count(3)->create(['work_date' => '2026-10-01']);

    expect(chartValues($this->charts->attendanceTrend($office->id))['On time'])->toBe(['Oct 1' => 1]);
});

it('counts only the employee\'s own working days this month', function () {
    $employee = Employee::factory()->create();
    DailyTimeRecord::factory()->for($employee)->create(['work_date' => '2026-10-01']);
    DailyTimeRecord::factory()->for($employee)->late()->create(['work_date' => '2026-10-02']);
    DailyTimeRecord::factory()->for($employee)->absent()->create(['work_date' => '2026-10-05']);
    DailyTimeRecord::factory()->create(['work_date' => '2026-10-01']);

    expect(chartValues($this->charts->myAttendanceMonth($employee)))->toBe([
        'On time' => ['October' => 1],
        'Late' => ['October' => 1],
        'On leave' => ['October' => 0],
        'Absent' => ['October' => 1],
    ]);
});

it('totals the approved leave days of this year by leave type', function () {
    LeaveRequest::factory()->approved()->between('2026-03-02', '2026-03-04')->create();
    LeaveRequest::factory()->approved()->between('2026-04-06', '2026-04-06')->create(['leave_type_id' => LeaveType::sick()->id]);
    LeaveRequest::factory()->between('2026-05-04', '2026-05-05')->create();
    LeaveRequest::factory()->approved()->between('2025-12-01', '2025-12-02')->create();

    $chart = $this->charts->leaveDaysByType();

    expect($chart['type'])->toBe('bar')
        ->and(chartValues($chart)['Days of leave'])->toBe([
            LeaveType::vacation()->name => 3.0,
            LeaveType::sick()->name => 1.0,
        ]);
});

it('counts employees in service in each office, largest first', function () {
    $small = Department::factory()->create(['name' => 'Small Office']);
    $large = Department::factory()->create(['name' => 'Large Office']);
    Employee::factory()->create(['department_id' => $small->id]);
    Employee::factory()->separated()->create(['department_id' => $small->id]);
    Employee::factory()->count(3)->create(['department_id' => $large->id]);

    $values = chartValues($this->charts->headcountByDepartment())['Employees'];

    expect($values)->toMatchArray(['Large Office' => 3, 'Small Office' => 1])
        ->and(array_key_first($values))->toBe('Large Office');
});

it('folds the smallest categories into Other', function () {
    $totals = collect(range(1, 10))->mapWithKeys(fn (int $number): array => ["Office {$number}" => $number])->all();

    $chart = $this->charts->singleSeries($totals, 'Employees', 'employees');

    expect($chart['labels'])->toHaveCount(8)
        ->and($chart['labels'][0])->toBe('Office 10')
        ->and(chartValues($chart)['Employees']['Other'])->toBe(1 + 2 + 3);
});

it('totals gross and net pay of the last six approved or released pay periods', function () {
    foreach (range(1, 7) as $month) {
        $period = PayrollPeriod::factory()->create([
            'month' => $month,
            'start_date' => sprintf('2026-%02d-01', $month),
            'end_date' => sprintf('2026-%02d-15', $month),
        ]);
        $run = PayrollRun::factory()->for($period, 'period')->create(['status' => PayrollRunStatus::Approved]);
        PayrollItem::factory()->count(2)->for($run, 'run')->create(['gross_pay' => '1000.00', 'net_pay' => '800.00']);
    }

    $draftPeriod = PayrollPeriod::factory()->create(['month' => 8, 'start_date' => '2026-08-01', 'end_date' => '2026-08-15']);
    PayrollItem::factory()->for(PayrollRun::factory()->for($draftPeriod, 'period'), 'run')->create();

    $chart = $this->charts->payrollTotals();

    expect($chart['labels'])->toBe(['Feb 1–15', 'Mar 1–15', 'Apr 1–15', 'May 1–15', 'Jun 1–15', 'Jul 1–15'])
        ->and(chartValues($chart)['Gross pay'])->each->toBe(2000.0)
        ->and(chartValues($chart)['Net pay'])->each->toBe(1600.0);
});

it('knows when a chart has nothing to draw', function () {
    expect(DashboardChartService::hasData($this->charts->attendanceTrend()))->toBeFalse();

    DailyTimeRecord::factory()->create(['work_date' => '2026-10-01']);

    expect(DashboardChartService::hasData($this->charts->attendanceTrend()))->toBeTrue();
});
