<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Access gate
    |--------------------------------------------------------------------------
    | A callable that decides whether the current request may view the audit
    | logs. It receives the authenticated user (or null) and must return a
    | boolean. Return true to allow access, false to deny (403).
    |
    | Example (after vendor:publish):
    |   'gate' => fn ($user) => $user?->can('ACTIVITY_LOGS_ALL') ?? false,
    */
    'gate' => fn ($user) => $user?->role === 'admin',

    /*
    |--------------------------------------------------------------------------
    | Role resolver
    |--------------------------------------------------------------------------
    | A callable that returns the actor's role label from a User model instance.
    | Receives the authenticated user (or null) and returns a string or null.
    |
    | Example (after vendor:publish):
    |   'role_resolver' => fn ($user) => $user?->roles->first()?->name,
    */
    'role_resolver' => fn ($user) => $user?->role ?? null,

    /*
    |--------------------------------------------------------------------------
    | Activity type registry
    |--------------------------------------------------------------------------
    | The class that supplies the viewer's filter dropdown options (log names,
    | events, subject types). Must implement
    | Mxnwire\AuditLog\Contracts\ActivityTypeRegistryContract.
    |
    | Defaults to the DB-backed registry, which `SELECT DISTINCT`s the live
    | activity_log table. To derive the options from a fixed vocabulary instead,
    | point this at an Mxnwire\AuditLog\AbstractActivityTypeRegistry subclass:
    |   'registry' => \App\Activity\ActivityType::class,
    */
    'registry' => \Mxnwire\AuditLog\ActivityTypeRegistry::class,

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
