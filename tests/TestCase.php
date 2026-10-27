<?php

namespace Tests;

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Seed roles and permissions after each database refresh.
     */
    protected bool $seed = true;

    protected string $seeder = RolePermissionSeeder::class;
}
