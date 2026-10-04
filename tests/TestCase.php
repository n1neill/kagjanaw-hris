<?php

namespace Tests;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Roles and permissions are reference data every feature depends on,
     * so they are seeded once when the test database is refreshed.
     */
    protected bool $seed = true;

    protected string $seeder = RolesAndPermissionsSeeder::class;
}
