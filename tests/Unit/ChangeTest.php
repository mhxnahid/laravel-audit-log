<?php

namespace Mxnwire\AuditLog\Tests\Unit;

use Mxnwire\AuditLog\Audit\Metadata\Change;
use PHPUnit\Framework\TestCase;

class ChangeTest extends TestCase
{
    public function test_make_stores_old_and_new(): void
    {
        $change = Change::make('before', 'after');

        $this->assertSame('before', $change->old());
        $this->assertSame('after', $change->new());
    }

    public function test_to_array_returns_old_new_keys(): void
    {
        $change = Change::make(1, 2);

        $this->assertSame(['old' => 1, 'new' => 2], $change->toArray());
    }

    public function test_accepts_null_values(): void
    {
        $change = Change::make(null, 'something');

        $this->assertNull($change->old());
        $this->assertSame('something', $change->new());
    }

    public function test_accepts_array_values(): void
    {
        $old = ['a', 'b'];
        $new = ['a', 'b', 'c'];

        $change = Change::make($old, $new);

        $this->assertSame($old, $change->old());
        $this->assertSame($new, $change->new());
        $this->assertSame(['old' => $old, 'new' => $new], $change->toArray());
    }
}
