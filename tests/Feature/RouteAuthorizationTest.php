<?php

use App\Enums\Role;
use App\Models\AllowanceType;
use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\BiometricDevice;
use App\Models\DeductionRate;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeAllowance;
use App\Models\EmployeeDeduction;
use App\Models\EmployeeDocument;
use App\Models\EmploymentType;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Models\Position;
use App\Models\User;
use Database\Seeders\PayrollReferenceDataSeeder;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/*
 * The complete access matrix: every signed-in route and the roles that may
 * use it, written out here independently of the route middleware, policies
 * and form requests, so a mistake in any of them fails this test.
 *
 * Every record in the URLs belongs to an employee of another office. Own-record
 * and own-office access therefore never applies: only roles with access to
 * every office are listed for them. Own-record and department-head access is
 * covered by each module's own tests.
 */

/**
 * @return array<string, list<Role>>
 */
function routeAccessMatrix(): array
{
    $everyone = Role::cases();
    $administrator = [Role::SystemAdministrator];
    $hr = [Role::HrOfficer];
    // Roles with an employee record: self-service.
    $staff = [Role::HrOfficer, Role::MunicipalMayor, Role::DepartmentHead, Role::Employee];
    // HR for every office, the Mayor and department heads for their own.
    $supervisors = [Role::HrOfficer, Role::MunicipalMayor, Role::DepartmentHead];
    $nobody = [];

    return [
        'dashboard' => $everyone,
        'profile.edit' => $everyone,
        'profile.update' => $everyone,
        'profile.theme' => $everyone,
        'password.confirm' => $everyone,
        'password.update' => $everyone,
        'password.change' => $everyone,
        'password.change.update' => $everyone,
        'logout' => $everyone,
        'notifications.index' => $everyone,
        'notifications.read-all' => $everyone,
        'notifications.open' => $everyone,

        'announcements.index' => $everyone,
        'announcements.show' => $everyone,
        'announcements.create' => $hr,
        'announcements.store' => $hr,
        'announcements.edit' => $hr,
        'announcements.update' => $hr,
        'announcements.destroy' => $hr,

        'my-record' => $staff,
        'employees.index' => $supervisors,
        'employees.show' => $hr,
        'employees.create' => $hr,
        'employees.store' => $hr,
        'employees.edit' => $hr,
        'employees.update' => $hr,
        'employees.documents.show' => $hr,
        'employees.documents.download' => $hr,
        'employees.documents.store' => $hr,
        'employees.documents.destroy' => $hr,

        'attendance.my' => $staff,
        'attendance.summary' => $supervisors,
        'attendance.summary.export' => $supervisors,
        'attendance.dtr.show' => $hr,
        'attendance.dtr.pdf' => $hr,
        'attendance.dtr.edit' => $hr,
        'attendance.dtr.update' => $hr,
        'attendance.dtr.validate' => $hr,
        'attendance.dtr.reprocess' => $hr,
        'attendance.logs' => $hr,
        'attendance.import.create' => $hr,
        'attendance.import.store' => $hr,
        'attendance.import.template' => $hr,

        'leave.my' => $staff,
        'leave.requests.create' => $staff,
        'leave.requests.store' => $staff,
        'leave.requests.index' => $hr,
        'leave.endorsements' => [Role::MunicipalMayor, Role::DepartmentHead],
        'leave.requests.show' => $hr,
        'leave.requests.attachment' => $hr,
        // The application was already endorsed by its own department head.
        'leave.requests.endorse' => $nobody,
        'leave.requests.approve' => $hr,
        'leave.requests.reject' => $hr,
        // Only the employee who filed it may withdraw a pending application.
        'leave.requests.cancel' => $nobody,
        'leave.card' => $hr,
        'leave.credits' => $hr,
        'leave.adjustments.store' => $hr,
        'leave.certificate' => $hr,
        'leave.posting.index' => $hr,
        'leave.posting.store' => $hr,
        'leave.opening-balances.create' => $hr,
        'leave.opening-balances.store' => $hr,
        'leave.opening-balances.template' => $hr,
        'leave.types.index' => $hr,
        'leave.types.create' => $hr,
        'leave.types.store' => $hr,
        'leave.types.edit' => $hr,
        'leave.types.update' => $hr,
        'leave.reports.utilization' => $supervisors,
        'leave.reports.utilization.export' => $supervisors,

        'payroll.payslips.my' => $staff,
        'payroll.payslips.show' => $hr,
        'payroll.runs.index' => $hr,
        'payroll.runs.store' => $hr,
        'payroll.runs.show' => $hr,
        'payroll.runs.destroy' => $hr,
        'payroll.runs.compute' => $hr,
        'payroll.runs.approve' => $hr,
        'payroll.runs.reopen' => $hr,
        'payroll.runs.release' => $hr,
        'payroll.runs.register' => $hr,
        'payroll.employees.show' => $hr,
        'payroll.employees.allowances.store' => $hr,
        'payroll.employees.allowances.destroy' => $hr,
        'payroll.employees.deductions.store' => $hr,
        'payroll.employees.deductions.edit' => $hr,
        'payroll.employees.deductions.update' => $hr,
        'payroll.allowances.index' => $hr,
        'payroll.allowances.create' => $hr,
        'payroll.allowances.store' => $hr,
        'payroll.allowances.edit' => $hr,
        'payroll.allowances.update' => $hr,
        'payroll.settings.edit' => $hr,
        'payroll.settings.update' => $hr,
        'payroll.salary-schedule.index' => $hr,
        'payroll.salary-schedule.store' => $hr,
        'payroll.salary-schedule.edit' => $hr,
        'payroll.salary-schedule.update' => $hr,
        'payroll.rates.index' => $hr,
        'payroll.rates.contributions.create' => $hr,
        'payroll.rates.contributions.store' => $hr,
        'payroll.rates.contributions.edit' => $hr,
        'payroll.rates.contributions.update' => $hr,
        'payroll.rates.tax.edit' => $hr,
        'payroll.rates.tax.update' => $hr,
        'payroll.rates.tax.store' => $hr,

        'reports.index' => $supervisors,
        'reports.employees' => $supervisors,
        'reports.employees.export' => $supervisors,
        'reports.payroll' => $hr,
        'reports.payroll.export' => $hr,

        'work-calendar.schedule.edit' => $hr,
        'work-calendar.schedule.update' => $hr,
        'work-calendar.holidays.index' => $hr,
        'work-calendar.holidays.create' => $hr,
        'work-calendar.holidays.store' => $hr,
        'work-calendar.holidays.edit' => $hr,
        'work-calendar.holidays.update' => $hr,
        'work-calendar.holidays.destroy' => $hr,

        'reference-data.departments.index' => [Role::SystemAdministrator, Role::HrOfficer],
        'reference-data.departments.create' => [Role::SystemAdministrator, Role::HrOfficer],
        'reference-data.departments.store' => [Role::SystemAdministrator, Role::HrOfficer],
        'reference-data.departments.edit' => [Role::SystemAdministrator, Role::HrOfficer],
        'reference-data.departments.update' => [Role::SystemAdministrator, Role::HrOfficer],
        'reference-data.positions.index' => [Role::SystemAdministrator, Role::HrOfficer],
        'reference-data.positions.create' => [Role::SystemAdministrator, Role::HrOfficer],
        'reference-data.positions.store' => [Role::SystemAdministrator, Role::HrOfficer],
        'reference-data.positions.edit' => [Role::SystemAdministrator, Role::HrOfficer],
        'reference-data.positions.update' => [Role::SystemAdministrator, Role::HrOfficer],
        'reference-data.employment-types.index' => [Role::SystemAdministrator, Role::HrOfficer],
        'reference-data.employment-types.create' => [Role::SystemAdministrator, Role::HrOfficer],
        'reference-data.employment-types.store' => [Role::SystemAdministrator, Role::HrOfficer],
        'reference-data.employment-types.edit' => [Role::SystemAdministrator, Role::HrOfficer],
        'reference-data.employment-types.update' => [Role::SystemAdministrator, Role::HrOfficer],

        'admin.users.index' => $administrator,
        'admin.users.create' => $administrator,
        'admin.users.store' => $administrator,
        'admin.users.edit' => $administrator,
        'admin.users.update' => $administrator,
        'admin.users.status' => $administrator,
        'admin.devices.index' => $administrator,
        'admin.devices.create' => $administrator,
        'admin.devices.store' => $administrator,
        'admin.devices.show' => $administrator,
        'admin.devices.edit' => $administrator,
        'admin.devices.update' => $administrator,
        'admin.devices.test' => $administrator,
        'admin.devices.sync' => $administrator,
        'admin.settings.edit' => $administrator,
        'admin.settings.update' => $administrator,
        'admin.audit-logs.index' => $administrator,
        'admin.audit-logs.show' => $administrator,
        'admin.backups.index' => $administrator,
        'admin.backups.store' => $administrator,
        'admin.backups.upload' => $administrator,
        'admin.backups.download' => $administrator,
        'admin.backups.restore' => $administrator,
        'admin.backups.destroy' => $administrator,
    ];
}

