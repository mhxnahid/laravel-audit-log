<?php

namespace Mxnwire\AuditLog\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Mxnwire\AuditLog\Activity\Metadata\ActivityMetadata;
use Mxnwire\AuditLog\ActivityTypeRegistry;
use Mxnwire\AuditLog\Services\ActivityLogService;
use Mxnwire\AuditLog\Tests\TestCase;
use Spatie\Activitylog\Models\Activity;

class ActivityLogServiceTest extends TestCase
{
    use RefreshDatabase;
    private ActivityLogService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ActivityLogService();
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
        $metadata = ActivityMetadata::make(['via' => 'oauth', 'provider' => 'google']);

        $this->service->log('user.login', null, $metadata);

        $props = Activity::first()->properties->toArray();
        $this->assertSame('oauth', $props['via']);
        $this->assertSame('google', $props['provider']);
    }

    public function test_metadata_change_expands_to_old_new_in_properties(): void
    {
        $metadata = ActivityMetadata::diff(
            ['status' => 'pending'],
            ['status' => 'approved']
        );

        $this->service->log('order.updated', null, $metadata);

        $props = Activity::first()->properties->toArray();
        $this->assertSame(['old' => 'pending', 'new' => 'approved'], $props['status']);
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

    public function test_activity_type_registry_returns_distinct_log_names(): void
    {
        $this->service->log('orders.placed');
        $this->service->log('users.created');
        $this->service->log('orders.cancelled');

        $names = (new ActivityTypeRegistry())->logNames();

        $this->assertSame(['orders', 'users'], $names);
    }

    public function test_activity_type_registry_returns_distinct_events(): void
    {
        $this->service->log('orders.placed');
        $this->service->log('orders.cancelled');
        $this->service->log('orders.placed');

        $events = (new ActivityTypeRegistry())->events();

        $this->assertSame(['cancelled', 'placed'], $events);
    }
}
