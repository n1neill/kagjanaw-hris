<?php

use App\Models\Department;
use App\Models\Employee;
use App\Providers\AppServiceProvider;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Foundation\Console\ServeCommand;

test('artisan serve passes the Windows temp folder to its workers so uploads work', function () {
    expect(ServeCommand::$passthroughVariables)->toContain('TEMP', 'TMP');
});

it('generates https links once HTTPS is forced', function () {
    config(['app.force_https' => true]);

    (new AppServiceProvider($this->app))->boot();

    expect(route('login'))->toStartWith('https://');
});

it('fails the tests on an N+1 query: a relationship lazy loaded in a loop', function () {
    Employee::factory()->count(2)->create();

    $departments = fn () => Employee::query()->get()->map(fn (Employee $employee): Department => $employee->department);

    expect($departments)->toThrow(LazyLoadingViolationException::class);
});
