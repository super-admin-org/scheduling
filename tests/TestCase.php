<?php

namespace SuperAdmin\Admin\Scheduling\Tests;

use Illuminate\Support\Arr;
use Orchestra\Testbench\TestCase as Orchestra;
use ReflectionClass;
use SuperAdmin\Admin\AdminServiceProvider;
use SuperAdmin\Admin\Auth\Database\AdminTablesSeeder;
use SuperAdmin\Admin\Auth\Database\Administrator;
use SuperAdmin\Admin\Facades\Admin;
use SuperAdmin\Admin\Scheduling\SchedulingServiceProvider;

/**
 * Boots Super Admin with this extension in Orchestra Testbench and logs in the seeded administrator.
 */
abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [AdminServiceProvider::class, SchedulingServiceProvider::class];
    }

    protected function getPackageAliases($app)
    {
        return ['Admin' => Admin::class];
    }

    protected function defineEnvironment($app)
    {
        $config = require static::corePath().'/config/admin.php';
        $config['bootstrap'] = __DIR__.'/fixtures/bootstrap.php';
        $config['auth']['controller'] = \SuperAdmin\Admin\Controllers\AuthController::class;

        $app['config']->set('admin', $config);

        foreach (Arr::dot(Arr::get($config, 'auth', []), 'auth.') as $key => $value) {
            $app['config']->set($key, $value);
        }

        $app['config']->set('cache.default', 'array');
    }

    protected function defineDatabaseMigrations()
    {
        $this->loadMigrationsFrom(static::corePath().'/database/migrations');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AdminTablesSeeder::class);

        Admin::routes();

        $this->actingAs(Administrator::query()->first(), 'admin');
    }

    protected static function corePath(): string
    {
        return dirname((new ReflectionClass(AdminServiceProvider::class))->getFileName(), 2);
    }
}
