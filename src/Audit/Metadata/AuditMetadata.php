<?php

namespace Mxnwire\AuditLog\Audit\Metadata;

/**
 * Typed metadata bag for an audit-log entry.
 *
 *     AuditMetadata::make(['level' => Change::make($old, $new)]);
 *     AuditMetadata::make(['via' => 'password']);
 *     AuditMetadata::diff($before, $after);
 *
 * {@see Change} values expand to `{old, new}` JSON; null fields are dropped.
 */
final class AuditMetadata
{
    /** @var array<string, mixed> */
    private $fields;

    /** @param array<string, mixed> $fields */
    public function __construct(array $fields = [])
    {
        $this->fields = $fields;
    }

    /** @param array<string, mixed> $fields */
    public static function make(array $fields = []): self
    {
        return new self($fields);
    }

    /**
     * Build a metadata bag of {@see Change}s by comparing before/after arrays,
     * keeping only the keys whose value actually changed.
     *
     * @param array<string, mixed>    $before
     * @param array<string, mixed>    $after
     * @param array<int, string>|null $keys
     */
    public static function diff(array $before, array $after, ?array $keys = null): self
    {
        $keys   = $keys ?? array_keys($before);
        $fields = [];

        foreach ($keys as $key) {
            $old = array_key_exists($key, $before) ? $before[$key] : null;
            $new = array_key_exists($key, $after)  ? $after[$key]  : null;

            if ($old !== $new) {
                $fields[$key] = Change::make($old, $new);
            }
        }

        return new self($fields);
    }

    /** Add or override a single field, fluently. */
    public function with(string $key, $value): self
    {
        $this->fields[$key] = $value;

        return $this;
    }

    /**
     * Flat array ready to merge into spatie's `properties` JSON column.
     * {@see Change} values become `{old, new}`; nulls are dropped.
     *
     * @return array<string, mixed>
     */
    public function toProperties(): array
    {
        $out = [];

        foreach ($this->fields as $key => $value) {
            if ($value === null) {
                continue;
            }

            $out[$key] = $value instanceof Change ? $value->toArray() : $value;
        }

        return $out;
    }
}
