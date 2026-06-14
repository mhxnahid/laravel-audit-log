<?php

namespace Mxnwire\AuditLog;

use Illuminate\Support\ServiceProvider;
use Mxnwire\AuditLog\Contracts\ActivityTypeRegistryContract;
use Mxnwire\AuditLog\Services\ActivityLogService;

class AuditLogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/audit-log.php', 'audit-log');

        $this->app->singleton(ActivityLogService::class);

        // Bind the registry named in config('audit-log.registry'), defaulting
        // to the live-database ActivityTypeRegistry. Host apps point this at an
        // AbstractActivityTypeRegistry subclass to serve filter options from a
        // fixed vocabulary instead. bindIf so an explicit container binding in
        // the host app still takes precedence.
        $this->app->bindIf(
            ActivityTypeRegistryContract::class,
            fn () => $this->app->make(config('audit-log.registry', ActivityTypeRegistry::class))
        );
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'audit-log');

        $this->publishes([
            __DIR__ . '/../config/audit-log.php'
                => config_path('audit-log.php'),
            __DIR__ . '/../resources/views'
                => resource_path('views/vendor/audit-log'),
        ], 'audit-log');
    }
}
