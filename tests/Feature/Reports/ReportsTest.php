<?php

use App\Enums\AuditEvent;
use App\Enums\EmploymentStatus;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\User;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * A department head with an employee record in the given office.
 */
function departmentHeadOf(Department $department): User
{
    return Employee::factory()->withAccount(Role::DepartmentHead)->create(['department_id' => $department->id])->user;
}

/**
 * @return list<list<string>>
 */
function csvRows(string $content): array
{
    return array_map('str_getcsv', explode("\n", trim(substr($content, 3))));
}

describe('reports page', function () {
    it('lists the reports each role may run', function (Role $role, array $expected) {
        $user = Employee::factory()->withAccount($role)->create()->user;

        $response = $this->actingAs($user)->get(route('reports.index'));

        expect($response->viewData('reports')->pluck('title')->all())->toBe($expected);
    })->with([
        'hr officer' => [Role::HrOfficer, ['Employee List', 'Attendance Summary', 'Leave Utilization', 'Payroll Summary']],
        'department head' => [Role::DepartmentHead, ['Employee List', 'Attendance Summary', 'Leave Utilization']],
        'municipal mayor' => [Role::MunicipalMayor, ['Employee List', 'Attendance Summary', 'Leave Utilization']],
    ]);

    it('is not available to employees or the System Administrator', function (Role $role) {
        $user = User::factory()->withRole($role)->create();

        $this->actingAs($user)->get(route('reports.index'))->assertForbidden();
        $this->actingAs($user)->get(route('reports.employees'))->assertForbidden();
        $this->actingAs($user)->get(route('leave.reports.utilization'))->assertForbidden();
    })->with([
        'employee' => Role::Employee,
        'system administrator' => Role::SystemAdministrator,
    ]);
});

describe('employee list', function () {
    it('lists employees in service by office for HR', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        $treasury = Department::factory()->create(['name' => 'Municipal Treasurer\'s Office']);
        $active = Employee::factory()->create(['department_id' => $treasury->id]);
        $resigned = Employee::factory()->separated()->create(['department_id' => $treasury->id]);
        $elsewhere = Employee::factory()->create();

        $response = $this->actingAs($hrOfficer)->get(route('reports.employees', ['department' => $treasury->id]));

        expect($response->viewData('employees')->pluck('id')->all())->toBe([$active->id]);

        $all = $this->actingAs($hrOfficer)->get(route('reports.employees', ['status' => 'all']));
        expect($all->viewData('employees')->pluck('id'))->toContain($active->id, $resigned->id, $elsewhere->id);
    });

    it('limits department heads to their own office, whatever they ask for', function () {
        $treasury = Department::factory()->create();
        $other = Department::factory()->create();
        $head = departmentHeadOf($treasury);
        $outsider = Employee::factory()->create(['department_id' => $other->id]);

        $response = $this->actingAs($head)->get(route('reports.employees', ['department' => $other->id]));
        $export = $this->actingAs($head)->get(route('reports.employees.export', ['department' => $other->id, 'format' => 'csv']));

        expect($response->viewData('employees')->pluck('id'))->not->toContain($outsider->id)
            ->and($response->viewData('employees')->pluck('department_id')->unique()->all())->toBe([$treasury->id])
            ->and($export->streamedContent())->not->toContain($outsider->employee_number);
    });

    it('shows nothing to a department head without an office', function () {
        $head = User::factory()->departmentHead()->create();
        Employee::factory()->create();

        $response = $this->actingAs($head)->get(route('reports.employees'));

        expect($response->viewData('employees'))->toBeEmpty()
            ->and($response->viewData('hasDepartment'))->toBeFalse();
    });

    it('exports as CSV without government ID numbers, and records the export', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        $employee = Employee::factory()->create(['tin' => '123456789', 'employee_number' => '2021-0230', 'employment_status' => EmploymentStatus::Active]);

        $response = $this->actingAs($hrOfficer)->get(route('reports.employees.export', ['format' => 'csv']));

        $rows = csvRows($response->streamedContent());
        expect($rows[2])->toBe(['No.', 'Employee ID', 'Name', 'Position', 'Office', 'Employment type', 'Salary grade', 'Date hired', 'Status'])
            ->and($rows[3][1])->toBe('2021-0230')
            ->and($response->streamedContent())->not->toContain('123456789');

        expect(AuditLog::query()->where('event', AuditEvent::Exported)->sole()->user_id)->toBe($hrOfficer->id);
    });

    it('exports as an Excel workbook and a PDF', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        $employee = Employee::factory()->create();

        $xlsx = $this->actingAs($hrOfficer)->get(route('reports.employees.export', ['format' => 'xlsx']));
        $path = tempnam(sys_get_temp_dir(), 'report');
        file_put_contents($path, $xlsx->streamedContent());
        $sheet = IOFactory::load($path)->getActiveSheet();
        unlink($path);

        expect($sheet->getCell('B5')->getValue())->toBe($employee->employee_number)
            ->and($sheet->getCell('A5')->getValue())->toBe(1);
        $this->actingAs($hrOfficer)->get(route('reports.employees.export', ['format' => 'pdf']))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    });

    it('rejects unknown export formats', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();

        $this->actingAs($hrOfficer)->get('/reports/employees/export.html')->assertNotFound();
    });
});

