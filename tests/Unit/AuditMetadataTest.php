<?php

namespace Mxnwire\AuditLog\Tests\Unit;

use Mxnwire\AuditLog\Audit\Metadata\AuditMetadata;
use Mxnwire\AuditLog\Audit\Metadata\Change;
use PHPUnit\Framework\TestCase;

class AuditMetadataTest extends TestCase
{
    public function test_make_returns_instance_with_scalar_fields(): void
    {
        $meta = AuditMetadata::make(['via' => 'password', 'count' => 3]);

        $this->assertSame(['via' => 'password', 'count' => 3], $meta->toProperties());
    }

    public function test_to_properties_expands_change_to_old_new_array(): void
    {
        $meta = AuditMetadata::make(['level' => Change::make(1, 5)]);

        $this->assertSame(['level' => ['old' => 1, 'new' => 5]], $meta->toProperties());
    }

    public function test_to_properties_drops_null_fields(): void
    {
        $meta = AuditMetadata::make(['keep' => 'yes', 'drop' => null]);

        $this->assertSame(['keep' => 'yes'], $meta->toProperties());
    }

    public function test_with_adds_field_fluently(): void
    {
        $meta = AuditMetadata::make()->with('source', 'api');

        $this->assertSame(['source' => 'api'], $meta->toProperties());
    }

    public function test_with_overrides_existing_field(): void
    {
        $meta = AuditMetadata::make(['mode' => 'old'])->with('mode', 'new');

        $this->assertSame(['mode' => 'new'], $meta->toProperties());
    }

    public function test_diff_creates_changes_only_for_differing_keys(): void
    {
        $before = ['name' => 'Alice', 'role' => 'admin', 'email' => 'a@b.com'];
        $after  = ['name' => 'Alice', 'role' => 'editor', 'email' => 'a@b.com'];

        $props = AuditMetadata::diff($before, $after)->toProperties();

        $this->assertArrayHasKey('role', $props);
        $this->assertSame(['old' => 'admin', 'new' => 'editor'], $props['role']);
        $this->assertArrayNotHasKey('name', $props);
        $this->assertArrayNotHasKey('email', $props);
    }

    public function test_diff_with_explicit_keys_limits_comparison(): void
    {
        $before = ['name' => 'Alice', 'role' => 'admin', 'score' => 10];
        $after  = ['name' => 'Bob',   'role' => 'editor', 'score' => 20];

        $props = AuditMetadata::diff($before, $after, keys: ['role'])->toProperties();

        $this->assertArrayHasKey('role', $props);
        $this->assertArrayNotHasKey('name', $props);
        $this->assertArrayNotHasKey('score', $props);
    }

    public function test_diff_treats_missing_key_as_null(): void
    {
        $before = ['status' => 'active'];
        $after  = [];

        $props = AuditMetadata::diff($before, $after)->toProperties();

        $this->assertSame(['old' => 'active', 'new' => null], $props['status']);
    }
}
