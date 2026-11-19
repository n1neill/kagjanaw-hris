<?php

use App\Enums\EmploymentStatus;
use App\Enums\PayBasis;
use App\Enums\Role;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmploymentType;
use App\Models\Position;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * A complete, valid employee form submission.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function employeePayload(array $overrides = []): array
{
    return [
        'last_name' => 'Dela Cruz',
        'first_name' => 'Juan',
        'middle_name' => 'Garcia',
        'name_extension' => 'Jr.',
        'sex' => 'male',
        'civil_status' => 'married',
        'birth_date' => '1990-05-15',
        'birth_place' => 'Cagdianao, Dinagat Islands',
        'email' => 'juan@cagdianao.local',
        'mobile_number' => '0917-123-4567',
        'residential_address' => 'Purok 2, Brgy. Poblacion, Cagdianao',
        'emergency_contact_name' => 'Maria Dela Cruz',
        'emergency_contact_number' => '09181234567',
        'department_id' => Department::factory()->create()->id,
        'position_id' => Position::factory()->create(['salary_grade' => 11])->id,
        'employment_type_id' => EmploymentType::factory()->create()->id,
        'salary_grade' => '11',
        'salary_step' => '1',
        'date_hired' => '2026-01-05',
        'employment_status' => 'active',
        'separation_date' => '',
        'gsis_number' => '2002-123-4567',
        'philhealth_number' => '12-345678901-2',
        'pagibig_number' => '1234-5678-9012',
        'tin' => '123-456-789-000',
        'biometric_user_id' => '1024',
        ...$overrides,
    ];
}

describe('index', function () {
    it('shows HR officers every employee in service by default', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        Employee::factory()->create(['last_name' => 'Active-Person']);
        Employee::factory()->separated()->create(['last_name' => 'Retired-Person']);

        $response = $this->actingAs($hrOfficer)->get(route('employees.index'));

        $response->assertSee('Active-Person')->assertDontSee('Retired-Person');
    });

    it('filters by department and status', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        $healthOffice = Department::factory()->create();
        Employee::factory()->separated()->create(['last_name' => 'Health-Retiree', 'department_id' => $healthOffice->id]);
        Employee::factory()->create(['last_name' => 'Health-Active', 'department_id' => $healthOffice->id]);
        Employee::factory()->separated()->create(['last_name' => 'Other-Retiree']);

        $response = $this->actingAs($hrOfficer)->get(route('employees.index', ['department' => $healthOffice->id, 'status' => 'resigned']));

        $response->assertSee('Health-Retiree')
            ->assertDontSee('Health-Active')
            ->assertDontSee('Other-Retiree');
    });

    it('shows department heads only the employees of their own department', function () {
        $department = Department::factory()->create();
        $head = Employee::factory()->withAccount(Role::DepartmentHead)->create(['department_id' => $department->id]);
        Employee::factory()->create(['last_name' => 'Same-Office', 'department_id' => $department->id]);
        Employee::factory()->create(['last_name' => 'Other-Office']);

        $response = $this->actingAs($head->user)->get(route('employees.index', ['department' => Department::factory()->create()->id]));

        $response->assertSee('Same-Office')->assertDontSee('Other-Office');
    });

    it('shows a department head without an employee record no employees', function () {
        $head = User::factory()->departmentHead()->create();
        Employee::factory()->create(['last_name' => 'Someone-Else']);

        $response = $this->actingAs($head)->get(route('employees.index'));

        $response->assertSee('not linked to an employee record')->assertDontSee('Someone-Else');
    });

    it('forbids employees and system administrators', function (Role $role) {
        $user = User::factory()->withRole($role)->create();

        $response = $this->actingAs($user)->get(route('employees.index'));

        $response->assertForbidden();
    })->with([
        'employee' => Role::Employee,
        'system administrator' => Role::SystemAdministrator,
    ]);

    it('rejects an unknown status filter', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();

        $response = $this->actingAs($hrOfficer)->get(route('employees.index', ['status' => 'missing']));

        $response->assertSessionHasErrors(['status' => 'The selected status is invalid.']);
    });
});

