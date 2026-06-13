<?php

namespace Mxnwire\AuditLog\Tests;

use Mxnwire\AuditLog\AuditLogServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Spatie\Activitylog\ActivitylogServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            ActivitylogServiceProvider::class,
            AuditLogServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
        ]);
        $app['config']->set('activitylog.enabled', true);
        $app['config']->set('activitylog.default_log_name', 'default');
    }

    protected function defineDatabaseMigrations(): void
    {
        $path = __DIR__ . '/../vendor/spatie/laravel-activitylog/database/migrations/';

        include_once $path . 'create_activity_log_table.php.stub';
        (new \CreateActivityLogTable())->up();

        include_once $path . 'add_event_column_to_activity_log_table.php.stub';
        (new \AddEventColumnToActivityLogTable())->up();

        include_once $path . 'add_batch_uuid_column_to_activity_log_table.php.stub';
        (new \AddBatchUuidColumnToActivityLogTable())->up();
    }
}
