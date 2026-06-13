<?php

use Illuminate\Support\Facades\Route;
use Mxnwire\AuditLog\Http\Controllers\ActivityLogController;

$prefix = config('audit-log.route_prefix', 'activity-logs');
$gate   = config('audit-log.gate', 'ACTIVITY_LOGS_ALL');

Route::prefix($prefix)
    ->middleware(['web', 'auth', 'can:' . $gate])
    ->group(function () {
        Route::get('/data', [ActivityLogController::class, 'data'])->name('audit-log.data');
        Route::get('/',     [ActivityLogController::class, 'index'])->name('audit-log.index');
    });