describe('store', function () {
    it('requires a salary grade and step, and discards a daily rate, for salary-grade employment types', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();

        $this->actingAs($hrOfficer)
            ->post(route('employees.store'), employeePayload(['salary_grade' => '', 'salary_step' => '']))
            ->assertSessionHasErrors(['salary_grade', 'salary_step']);

        $this->actingAs($hrOfficer)
            ->post(route('employees.store'), employeePayload(['daily_rate' => '650']))
            ->assertSessionDoesntHaveErrors();

        expect(Employee::sole()->daily_rate)->toBeNull();
    });

    it('requires a daily rate, and discards the salary grade and step, for daily-rate employment types', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        $jobOrder = EmploymentType::factory()->create(['pay_basis' => PayBasis::DailyRate]);

        $this->actingAs($hrOfficer)
            ->post(route('employees.store'), employeePayload(['employment_type_id' => $jobOrder->id]))
            ->assertSessionHasErrors('daily_rate')
            ->assertSessionDoesntHaveErrors(['salary_grade', 'salary_step']);

        $this->actingAs($hrOfficer)
            ->post(route('employees.store'), employeePayload(['employment_type_id' => $jobOrder->id, 'daily_rate' => '650']))
            ->assertSessionDoesntHaveErrors();

        $employee = Employee::sole();
        expect($employee->daily_rate)->toBe('650.00')
            ->and($employee->salary_grade)->toBeNull()
            ->and($employee->salary_step)->toBeNull();
    });

    it('only accepts a position of the selected department or a general position', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        $accounting = Department::factory()->create();
        $accountant = Position::factory()->create(['department_id' => $accounting->id]);
        $general = Position::factory()->create(['department_id' => null]);
        $engineer = Position::factory()->create(['department_id' => Department::factory()->create()->id]);

        $this->actingAs($hrOfficer)
            ->post(route('employees.store'), employeePayload(['department_id' => $accounting->id, 'position_id' => $engineer->id]))
            ->assertSessionHasErrors('position_id');

        $this->actingAs($hrOfficer)
            ->post(route('employees.store'), employeePayload(['department_id' => $accounting->id, 'position_id' => $accountant->id]))
            ->assertSessionDoesntHaveErrors();

        $this->actingAs($hrOfficer)
            ->post(route('employees.store'), employeePayload(['department_id' => $accounting->id, 'position_id' => $general->id, 'email' => 'other@cagdianao.local', 'biometric_user_id' => '2000']))
            ->assertSessionDoesntHaveErrors();
    });

    it('creates the employee and an Employee-role login account named after the employee ID', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        $payload = employeePayload();

        $response = $this->actingAs($hrOfficer)->post(route('employees.store'), $payload);

        $employee = Employee::where('employee_number', '2026-0001')->sole();
        $response->assertRedirect(route('employees.show', $employee))
            ->assertSessionHas('status', 'Juan G. Dela Cruz Jr. was added and a login account was created.');
        expect($employee->full_name)->toBe('Juan G. Dela Cruz Jr.')
            ->and($employee->mobile_number)->toBe('09171234567')
            ->and($employee->philhealth_number)->toBe('123456789012')
            ->and($employee->employment_status)->toBe(EmploymentStatus::Active);

        $account = $employee->user;
        expect($account->username)->toBe('2026-0001')
            ->and($account->name)->toBe('Juan G. Dela Cruz Jr.')
            ->and($account->email)->toBe('juan@cagdianao.local')
            ->and($account->is_active)->toBeTrue()
            ->and($account->must_change_password)->toBeTrue()
            ->and($account->primaryRole())->toBe(Role::Employee);
    });

    it('shows a temporary password once that lets the employee sign in', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();

        $response = $this->actingAs($hrOfficer)->post(route('employees.store'), employeePayload());

        $temporaryPassword = session('temporaryPassword');
        expect($temporaryPassword)->toHaveLength(12)
            ->toMatch('/[A-Z]/')
            ->toMatch('/[a-z]/')
            ->toMatch('/[0-9]/')
            ->toMatch('/[^A-Za-z0-9]/');
        $this->followRedirects($response)->assertSee($temporaryPassword);
        expect(Hash::check($temporaryPassword, User::where('username', '2026-0001')->sole()->password))->toBeTrue();
    });

    it('stores government ID numbers encrypted', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();

        $this->actingAs($hrOfficer)->post(route('employees.store'), employeePayload());

        $raw = DB::table('employees')->where('employee_number', '2026-0001')->first();
        expect($raw->tin)->not->toContain('123456789000')
            ->and($raw->gsis_number)->not->toContain('20021234567')
            ->and(Employee::where('employee_number', '2026-0001')->sole()->tin)->toBe('123456789000');
    });

    it('rejects an empty submission', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();

        $response = $this->actingAs($hrOfficer)->post(route('employees.store'), []);

        $response->assertSessionHasErrors([
            'last_name' => 'The last name field is required.',
            'first_name' => 'The first name field is required.',
            'sex' => 'The sex field is required.',
            'birth_date' => 'The birth date field is required.',
            'department_id' => 'The department field is required.',
            'position_id' => 'The position field is required.',
            'employment_type_id' => 'The employment type field is required.',
            'date_hired' => 'The date hired field is required.',
        ]);
        $this->assertDatabaseCount('employees', 0);
        $this->assertDatabaseCount('users', 1);
    });

    it('numbers employees in sequence within the year hired', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        Employee::factory()->create(['employee_number' => '2026-0007']);

        $this->actingAs($hrOfficer)->post(route('employees.store'), employeePayload(['email' => 'first@cagdianao.local']));
        $this->actingAs($hrOfficer)->post(route('employees.store'), employeePayload(['email' => 'second@cagdianao.local', 'biometric_user_id' => '1025']));

        expect(Employee::where('last_name', 'Dela Cruz')->orderBy('id')->pluck('employee_number')->all())
            ->toBe(['2026-0008', '2026-0009']);
    });

    it('restarts the numbering for each year of hire', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        Employee::factory()->create(['employee_number' => '2026-0042']);

        $this->actingAs($hrOfficer)->post(route('employees.store'), employeePayload(['date_hired' => '2027-02-01']));

        expect(Employee::where('last_name', 'Dela Cruz')->sole()->employee_number)->toBe('2027-0001');
    });

    it('ignores an employee ID typed into the form', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();

        $this->actingAs($hrOfficer)->post(route('employees.store'), employeePayload(['employee_number' => 'HACKED-1']));

        expect(Employee::where('last_name', 'Dela Cruz')->sole()->employee_number)->toBe('2026-0001');
    });

    it('rejects malformed government ID and mobile numbers', function (string $field, string $value, string $message) {
        $hrOfficer = User::factory()->hrOfficer()->create();

        $response = $this->actingAs($hrOfficer)->post(route('employees.store'), employeePayload([$field => $value]));

        $response->assertSessionHasErrors([$field => $message]);
        $this->assertDatabaseCount('employees', 0);
    })->with([
        'philhealth too short' => ['philhealth_number', '12-3456', 'The PhilHealth number must have 12 digits.'],
        'pag-ibig with letters' => ['pagibig_number', '1234-ABCD-9012', 'The Pag-IBIG MID number must have 12 digits.'],
        'tin too long' => ['tin', '123-456-789-0000', 'The TIN must have 9 to 12 digits.'],
        'landline instead of mobile' => ['mobile_number', '(086) 826-1234', 'Enter a Philippine mobile number, e.g. 09171234567.'],
    ]);

    it('requires a separation date for a separated employee', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();

        $response = $this->actingAs($hrOfficer)->post(route('employees.store'), employeePayload(['employment_status' => 'resigned']));

        $response->assertSessionHasErrors(['separation_date' => 'Enter the separation date for a separated employee.']);
    });

    it('forbids department heads from adding employees', function () {
        $head = User::factory()->departmentHead()->create();

        $response = $this->actingAs($head)->post(route('employees.store'), employeePayload());

        $response->assertForbidden();
        $this->assertDatabaseCount('employees', 0);
    });
});

