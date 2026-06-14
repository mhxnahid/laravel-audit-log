<?php

namespace Mxnwire\AuditLog\Tests\Unit;

use Mxnwire\AuditLog\AbstractActivityTypeRegistry;
use PHPUnit\Framework\TestCase;

class AbstractActivityTypeRegistryTest extends TestCase
{
    public function test_all_returns_only_dotted_action_constants(): void
    {
        $this->assertSame(
            ['rank.created', 'rank.updated', 'user.created'],
            FakeActivityTypeRegistry::all()
        );
    }

    public function test_log_names_are_distinct_resources_sorted(): void
    {
        $registry = new FakeActivityTypeRegistry();

        $this->assertSame(['rank', 'user'], $registry->logNames());
    }

    public function test_events_are_distinct_verbs_sorted(): void
    {
        $registry = new FakeActivityTypeRegistry();

        $this->assertSame(['created', 'updated'], $registry->events());
    }

    public function test_subject_types_are_distinct_models_sorted(): void
    {
        $registry = new FakeActivityTypeRegistry();

        $this->assertSame(
            ['App\\Models\\Rank', 'App\\Models\\User'],
            $registry->subjectTypes()
        );
    }
}

class FakeActivityTypeRegistry extends AbstractActivityTypeRegistry
{
    const RANK_CREATED = 'rank.created';
    const RANK_UPDATED = 'rank.updated';
    const USER_CREATED = 'user.created';

    // Non-action constants must be ignored by all().
    const SOMETHING_ELSE = 'no-dot-here';

    protected const SUBJECT_MODELS = [
        'rank' => 'App\\Models\\Rank',
        'user' => 'App\\Models\\User',
    ];
}