describe('leave utilization for department heads', function () {
    it('covers their own office only', function () {
        $treasury = Department::factory()->create();
        $head = departmentHeadOf($treasury);
        $ownStaff = Employee::factory()->create(['department_id' => $treasury->id]);
        LeaveRequest::factory()->for($ownStaff)->approved()->between('2026-09-07', '2026-09-08')->create();
        LeaveRequest::factory()->approved()->between('2026-09-07', '2026-09-09')->create();

        $response = $this->actingAs($head)->get(route('leave.reports.utilization', ['year' => 2026, 'month' => 9, 'department' => '']));

        $response->assertOk()->assertViewHas('totals', ['employees' => 1, 'days' => 2000, 'with_pay' => 2000, 'without_pay' => 0]);
    });

    it('shows nothing to a department head without an office', function () {
        $head = User::factory()->departmentHead()->create();
        LeaveRequest::factory()->approved()->between('2026-09-07', '2026-09-09')->create();

        $this->actingAs($head)->get(route('leave.reports.utilization', ['year' => 2026, 'month' => 9]))
            ->assertViewHas('totals', ['employees' => 0, 'days' => 0, 'with_pay' => 0, 'without_pay' => 0]);
    });
});

describe('payroll summary', function () {
    it('totals released payrolls by office', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        $released = PayrollRun::factory()->released()->create(['payroll_period_id' => PayrollPeriod::for(2026, 9, 1)->id]);
        $secondHalf = PayrollRun::factory()->released()->create(['payroll_period_id' => PayrollPeriod::for(2026, 9, 2)->id]);
        $draft = PayrollRun::factory()->create(['payroll_period_id' => PayrollPeriod::for(2026, 10, 1)->id]);
        $employee = Employee::factory()->create();
        PayrollItem::factory()->for($released, 'run')->for($employee)->create();
        PayrollItem::factory()->for($secondHalf, 'run')->for($employee)->create();
        PayrollItem::factory()->for($released, 'run')->create(['department_name' => 'Municipal Treasurer\'s Office', 'gross_pay' => '9000.00', 'net_pay' => '8000.00']);
        PayrollItem::factory()->for($draft, 'run')->create(['gross_pay' => '99999.00']);

        $response = $this->actingAs($hrOfficer)->get(route('reports.payroll', ['year' => 2026]));

        $byOffice = $response->viewData('byOffice')->keyBy('office');
        expect($byOffice['Office of the Municipal Mayor']['employees'])->toBe(1)
            ->and($byOffice['Office of the Municipal Mayor']['amounts']['gross_pay'])->toBe(2_419_400)
            ->and($byOffice['Office of the Municipal Mayor']['amounts']['net_pay'])->toBe(2_144_168)
            ->and($byOffice['Municipal Treasurer\'s Office']['amounts']['gross_pay'])->toBe(900_000)
            ->and($response->viewData('totals')['amounts']['gross_pay'])->toBe(3_319_400)
            ->and($response->viewData('totals')['employees'])->toBe(2);
    });

    it('exports the totals with the employer shares', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        $run = PayrollRun::factory()->released()->create(['payroll_period_id' => PayrollPeriod::for(2026, 9, 1)->id]);
        PayrollItem::factory()->for($run, 'run')->create();

        $response = $this->actingAs($hrOfficer)->get(route('reports.payroll.export', ['year' => 2026, 'month' => 9, 'format' => 'csv']));

        $response->assertDownload('payroll-summary-2026-09.csv');
        $rows = csvRows($response->streamedContent());
        expect($rows[3])->toBe(['Office', 'Employees', 'Gross pay', 'GSIS', 'PhilHealth', 'Pag-IBIG', 'Withholding tax', 'Loans and others', 'Total deductions', 'Net pay', 'GSIS (employer)', 'PhilHealth (employer)', 'Pag-IBIG (employer)'])
            ->and($rows[4])->toBe(['Office of the Municipal Mayor', '1', '12097.00', '998.73', '277.43', '100.00', '0.00', '0.00', '1376.16', '10720.84', '1331.64', '277.43', '100.00']);
    });

    it('is only available to the HR Office', function () {
        $head = departmentHeadOf(Department::factory()->create());

        $this->actingAs($head)->get(route('reports.payroll'))->assertForbidden();
        $this->actingAs($head)->get(route('reports.payroll.export', ['format' => 'csv']))->assertForbidden();
    });
});