describe('show', function () {
    it('shows HR officers the full record including government IDs', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        $employee = Employee::factory()->create(['tin' => '123456789000', 'residential_address' => 'Purok 3, Brgy. Valencia']);

        $response = $this->actingAs($hrOfficer)->get(route('employees.show', $employee));

        $response->assertSee($employee->full_name)
            ->assertSee('123456789000')
            ->assertSee('Purok 3, Brgy. Valencia')
            ->assertSee('201 File');
    });

    it('shows department heads a basic profile of their own staff without sensitive details', function () {
        $department = Department::factory()->create();
        $head = Employee::factory()->withAccount(Role::DepartmentHead)->create(['department_id' => $department->id]);
        $staff = Employee::factory()->create([
            'department_id' => $department->id,
            'tin' => '123456789000',
            'residential_address' => 'Purok 3, Brgy. Valencia',
        ]);

        $response = $this->actingAs($head->user)->get(route('employees.show', $staff));

        $response->assertSee($staff->full_name)
            ->assertDontSee('123456789000')
            ->assertDontSee('Purok 3, Brgy. Valencia')
            ->assertDontSee('201 File');
    });

    it('hides employees of other departments from department heads', function () {
        $head = Employee::factory()->withAccount(Role::DepartmentHead)->create();
        $outsider = Employee::factory()->create();

        $response = $this->actingAs($head->user)->get(route('employees.show', $outsider));

        $response->assertNotFound();
    });

    it('hides other employees from an employee who changes the ID in the URL', function () {
        $employee = Employee::factory()->withAccount()->create();
        $colleague = Employee::factory()->create(['department_id' => $employee->department_id]);

        $response = $this->actingAs($employee->user)->get(route('employees.show', $colleague));

        $response->assertNotFound();
    });

    it('escapes names entered on the record', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        $employee = Employee::factory()->create(['first_name' => '<img src=x onerror=alert(1)>']);

        $response = $this->actingAs($hrOfficer)->get(route('employees.show', $employee));

        $response->assertSee('&lt;img src=x onerror=alert(1)&gt;', escape: false)
            ->assertDontSee('<img src=x onerror=alert(1)>', escape: false);
    });
});