/**
 * One account per role, all but the administrator with an employee record
 * in the same office, and records belonging to a colleague elsewhere.
 *
 * @return array{users: array<string, User>, parameters: array<string, int|string>, taxTableDate: string}
 */
function routeAuthorizationFixtures(): array
{
    Storage::fake('local');
    Storage::fake(config('backup.disk'));

    $office = Department::factory()->create();
    $otherOffice = Department::factory()->create();

    $users = [Role::SystemAdministrator->value => User::factory()->systemAdministrator()->create()];

    foreach ([Role::HrOfficer, Role::MunicipalMayor, Role::DepartmentHead, Role::Employee] as $role) {
        $users[$role->value] = Employee::factory()->withAccount($role)->create(['department_id' => $office->id])->user;
    }

    foreach ($users as $user) {
        $user->notifications()->create(['id' => (string) Str::uuid(), 'type' => 'test', 'data' => ['url' => '/dashboard']]);
    }

    $otherHead = Employee::factory()->withAccount(Role::DepartmentHead)->create(['department_id' => $otherOffice->id]);
    $colleague = Employee::factory()->withAccount()->create(['department_id' => $otherOffice->id]);

    $document = EmployeeDocument::factory()->for($colleague)->create();
    Storage::disk(EmployeeDocument::DISK)->put($document->path, '%PDF-1.4');

    $leaveRequest = LeaveRequest::factory()->for($colleague)->endorsed()->create([
        'endorsed_by' => $otherHead->user_id,
        'attachment_path' => 'leave-attachments/certificate.pdf',
        'attachment_name' => 'medical-certificate.pdf',
        'attachment_mime' => 'application/pdf',
        'attachment_size' => 8,
    ]);
    Storage::disk(LeaveRequest::ATTACHMENT_DISK)->put('leave-attachments/certificate.pdf', '%PDF-1.4');

    $payslip = PayrollItem::factory()->create([
        'payroll_run_id' => PayrollRun::factory()->released(),
        'employee_id' => $colleague->id,
    ]);

    $backup = 'route-test-'.Str::lower(Str::random(8)).'.zip';
    $zip = new ZipArchive;
    $zip->open(Storage::disk(config('backup.disk'))->path($backup), ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('database.sql', "-- backup\n");
    $zip->addFromString('manifest.json', json_encode([
        'application' => config('app.name'),
        'format' => 1,
        'created_at' => now()->toIso8601String(),
        'trigger' => 'manual',
        'migrations' => DB::table('migrations')->pluck('migration')->all(),
        'file_count' => 0,
    ]));
    $zip->close();

    return [
        'users' => $users,
        'parameters' => [
            'employee' => $colleague->id,
            'document' => $document->id,
            'leaveRequest' => $leaveRequest->id,
            'payrollItem' => $payslip->id,
            'payrollRun' => $payslip->payroll_run_id,
            'allowance' => EmployeeAllowance::factory()->create(['employee_id' => $colleague->id])->id,
            'payrollDeduction' => EmployeeDeduction::factory()->create(['employee_id' => $colleague->id])->id,
            'allowanceType' => AllowanceType::factory()->create()->id,
            'deductionRate' => DeductionRate::query()->value('id'),
            'effectiveFrom' => PayrollReferenceDataSeeder::SALARY_SCHEDULE_EFFECTIVE,
            'date' => now()->subDays(3)->toDateString(),
            'user' => $colleague->user_id,
            'device' => BiometricDevice::factory()->create()->id,
            'auditLog' => AuditLog::factory()->create()->id,
            'backup' => $backup,
            'holiday' => Holiday::factory()->create()->id,
            'department' => $otherOffice->id,
            'position' => Position::factory()->create()->id,
            'employment_type' => EmploymentType::factory()->create()->id,
            'leaveType' => LeaveType::vacation()->id,
            'announcement' => Announcement::factory()->create()->id,
            'format' => 'csv',
        ],
        'taxTableDate' => (string) DB::table('tax_brackets')->min('effective_from'),
    ];
}

/**
 * @param  array{parameters: array<string, int|string>, taxTableDate: string}  $fixtures
 */
function routeAuthorizationUrl(RoutingRoute $route, array $fixtures, ?User $actor): string
{
    $values = [
        ...$fixtures['parameters'],
        'notification' => $actor?->notifications()->value('id') ?? (string) Str::uuid(),
    ];

    if (str_starts_with($route->getName(), 'payroll.rates.tax.')) {
        $values['effectiveFrom'] = $fixtures['taxTableDate'];
    }

    return route($route->getName(), Arr::only($values, $route->parameterNames()));
}

test('every signed-in route declares which roles may use it', function () {
    $signedInRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RoutingRoute $route): bool => $route->getName() !== null && in_array('auth', $route->gatherMiddleware(), true))
        ->map(fn (RoutingRoute $route): string => $route->getName())
        ->sort()
        ->values()
        ->all();

    expect($signedInRoutes)->toBe(collect(array_keys(routeAccessMatrix()))->sort()->values()->all());
});

