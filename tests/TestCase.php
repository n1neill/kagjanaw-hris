<?php

namespace Tests;

use Database\Seeders\CoreReferenceDataSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Roles, permissions and leave types are reference data every feature
     * depends on, so they are seeded once when the test database is refreshed.
     */
    protected bool $seed = true;

    protected string $seeder = CoreReferenceDataSeeder::class;
}