describe('update', function () {
    it('records a Job Order worker\'s daily rate', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        $employee = Employee::factory()->create();
        $jobOrder = EmploymentType::factory()->create(['pay_basis' => PayBasis::DailyRate]);

        $this->actingAs($hrOfficer)->put(route('employees.update', $employee), employeePayload([
            'employment_type_id' => $jobOrder->id,
            'salary_grade' => '',
            'salary_step' => '',
            'daily_rate' => '550.50',
        ]));

        expect($employee->fresh()->only(['salary_grade', 'daily_rate']))->toBe(['salary_grade' => null, 'daily_rate' => '550.50']);
    });

    it('keeps the login account name, username and email in step with the record', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        $employee = Employee::factory()->withAccount()->create();

        $response = $this->actingAs($hrOfficer)->put(route('employees.update', $employee), employeePayload([
            'first_name' => 'Juana',
            'last_name' => 'Santos',
            'middle_name' => '',
            'name_extension' => '',
            'email' => 'juana@cagdianao.local',
        ]));

        $response->assertRedirect(route('employees.show', $employee));
        $account = $employee->user->refresh();
        expect($account->username)->toBe($employee->employee_number)
            ->and($account->name)->toBe('Juana Santos')
            ->and($account->email)->toBe('juana@cagdianao.local');
    });

    it('deactivates the login account when the employee separates and restores it on return', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        $employee = Employee::factory()->withAccount()->create();

        $this->actingAs($hrOfficer)->put(route('employees.update', $employee), employeePayload([
            'employment_status' => 'retired',
            'separation_date' => '2026-09-30',
        ]))->assertSessionHasNoErrors();

        expect($employee->user->refresh()->is_active)->toBeFalse();

        $this->actingAs($hrOfficer)->put(route('employees.update', $employee), employeePayload())->assertSessionHasNoErrors();

        expect($employee->user->refresh()->is_active)->toBeTrue();
    });

    it('keeps its own biometric ID when unchanged', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        $employee = Employee::factory()->withAccount()->create(['biometric_user_id' => '77']);

        $response = $this->actingAs($hrOfficer)->put(route('employees.update', $employee), employeePayload([
            'biometric_user_id' => '77',
        ]));

        $response->assertSessionHasNoErrors();
    });

    it('never changes the employee ID or login username', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        $employee = Employee::factory()->withAccount()->create(['employee_number' => '2015-0101']);

        $this->actingAs($hrOfficer)->put(route('employees.update', $employee), employeePayload(['employee_number' => '2026-0099']));

        expect($employee->refresh()->employee_number)->toBe('2015-0101')
            ->and($employee->user->username)->toBe('2015-0101');
    });

    it('forbids an employee from editing their own record', function () {
        $employee = Employee::factory()->withAccount()->create(['salary_grade' => 11]);

        $response = $this->actingAs($employee->user)->put(route('employees.update', $employee), employeePayload([
            'salary_grade' => '33',
        ]));

        $response->assertForbidden();
        expect($employee->refresh()->salary_grade)->toBe(11);
    });
});