/*
 * Guests are sent to sign in. Roles without access get 403 Forbidden, or
 * 404 Not Found where a record must not reveal that it exists. Roles with
 * access open every page; their form submissions are covered by each module.
 */
test('admits only the roles allowed to use the route', function (string $routeName, array $allowedRoles) {
    $fixtures = routeAuthorizationFixtures();
    $route = Route::getRoutes()->getByName($routeName);
    $method = collect($route->methods())->reject(fn (string $method): bool => $method === 'HEAD')->first();
    $mismatches = [];

    $guest = $this->call($method, routeAuthorizationUrl($route, $fixtures, null));

    if (! $guest->baseResponse->isRedirect(route('login'))) {
        $mismatches[] = "guest: expected a redirect to sign in, got {$guest->baseResponse->getStatusCode()}";
    }

    foreach (Role::cases() as $role) {
        $user = $fixtures['users'][$role->value];
        $isAllowed = in_array($role, $allowedRoles, true);

        if ($isAllowed && $method !== 'GET') {
            continue;
        }

        $status = $this->actingAs($user)->call($method, routeAuthorizationUrl($route, $fixtures, $user))->baseResponse->getStatusCode();

        if ($isAllowed && $status >= 400) {
            $mismatches[] = "{$role->value}: expected access, got {$status}";
        }

        if (! $isAllowed && ! in_array($status, [403, 404], true)) {
            $mismatches[] = "{$role->value}: expected 403 or 404, got {$status}";
        }
    }

    expect($mismatches)->toBe([]);
})->with(fn (): array => collect(routeAccessMatrix())
    ->map(fn (array $roles, string $routeName): array => [$routeName, $roles])
    ->all());
