<?php

use Illuminate\Support\Facades\Route;
use Mxnwire\AuditLog\Http\Controllers\ActivityLogController;

$prefix = config('audit-log.route_prefix', 'activity-logs');

Route::prefix($prefix)
    ->middleware(['web', 'auth', function ($request, $next) {
        $gate = config('audit-log.gate');

        abort_unless(is_callable($gate) ? $gate($request->user()) : (bool) $gate, 403);

        return $next($request);
    }])
    ->group(function () {
        Route::get('/data', [ActivityLogController::class, 'data'])->name('audit-log.data');
        Route::get('/',     [ActivityLogController::class, 'index'])->name('audit-log.index');
    });
