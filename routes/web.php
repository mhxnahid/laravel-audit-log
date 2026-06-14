<?php

use Illuminate\Support\Facades\Route;
use Mxnwire\AuditLog\Http\Controllers\ActivityLogController;
use Mxnwire\AuditLog\Http\Middleware\AuthorizeAuditLog;

$prefix = config('audit-log.route_prefix', 'activity-logs');

// The config gate is a Closure, but Laravel casts every route middleware entry
// to a string — so it is wrapped in a class-based middleware instead of being
// passed inline (which fatals: "Object of class Closure could not be converted
// to string").
Route::prefix($prefix)
    ->middleware(['web', 'auth', AuthorizeAuditLog::class])
    ->group(function () {
        Route::get('/data', [ActivityLogController::class, 'data'])->name('audit-log.data');
        Route::get('/',     [ActivityLogController::class, 'index'])->name('audit-log.index');
    });
