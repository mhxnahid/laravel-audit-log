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
    |   'gate' => fn ($user) => $user?->can('AUDIT_LOGS_ALL') ?? false,
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
    | Audit type registry
    |--------------------------------------------------------------------------
    | The class that supplies the viewer's filter dropdown options (log names,
    | events, subject types). Must implement
    | Mxnwire\AuditLog\Contracts\AuditTypeRegistryContract.
    |
    | Defaults to the DB-backed registry, which `SELECT DISTINCT`s the live
    | activity_log table. To derive the options from a fixed vocabulary instead,
    | point this at an Mxnwire\AuditLog\AbstractAuditTypeRegistry subclass:
    |   'registry' => \App\Audit\AuditType::class,
    */
    'registry' => \Mxnwire\AuditLog\AuditTypeRegistry::class,

    /*
    |--------------------------------------------------------------------------
    | Request context properties
    |--------------------------------------------------------------------------
    | The properties recorded under each log entry's `__request` context. This
    | array is the single source of truth — add or remove entries to control
    | exactly what gets logged.
    |
    | List the built-in properties you want by name; the package knows how to
    | resolve each from the current request:
    |
    |   'method', 'route', 'url', 'ip', 'user_agent', 'query', 'body'
    |
    | 'query' (query-string params) is enabled by default; 'body' (request input)
    | is not, since it can carry secrets — enable it only when you need it. Both
    | are filtered through the `redact` list below before being stored.
    |
    | To capture something the package doesn't know about, add a keyed entry with
    | your own resolver — `'name' => fn ($request) => mixed`.
    |
    | Header-sourced properties live under the nested `headers` key, each mapping a
    | property name to the inbound header to read. A null/empty result is dropped,
    | so a request without a given header simply omits that property. Any property
    | can also be overridden per-call by passing a field of the same name in the
    | AuditMetadata bag — the explicit value always wins, and is stored only
    | under `__request`.
    */
    'request_context' => [
        'method',
        'route',
        'url',
        'ip',
        'user_agent',
        'query',

        // 'body',   // request input — off by default; redacted via `redact` below

        // Add your own, resolved from anywhere:
        // 'tenant_id' => fn ($request) => $request->header('X-Tenant') ?? optional(tenant())->id,

        'headers' => [
            'request_id'     => 'X-Request-Id',
            'correlation_id' => 'X-Correlation-Id',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Redacted input keys
    |--------------------------------------------------------------------------
    | Field names stripped from logged `query` and `body` input before storage,
    | so secrets never reach the log. Matching is case-insensitive and recurses
    | into nested arrays; a matched value is replaced with '[REDACTED]'. Edit the
    | list to fit your app. Remove the key entirely to fall back to the package
    | defaults shown here.
    */
    'redact' => [
        'password',
        'password_confirmation',
        'current_password',
        'token',
        '_token',
        'secret',
        'api_key',
        'authorization',
        'credit_card',
        'card_number',
        'cvv',
    ],

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
    | Causer attributes
    |--------------------------------------------------------------------------
    | The causer (actor) is eager-loaded onto each log entry and serialized into
    | the viewer's JSON. By default the full model is returned, which is more
    | than the viewer needs and can leak columns you would rather keep out of the
    | API response. List the attribute names to expose here and only those keys
    | survive serialization (the model's own `$hidden` is still honoured first).
    |
    | Set to null to fall back to the full causer model.
    */
    'causer_attributes' => ['id', 'name', 'email'],

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
