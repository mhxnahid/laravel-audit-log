# mxnwire/laravel-audit-log

Audit log viewer and logger layered on top of [spatie/laravel-activitylog](https://github.com/spatie/laravel-activitylog).

Spatie owns the storage (`activity_log` table, polymorphic causer/subject, and the `activitylog:clean` prune command). This package adds:

- An `audit_log()` helper and `AuditLogService` that freeze actor identity and request context at write time.
- A built-in viewer UI (filterable table, no JS build step required) protected by a permission gate.
- `AuditMetadata` / `Change` value objects for structured before/after diffs in the `properties` column.

---

## Requirements

| Dependency | Version |
|---|---|
| PHP | ^8.1 |
| Laravel | 10 or 11 |
| spatie/laravel-activitylog | ^4.0 |

---

## Installation

**1. Install the package via Composer:**

```bash
composer require mxnwire/laravel-audit-log
```

The service provider is auto-discovered — no manual registration needed.

**2. Install and run spatie's migration:**

If you have not already done so for `spatie/laravel-activitylog`:

```bash
php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-migrations"
php artisan migrate
```

**3. Publish the package assets (optional but recommended):**

```bash
php artisan vendor:publish --tag="audit-log"
```

This copies two things:

| Source | Destination |
|---|---|
| `config/audit-log.php` | `config/audit-log.php` |
| `resources/views/` | `resources/views/vendor/audit-log/` |

You only need to publish if you want to customise views or the config file. The package works out of the box without publishing.

---

## Configuration

After publishing, edit `config/audit-log.php`:

```php
return [

    // A callable that decides whether the current request may view the logs.
    // It receives the authenticated user (or null) and must return a boolean.
    // Defaults to admins only; e.g. fn ($user) => $user?->can('AUDIT_LOGS_ALL') ?? false,
    'gate' => fn ($user) => $user?->role === 'admin',

    // A callable that resolves the actor's role label from a User model instance.
    // Receives the authenticated user (or null) and returns a string or null.
    'role_resolver' => fn ($user) => $user?->role ?? null,

    // Class supplying the viewer's filter dropdown options (log names, events,
    // subject types). Defaults to the DB-backed registry, which SELECT DISTINCTs
    // the live activity_log table. Point this at an AbstractAuditTypeRegistry
    // subclass to derive the options from a fixed vocabulary instead.
    // See "Custom audit type registry" below.
    'registry' => \Mxnwire\AuditLog\AuditTypeRegistry::class,

    // Properties recorded under each entry's `__request` context.
    // See "Configuring `__request`" below for the full reference.
    'request_context' => [
        'method', 'route', 'url', 'ip', 'user_agent', 'query',
        // 'body',   // off by default — request input can carry secrets
        'headers' => [
            'request_id'     => 'X-Request-Id',
            'correlation_id' => 'X-Correlation-Id',
        ],
    ],

    // Field names stripped from logged `query`/`body` input before storage.
    // Case-insensitive, recurses into nested arrays. See "Redacting secrets".
    'redact' => [
        'password', 'password_confirmation', 'token', 'secret', // …
    ],

    // URL prefix for the viewer routes.
    // Changing this also renames the named routes `audit-log.index` and `audit-log.data`.
    'route_prefix' => 'mxn/audit-logs',

    // Eloquent model used to populate the "User" filter dropdown.
    // The model must have `id` and `name` columns.
    // Set to null to hide the user filter entirely.
    'user_model' => 'App\\Models\\User',

    // Attributes of the causer (actor) exposed in the viewer's JSON response.
    // Only these keys survive serialization (the model's own `$hidden` still
    // applies first). Set to null to return the full causer model.
    'causer_attributes' => ['id', 'name', 'email'],

    // Blade layout the viewer page extends.
    // Override to wrap the viewer inside your own app shell.
    // The layout must @yield('content') and @yield('script').
    'layout' => 'audit-log::layouts.app',

];
```

---

## Usage

### Logging an audit event

Use the global helper (available automatically — no import needed):

```php
audit_log('user.login');
```

> **Renamed in 2.0.** The helper is now `audit_log()` and the service is `AuditLogService`. The old `activity_log()` helper has been removed — migrate any remaining calls to `audit_log()`.

Or inject the service directly:

```php
use Mxnwire\AuditLog\Services\AuditLogService;

class AuthController extends Controller
{
    public function __construct(private AuditLogService $auditLog) {}

    public function login(Request $request)
    {
        // ... authenticate ...
        $this->auditLog->log('user.login');
    }
}
```

#### Signature

```php
audit_log(
    string $action,                      // dotted verb, e.g. 'broadsheet.viewed'
    ?Model $subject = null,              // the record acted on
    ?AuditMetadata $metadata = null,  // structured diff / extra fields
    ?string $description = null          // optional human-readable label
): void
```

The dotted action maps to spatie's two indexable columns:

```
'broadsheet.updated'  →  log_name = 'broadsheet'
                         event    = 'updated'

'login'               →  log_name = 'login'
                         event    = null
```

The `__actor` and `__request` context is merged into spatie's `properties` JSON column automatically. `__actor` is the actor snapshot; `__request` is built from the `request_context` config (see below):

```json
{
  "__actor":   { "name": "Jane Smith", "role": "admin" },
  "__request": {
    "method": "POST", "route": "posts.store", "url": "...", "ip": "...", "user_agent": "...",
    "query": { "page": "2" },
    "headers": { "request_id": "...", "correlation_id": "..." }
  }
}
```

#### Configuring `__request`

The `request_context` array in `config/audit-log.php` is the single source of
truth for what lands in `__request`. List the built-in properties you want by
name — the package ships resolvers for `method`, `route`, `url`, `ip`,
`user_agent`, `query`, and `body`. `query` is enabled by default; `body` is not,
since it can carry secrets. To capture anything else, add a keyed entry with your
own `fn ($request) => mixed` resolver (this also overrides a built-in of the same
name). Header-sourced values go under the `headers` group, mapping a property
name to the inbound header:

```php
'request_context' => [
    // Enable built-ins by name:
    'method',
    'route',
    'url',
    'ip',
    'user_agent',
    'query',
    // 'body',   // off by default — enable to log request input

    // Add your own, resolved from anywhere (or override a built-in by name):
    'tenant_id'  => fn ($request) => $request->header('X-Tenant') ?? optional(tenant())->id,
    'user_agent' => fn ($request) => substr((string) $request->userAgent(), 0, 200),

    // Header-sourced values go under the `headers` group:
    'headers' => [
        'request_id'     => 'X-Request-Id',
        'correlation_id' => 'X-Correlation-Id',
    ],
],
```

Resolved values land in `__request` (header values under `__request.headers`); a
null/empty result is dropped, so a request without a given header — or an empty
query string — simply omits that property.

##### Redacting secrets

Logged `query` and `body` input is filtered through the `redact` list before
storage, so credentials never reach the log. Matching is case-insensitive and
recurses into nested arrays; a matched value becomes `"[REDACTED]"`. The defaults
cover common credential fields (`password`, `token`, `secret`, …) — edit the list
to fit your app:

```php
'redact' => [
    'password',
    'password_confirmation',
    'token',
    'secret',
    // …
],
```

To set a value manually (overriding the configured source), pass a field of the
same name in the metadata bag — the explicit value always wins and is stored only
under `__request`:

```php
audit_log('order.placed', $order, AuditMetadata::make([
    'request_id' => $myTraceId,
]));
```

---

### Adding structured metadata

Use `AuditMetadata` to attach typed field-level detail to a log entry.

**Arbitrary fields:**

```php
use Mxnwire\AuditLog\Audit\Metadata\AuditMetadata;

audit_log(
    'user.login',
    subject: $user,
    metadata: AuditMetadata::make(['via' => 'password'])
);
```

**Before/after diff for a specific field:**

```php
use Mxnwire\AuditLog\Audit\Metadata\AuditMetadata;
use Mxnwire\AuditLog\Audit\Metadata\Change;

audit_log(
    'subscription.updated',
    subject: $subscription,
    metadata: AuditMetadata::make([
        'level' => Change::make($old->level, $new->level),
    ])
);
```

Stored in `properties` as:

```json
{ "level": { "old": 2, "new": 5 } }
```

**Auto-diff two arrays (keeps only changed keys):**

```php
$metadata = AuditMetadata::diff(
    $record->getOriginal(),  // before
    $record->getAttributes() // after
);

audit_log('post.updated', subject: $record, metadata: $metadata);
```

You can restrict which keys are diffed with the optional third argument:

```php
AuditMetadata::diff($before, $after, keys: ['title', 'status', 'published_at']);
```

**Fluent chaining:**

```php
AuditMetadata::diff($before, $after)
    ->with('trigger', 'bulk-import')
    ->with('row_count', 500);
```

---

### Viewing logs

The package registers two routes automatically:

| Route | Named route | Description |
|---|---|---|
| `GET /mxn/audit-logs` | `audit-log.index` | Viewer page |
| `GET /mxn/audit-logs/data` | `audit-log.data` | JSON data endpoint for the table |

Both routes are protected by `auth` plus a gate callback. The `gate` config is a closure receiving the authenticated user and returning a boolean; it allows only users whose `role` is `admin` by default. Override it in the published config — e.g. `fn ($user) => $user?->can('AUDIT_LOGS_ALL') ?? false`.

---

## Customisation

### Custom viewer layout

To embed the viewer inside your own app shell, set `layout` in the config to any Blade layout that yields `content` and `script`:

```php
'layout' => 'layouts.app',
```

Your layout must contain:

```blade
@yield('content')
@yield('script')
```

### Custom route prefix

```php
'route_prefix' => 'admin/audit-logs',
```

The named routes (`audit-log.index`, `audit-log.data`) follow the prefix automatically.

### Custom role resolver

If your app does not use a `role` or `urole` attribute directly on the User model:

```php
'role_resolver' => fn ($user) => $user->roles->first()?->name,
```

### Custom audit type registry

The viewer's filter dropdowns (log names, events, subject types) come from the class named in the `registry` config key. By default this is the DB-backed `AuditTypeRegistry`, which `SELECT DISTINCT`s the live `activity_log` table at runtime — zero setup, but it only ever surfaces values that have already been logged.

To serve the options from a fixed vocabulary instead, extend `AbstractAuditTypeRegistry`, declare your dotted `resource.verb` actions as constants, and map each resource to its subject model:

```php
use Mxnwire\AuditLog\AbstractAuditTypeRegistry;

class AuditType extends AbstractAuditTypeRegistry
{
    const RANK_UPDATED      = 'rank.updated';
    const BROADSHEET_VIEWED = 'broadsheet.viewed';

    protected const SUBJECT_MODELS = [
        'rank' => \App\Models\Rank::class,
    ];
}
```

Then point the config at it:

```php
'registry' => \App\Audit\AuditType::class,
```

The option lists are derived from those constants via reflection, so new `RESOURCE_VERB` constants enrol automatically.

For full control you can implement `AuditTypeRegistryContract` directly instead of extending the abstract class — it requires `logNames(): array`, `events(): array`, and `subjectTypes(): array`. The provider binds the contract with `bindIf`, so an explicit container binding in a service provider still takes precedence over the config value:

```php
use Mxnwire\AuditLog\Contracts\AuditTypeRegistryContract;

$this->app->bind(AuditTypeRegistryContract::class, MyCustomRegistry::class);
```

---

## Development

### Running the tests

The test suite uses [Orchestra Testbench](https://github.com/orchestral/testbench) and an in-memory SQLite database — no external database or running Laravel app is required.

**1. Install dev dependencies (first time only):**

```bash
composer install
```

**2. Run the full suite:**

```bash
composer test
```

Or call PHPUnit directly:

```bash
./vendor/bin/phpunit
```

**3. Run a single suite:**

```bash
./vendor/bin/phpunit --testsuite Unit
./vendor/bin/phpunit --testsuite Feature
```

**4. Run a single test file:**

```bash
./vendor/bin/phpunit tests/Unit/AuditMetadataTest.php
./vendor/bin/phpunit tests/Unit/ChangeTest.php
./vendor/bin/phpunit tests/Feature/AuditLogServiceTest.php
```

**5. Run a single test by name:**

```bash
./vendor/bin/phpunit --filter "test_name_here"
```

### Test structure

| Path | What it covers |
|---|---|
| `tests/Unit/AuditMetadataTest.php` | `AuditMetadata` value object — make, diff, with |
| `tests/Unit/ChangeTest.php` | `Change` value object — serialisation |
| `tests/Feature/AuditLogServiceTest.php` | `AuditLogService` end-to-end against a real SQLite DB |
| `tests/TestCase.php` | Base case — boots Testbench, runs spatie migrations in-memory |

---

## License

MIT
