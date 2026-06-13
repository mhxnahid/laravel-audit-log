<?php

namespace Mxnwire\AuditLog\Activity\Metadata;

/**
 * A single field's before/after pair.
 *
 * Serialized by {@see ActivityMetadata::toProperties()} into self-documenting JSON:
 *
 *     { "old": 2, "new": 5 }
 */
final class Change
{
    /** @var mixed */
    private $old;

    /** @var mixed */
    private $new;

    /**
     * @param mixed $old
     * @param mixed $new
     */
    public function __construct($old, $new)
    {
        $this->old = $old;
        $this->new = $new;
    }

    /**
     * @param mixed $old
     * @param mixed $new
     */
    public static function make($old, $new): self
    {
        return new self($old, $new);
    }

    /** @return mixed */
    public function old()
    {
        return $this->old;
    }

    /** @return mixed */
    public function new()
    {
        return $this->new;
    }

    /** @return array{old: mixed, new: mixed} */
    public function toArray(): array
    {
        return ['old' => $this->old, 'new' => $this->new];
    }
}
