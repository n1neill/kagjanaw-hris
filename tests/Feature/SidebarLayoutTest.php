<?php

use App\Models\User;

test('the app shell offers a desktop collapse toggle and keeps the mobile drawer button', function () {
    $this->actingAs(User::factory()->employee()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('x-data="appShell"', false)
        ->assertSee('id="desktop-sidebar"', false)
        ->assertSee('aria-controls="desktop-sidebar"', false)
        ->assertSeeText('Collapse sidebar')
        ->assertSeeText('Open navigation');
});

test('collapsing the sidebar leaves every permitted link in place with its label', function () {
    $this->actingAs(User::factory()->hrOfficer()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('href="'.route('employees.index').'"', false)
        ->assertSee('href="'.route('payroll.runs.index').'"', false)
        ->assertSeeText('Employees')
        ->assertSeeText('Payroll Runs')
        ->assertSeeText('Human Resources');
});

test('links an employee may not use are still not rendered', function () {
    $this->actingAs(User::factory()->employee()->create())
        ->get(route('dashboard'))
        ->assertDontSee('href="'.route('payroll.runs.index').'"', false)
        ->assertDontSee('href="'.route('admin.audit-logs.index').'"', false);
});

test('the system administrator keeps the administration links', function () {
    $this->actingAs(User::factory()->systemAdministrator()->create())
        ->get(route('dashboard'))
        ->assertSee('href="'.route('admin.audit-logs.index').'"', false);
});
