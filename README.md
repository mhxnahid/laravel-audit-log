# mxnwire/laravel-audit-log

Activity log viewer and logger layered on top of [spatie/laravel-activitylog](https://github.com/spatie/laravel-activitylog).

Spatie owns the storage (`activity_log` table, polymorphic causer/subject, and the `activitylog:clean` prune command). This package adds:

- A `activity_log()` helper and `ActivityLogService` that freeze actor identity and request context at write time.
- A built-in viewer UI (filterable table, no JS build step required) protected by a permission gate.
- `ActivityMetadata` / `Change` value objects for structured before/after diffs in the `properties` column.

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

    // The ability string checked by the route middleware `can:X`.
    // Must be a permission defined in your host app (e.g. via spatie/laravel-permission).
    // Can also be set via the AUDIT_LOG_GATE environment variable.
    'gate' => env('AUDIT_LOG_GATE', 'ACTIVITY_LOGS_ALL'),

    // A callable that resolves the actor's role label from a User model instance.
    // null = falls back to $user->role ?? $user->urole ?? null.
    'role_resolver' => null,

    // URL prefix for the viewer routes.
    // Changing this also renames the named routes `audit-log.index` and `audit-log.data`.
    'route_prefix' => 'mxn/audit-logs',

    // Eloquent model used to populate the "User" filter dropdown.
    // The model must have `id` and `name` columns.
    // Set to null to hide the user filter entirely.
    'user_model' => 'App\\Models\\User',

    // Blade layout the viewer page extends.
    // Override to wrap the viewer inside your own app shell.
    // The layout must @yield('content') and @yield('script').
    'layout' => 'audit-log::layouts.app',

];
```

### Environment variable

| Variable | Default | Description |
|---|---|---|
| `AUDIT_LOG_GATE` | `ACTIVITY_LOGS_ALL` | Permission gate for the viewer routes |

---

## Usage

### Logging an activity

Use the global helper (available automatically — no import needed):

```php
activity_log('user.login');
```

Or inject the service directly:

```php
use Mxnwire\AuditLog\Services\ActivityLogService;

class AuthController extends Controller
{
    public function __construct(private ActivityLogService $auditLog) {}

    public function login(Request $request)
    {
        // ... authenticate ...
        $this->auditLog->log('user.login');
    }
}
```

#### Signature

```php
activity_log(
    string $action,                      // dotted verb, e.g. 'broadsheet.viewed'
    ?Model $subject = null,              // the record acted on
    ?ActivityMetadata $metadata = null,  // structured diff / extra fields
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

The `__actor` and `__request` context is always merged into spatie's `properties` JSON column automatically:

```json
{
  "__actor":   { "name": "Jane Smith", "role": "admin" },
  "__request": { "method": "POST", "route": "posts.store", "url": "...", "ip": "...", "user_agent": "..." }
}
```

---

### Adding structured metadata

Use `ActivityMetadata` to attach typed field-level detail to a log entry.

**Arbitrary fields:**

```php
use Mxnwire\AuditLog\Activity\Metadata\ActivityMetadata;

activity_log(
    'user.login',
    subject: $user,
    metadata: ActivityMetadata::make(['via' => 'password'])
);
```

**Before/after diff for a specific field:**

```php
use Mxnwire\AuditLog\Activity\Metadata\ActivityMetadata;
use Mxnwire\AuditLog\Activity\Metadata\Change;

activity_log(
    'subscription.updated',
    subject: $subscription,
    metadata: ActivityMetadata::make([
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
$metadata = ActivityMetadata::diff(
    $record->getOriginal(),  // before
    $record->getAttributes() // after
);

activity_log('post.updated', subject: $record, metadata: $metadata);
```

You can restrict which keys are diffed with the optional third argument:

```php
ActivityMetadata::diff($before, $after, keys: ['title', 'status', 'published_at']);
```

**Fluent chaining:**

```php
ActivityMetadata::diff($before, $after)
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

Both routes are protected by `auth` and `can:{gate}` middleware. The gate defaults to `ACTIVITY_LOGS_ALL`; change it via `AUDIT_LOG_GATE` in your `.env` or in the published config.

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

### Custom activity type registry

The package resolves filter options (log names, events, subject types) from the `activity_log` table at runtime via `ActivityTypeRegistry`. To provide a static list instead — or to customise the queries — bind your own implementation in a service provider:

```php
use Mxnwire\AuditLog\Contracts\ActivityTypeRegistryContract;

$this->app->bind(ActivityTypeRegistryContract::class, MyCustomRegistry::class);
```

Your class must implement `logNames(): array`, `events(): array`, and `subjectTypes(): array`.

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
./vendor/bin/phpunit tests/Unit/ActivityMetadataTest.php
./vendor/bin/phpunit tests/Unit/ChangeTest.php
./vendor/bin/phpunit tests/Feature/ActivityLogServiceTest.php
```

**5. Run a single test by name:**

```bash
./vendor/bin/phpunit --filter "test_name_here"
```

### Test structure

| Path | What it covers |
|---|---|
| `tests/Unit/ActivityMetadataTest.php` | `ActivityMetadata` value object — make, diff, with |
| `tests/Unit/ChangeTest.php` | `Change` value object — serialisation |
| `tests/Feature/ActivityLogServiceTest.php` | `ActivityLogService` end-to-end against a real SQLite DB |
| `tests/TestCase.php` | Base case — boots Testbench, runs spatie migrations in-memory |

---

## License

MIT
