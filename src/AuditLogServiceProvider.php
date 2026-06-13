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

        // Bind the default registry. Host apps can override this binding in
        // their own service provider to supply a hand-crafted registry with
        // a fixed list of log names / events / subject types instead of the
        // live database query that ActivityTypeRegistry performs.
        $this->app->bindIf(ActivityTypeRegistryContract::class, ActivityTypeRegistry::class);
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
            __DIR__ . '/../resources/js/ActivityLogs.vue'
                => resource_path('js/vendor/audit-log/ActivityLogs.vue'),
        ], 'audit-log');
    }
}
