<?php

use Illuminate\Support\Facades\Route;
use Mxnwire\AuditLog\Http\Controllers\AuditLogController;
use Mxnwire\AuditLog\Http\Middleware\AuthorizeAuditLog;

$prefix = config('audit-log.route_prefix', 'audit-logs');

Route::prefix($prefix)
    ->middleware(['web', 'auth', AuthorizeAuditLog::class])
    ->group(function () {
        Route::get('/data', [AuditLogController::class, 'data'])->name('audit-log.data');
        Route::get('/',     [AuditLogController::class, 'index'])->name('audit-log.index');
    });
