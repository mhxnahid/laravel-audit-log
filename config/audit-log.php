<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Permission gate
    |--------------------------------------------------------------------------
    | The ability string used in the route middleware `can:X` guard. Must be a
    | permission defined in your host app (e.g. via spatie/laravel-permission).
    */
    'gate' => env('AUDIT_LOG_GATE', 'ACTIVITY_LOGS_ALL'),

    /*
    |--------------------------------------------------------------------------
    | Role resolver
    |--------------------------------------------------------------------------
    | A callable that returns the actor's role label from a User model instance.
    | null = falls back to ->role ?? ->urole ?? null.
    |
    | Example (after vendor:publish):
    |   'role_resolver' => fn ($user) => $user->urole ?? null,
    */
    'role_resolver' => null,

    /*
    |--------------------------------------------------------------------------
    | Route prefix
    |--------------------------------------------------------------------------
    | URL prefix for the viewer routes. Changing this also changes the named
    | routes `audit-log.index` and `audit-log.data`.
    */
    'route_prefix' => 'mxn/audit-logs',

    /*
    |--------------------------------------------------------------------------
    | User model
    |--------------------------------------------------------------------------
    | The Eloquent model used to populate the "User" filter dropdown.
    | The model must have `id` and `name` columns.
    | Set to null to disable the user filter dropdown entirely.
    */
    'user_model' => 'App\\Models\\User',

    /*
    |--------------------------------------------------------------------------
    | Viewer layout
    |--------------------------------------------------------------------------
    | The Blade layout the viewer page extends. Override after vendor:publish
    | to wrap the viewer inside your own app shell. The layout must
    | @yield('content') and @yield('script').
    |
    | Defaults to the package's self-contained Bootstrap 5 page — no extra
    | setup needed to get the viewer working out of the box.
    */
    'layout' => 'audit-log::layouts.app',

];
