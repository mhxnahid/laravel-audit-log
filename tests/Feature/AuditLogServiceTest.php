<?php

namespace Mxnwire\AuditLog\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Mxnwire\AuditLog\Audit\Metadata\AuditMetadata;
use Mxnwire\AuditLog\AuditTypeRegistry;
use Mxnwire\AuditLog\Services\AuditLogService;
use Mxnwire\AuditLog\Tests\TestCase;
use Spatie\Activitylog\Models\Activity;

class AuditLogServiceTest extends TestCase
{
    use RefreshDatabase;
    private AuditLogService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AuditLogService();
    }

    /** Bind a request instance so the service's request() helper resolves it. */
    private function bindRequest(Request $request): void
    {
        $this->app->instance('request', $request);
    }

    public function test_dotted_action_sets_log_name_and_event(): void
    {
        $this->service->log('user.login');

        $activity = Activity::first();
        $this->assertSame('user', $activity->log_name);
        $this->assertSame('login', $activity->event);
    }

    public function test_single_word_action_sets_log_name_with_null_event(): void
    {
        $this->service->log('authentication');

        $activity = Activity::first();
        $this->assertSame('authentication', $activity->log_name);
        $this->assertNull($activity->event);
    }

    public function test_uses_action_as_description_when_none_given(): void
    {
        $this->service->log('order.placed');

        $this->assertSame('order.placed', Activity::first()->description);
    }

    public function test_uses_explicit_description(): void
    {
        $this->service->log('order.placed', null, null, 'Order #42 placed');

        $this->assertSame('Order #42 placed', Activity::first()->description);
    }

    public function test_properties_include_actor_and_request_context(): void
    {
        $this->service->log('user.login');

        $props = Activity::first()->properties->toArray();
        $this->assertArrayHasKey('__actor', $props);
        $this->assertArrayHasKey('__request', $props);
        $this->assertArrayHasKey('name', $props['__actor']);
        $this->assertArrayHasKey('role', $props['__actor']);
    }

    public function test_metadata_fields_are_merged_into_properties(): void
    {
        $metadata = AuditMetadata::make(['via' => 'oauth', 'provider' => 'google']);

        $this->service->log('user.login', null, $metadata);

        $props = Activity::first()->properties->toArray();
        $this->assertSame('oauth', $props['via']);
        $this->assertSame('google', $props['provider']);
    }

    public function test_metadata_change_expands_to_old_new_in_properties(): void
    {
        $metadata = AuditMetadata::diff(
            ['status' => 'pending'],
            ['status' => 'approved']
        );

        $this->service->log('order.updated', null, $metadata);

        $props = Activity::first()->properties->toArray();
        $this->assertSame(['old' => 'pending', 'new' => 'approved'], $props['status']);
    }

    public function test_request_and_correlation_ids_are_read_from_headers(): void
    {
        config(['audit-log.request_context' => ['headers' => [
            'request_id'     => 'X-Request-Id',
            'correlation_id' => 'X-Correlation-Id',
        ]]]);
        request()->headers->set('X-Request-Id', 'req-123');
        request()->headers->set('X-Correlation-Id', 'corr-456');

        $this->service->log('user.login');

        $request = Activity::first()->properties->toArray()['__request'];
        $this->assertSame('req-123', $request['headers']['request_id']);
        $this->assertSame('corr-456', $request['headers']['correlation_id']);
    }

    public function test_metadata_overrides_header_request_id(): void
    {
        config(['audit-log.request_context' => ['headers' => [
            'request_id' => 'X-Request-Id',
        ]]]);
        request()->headers->set('X-Request-Id', 'from-header');

        $metadata = AuditMetadata::make(['request_id' => 'manual-override']);

        $this->service->log('user.login', null, $metadata);

        $props   = Activity::first()->properties->toArray();
        $request = $props['__request'];

        $this->assertSame('manual-override', $request['headers']['request_id']);
        // The override key lives only under __request, not at the top level.
        $this->assertArrayNotHasKey('request_id', $props);
    }

    public function test_header_can_be_dropped_with_false(): void
    {
        config(['audit-log.request_context' => ['headers' => [
            'request_id'     => 'X-Request-Id',
            'correlation_id' => false,
        ]]]);
        request()->headers->set('X-Request-Id', 'req-123');
        request()->headers->set('X-Correlation-Id', 'corr-456');

        $this->service->log('user.login');

        $request = Activity::first()->properties->toArray()['__request'];
        $this->assertArrayNotHasKey('correlation_id', $request['headers']);
        // Sibling header is untouched.
        $this->assertSame('req-123', $request['headers']['request_id']);
    }

    public function test_config_is_the_source_of_truth(): void
    {
        // Only trace_id is configured — the shipped defaults are replaced wholesale.
        config(['audit-log.request_context' => ['headers' => ['trace_id' => 'X-Trace-Id']]]);
        request()->headers->set('X-Trace-Id', 'trace-789');
        request()->headers->set('X-Request-Id', 'req-123');

        $this->service->log('user.login');

        $headers = Activity::first()->properties->toArray()['__request']['headers'];
        $this->assertSame('trace-789', $headers['trace_id']);
        $this->assertArrayNotHasKey('request_id', $headers);
    }

    public function test_config_overrides_header_name(): void
    {
        config(['audit-log.request_context' => ['headers' => ['request_id' => 'X-Trace-Id']]]);
        request()->headers->set('X-Trace-Id', 'trace-789');

        $this->service->log('user.login');

        $request = Activity::first()->properties->toArray()['__request'];
        $this->assertSame('trace-789', $request['headers']['request_id']);
    }

    public function test_shipped_config_provides_defaults(): void
    {
        // No override — the package's shipped request_context applies, resolving
        // each listed built-in name via its packaged resolver.
        $this->service->log('user.login');

        $request = Activity::first()->properties->toArray()['__request'];
        // 'route' is omitted here because the test request has no named route.
        foreach (['method', 'url', 'ip', 'user_agent'] as $key) {
            $this->assertArrayHasKey($key, $request);
        }
    }

    public function test_bare_key_resolves_via_builtin(): void
    {
        config(['audit-log.request_context' => ['method']]);

        $this->service->log('user.login');

        $request = Activity::first()->properties->toArray()['__request'];
        $this->assertSame('GET', $request['method']);
    }

    public function test_unknown_bare_key_is_ignored(): void
    {
        config(['audit-log.request_context' => ['method', 'not_a_builtin']]);

        $this->service->log('user.login');

        $request = Activity::first()->properties->toArray()['__request'];
        $this->assertArrayHasKey('method', $request);
        $this->assertArrayNotHasKey('not_a_builtin', $request);
    }

    public function test_each_builtin_resolves_its_value(): void
    {
        config(['audit-log.request_context' => ['method', 'route', 'url', 'ip', 'user_agent']]);

        $route = (new \Illuminate\Routing\Route(['GET'], '/orders', []))->name('orders.index');
        request()->setRouteResolver(fn () => $route);
        request()->headers->set('User-Agent', 'TestAgent/1.0');

        $this->service->log('user.login');

        $request = Activity::first()->properties->toArray()['__request'];
        $this->assertSame(request()->method(), $request['method']);
        $this->assertSame('orders.index', $request['route']);
        $this->assertSame(request()->fullUrl(), $request['url']);
        $this->assertSame(request()->ip(), $request['ip']);
        $this->assertSame('TestAgent/1.0', $request['user_agent']);
    }

    public function test_omitted_builtins_are_not_recorded(): void
    {
        // Only 'method' is listed — the other built-ins are left out entirely.
        config(['audit-log.request_context' => ['method']]);

        $this->service->log('user.login');

        $request = Activity::first()->properties->toArray()['__request'];
        $this->assertArrayHasKey('method', $request);
        foreach (['route', 'url', 'ip', 'user_agent'] as $key) {
            $this->assertArrayNotHasKey($key, $request);
        }
    }

    public function test_builtin_can_be_overridden_with_resolver(): void
    {
        // A keyed resolver for a built-in name replaces the packaged resolver.
        config(['audit-log.request_context' => [
            'user_agent' => fn ($request) => 'OVERRIDDEN',
        ]]);
        request()->headers->set('User-Agent', 'Real/9.9');

        $this->service->log('user.login');

        $request = Activity::first()->properties->toArray()['__request'];
        $this->assertSame('OVERRIDDEN', $request['user_agent']);
    }

    public function test_callable_source_resolves_property(): void
    {
        config(['audit-log.request_context' => [
            'tenant_id' => fn ($request) => 'tenant-' . $request->header('X-Tenant'),
        ]]);
        request()->headers->set('X-Tenant', '7');

        $this->service->log('user.login');

        $request = Activity::first()->properties->toArray()['__request'];
        $this->assertSame('tenant-7', $request['tenant_id']);
    }

    public function test_metadata_overrides_callable_source(): void
    {
        config(['audit-log.request_context' => [
            'tenant_id' => fn ($request) => 'from-resolver',
        ]]);

        $metadata = AuditMetadata::make(['tenant_id' => 'manual']);

        $this->service->log('user.login', null, $metadata);

        $props = Activity::first()->properties->toArray();
        $this->assertSame('manual', $props['__request']['tenant_id']);
        $this->assertArrayNotHasKey('tenant_id', $props);
    }

    public function test_id_is_absent_when_no_header_present(): void
    {
        $this->service->log('user.login');

        $request = Activity::first()->properties->toArray()['__request'];
        $this->assertArrayNotHasKey('request_id', $request);
        $this->assertArrayNotHasKey('correlation_id', $request);
    }

    public function test_query_params_are_logged_by_default(): void
    {
        // Shipped config enables 'query'; no override here.
        $this->bindRequest(Request::create('/orders', 'GET', ['status' => 'open', 'page' => '2']));

        $this->service->log('order.viewed');

        $request = Activity::first()->properties->toArray()['__request'];
        $this->assertSame(['status' => 'open', 'page' => '2'], $request['query']);
    }

    public function test_query_redacts_sensitive_keys_by_default(): void
    {
        $this->bindRequest(Request::create('/orders', 'GET', ['q' => 'shoes', 'token' => 'abc123']));

        $this->service->log('order.viewed');

        $query = Activity::first()->properties->toArray()['__request']['query'];
        $this->assertSame('shoes', $query['q']);
        $this->assertSame('[REDACTED]', $query['token']);
    }

    public function test_empty_query_is_omitted(): void
    {
        $this->bindRequest(Request::create('/orders', 'GET'));

        $this->service->log('order.viewed');

        $request = Activity::first()->properties->toArray()['__request'];
        $this->assertArrayNotHasKey('query', $request);
    }

    public function test_body_is_not_logged_by_default(): void
    {
        $this->bindRequest(Request::create('/orders', 'POST', ['name' => 'Jane']));

        $this->service->log('order.placed');

        $request = Activity::first()->properties->toArray()['__request'];
        $this->assertArrayNotHasKey('body', $request);
    }

    public function test_body_is_logged_when_enabled(): void
    {
        config(['audit-log.request_context' => ['body']]);
        $this->bindRequest(Request::create('/orders', 'POST', ['name' => 'Jane', 'qty' => '3']));

        $this->service->log('order.placed');

        $request = Activity::first()->properties->toArray()['__request'];
        $this->assertSame(['name' => 'Jane', 'qty' => '3'], $request['body']);
    }

    public function test_body_redacts_sensitive_keys_by_default(): void
    {
        config(['audit-log.request_context' => ['body']]);
        $this->bindRequest(Request::create('/register', 'POST', [
            'email'    => 'jane@example.com',
            'password' => 'hunter2',
        ]));

        $this->service->log('user.registered');

        $body = Activity::first()->properties->toArray()['__request']['body'];
        $this->assertSame('jane@example.com', $body['email']);
        $this->assertSame('[REDACTED]', $body['password']);
    }

    public function test_body_redaction_recurses_into_nested_arrays(): void
    {
        config(['audit-log.request_context' => ['body']]);
        $this->bindRequest(Request::create('/users', 'POST', [
            'user' => ['name' => 'Jane', 'password' => 'hunter2'],
        ]));

        $this->service->log('user.created');

        $body = Activity::first()->properties->toArray()['__request']['body'];
        $this->assertSame('Jane', $body['user']['name']);
        $this->assertSame('[REDACTED]', $body['user']['password']);
    }

    public function test_json_body_is_logged_and_redacted(): void
    {
        config(['audit-log.request_context' => ['body']]);
        $this->bindRequest(Request::create(
            '/orders',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['sku' => 'A1', 'secret' => 'shh'])
        ));

        $this->service->log('order.placed');

        $body = Activity::first()->properties->toArray()['__request']['body'];
        $this->assertSame('A1', $body['sku']);
        $this->assertSame('[REDACTED]', $body['secret']);
    }

    public function test_redact_config_replaces_defaults(): void
    {
        config([
            'audit-log.request_context' => ['body'],
            'audit-log.redact'          => ['ssn'],
        ]);
        $this->bindRequest(Request::create('/applications', 'POST', [
            'password' => 'hunter2',
            'ssn'      => '123-45-6789',
        ]));

        $this->service->log('application.submitted');

        $body = Activity::first()->properties->toArray()['__request']['body'];
        // 'password' is no longer in the list, so it passes through untouched.
        $this->assertSame('hunter2', $body['password']);
        $this->assertSame('[REDACTED]', $body['ssn']);
    }

    public function test_log_does_not_throw_on_failure(): void
    {
        // Override the role_resolver with one that throws to simulate an unexpected error.
        config(['audit-log.role_resolver' => function () {
            throw new \RuntimeException('resolver exploded');
        }]);

        // Should not propagate — log() is best-effort.
        $this->service->log('user.login');

        $this->assertTrue(true);
    }

    public function test_audit_type_registry_returns_distinct_log_names(): void
    {
        $this->service->log('orders.placed');
        $this->service->log('users.created');
        $this->service->log('orders.cancelled');

        $names = (new AuditTypeRegistry())->logNames();

        $this->assertSame(['orders', 'users'], $names);
    }

    public function test_audit_type_registry_returns_distinct_events(): void
    {
        $this->service->log('orders.placed');
        $this->service->log('orders.cancelled');
        $this->service->log('orders.placed');

        $events = (new AuditTypeRegistry())->events();

        $this->assertSame(['cancelled', 'placed'], $events);
    }
}
