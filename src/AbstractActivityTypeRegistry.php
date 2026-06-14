<?php

namespace Mxnwire\AuditLog;

use Mxnwire\AuditLog\Contracts\ActivityTypeRegistryContract;

/**
 * Vocabulary-backed activity-type registry.
 *
 * Extend this in the host app, declare your dotted `resource.verb` action
 * constants (e.g. const RANK_UPDATED = 'rank.updated') and override
 * {@see self::SUBJECT_MODELS}; the filter option lists are then derived from
 * that vocabulary via reflection instead of `SELECT DISTINCT`-ing the
 * (potentially huge) activity_log table.
 *
 * Contrast with {@see ActivityTypeRegistry}, the default DB-backed registry the
 * service provider binds when the host app does not supply its own.
 *
 * Bind your subclass to the contract in a service provider:
 *   $this->app->bind(ActivityTypeRegistryContract::class, App\Activity\ActivityType::class);
 */
abstract class AbstractActivityTypeRegistry implements ActivityTypeRegistryContract
{
    /**
     * Subject model FQCN each resource (`log_name`) is logged against. Override
     * in the subclass. Keyed by resource so it stays one entry per model, not
     * one per verb; resources whose actions are subject-less are simply absent.
     *
     * @var array<string, class-string>
     */
    protected const SUBJECT_MODELS = [];

    /**
     * Every registered dotted action key. Read off the subclass constants via
     * reflection so new `RESOURCE_VERB` constants enrol automatically; the
     * `resource.verb` shape filters out non-action constants like the map above.
     *
     * @return string[]
     */
    public static function all(): array
    {
        $constants = (new \ReflectionClass(static::class))->getConstants();

        return array_values(array_filter($constants, function ($value) {
            return is_string($value) && strpos($value, '.') !== false;
        }));
    }

    /**
     * Distinct `log_name` values (the resource before the dot), sorted.
     *
     * @return string[]
     */
    public function logNames(): array
    {
        return self::segment(0);
    }

    /**
     * Distinct `event` values (the verb after the dot), sorted.
     *
     * @return string[]
     */
    public function events(): array
    {
        return self::segment(1);
    }

    /**
     * Distinct subject model FQCNs that actions are recorded against, sorted.
     *
     * @return string[]
     */
    public function subjectTypes(): array
    {
        $types = array_values(array_unique(static::SUBJECT_MODELS));
        sort($types);

        return $types;
    }

    /**
     * Distinct values of one `resource.verb` segment across all actions, sorted.
     *
     * @return string[]
     */
    private static function segment(int $index): array
    {
        $values = [];
        foreach (static::all() as $action) {
            $parts = explode('.', $action, 2);
            if (isset($parts[$index]) && $parts[$index] !== '') {
                $values[$parts[$index]] = true;
            }
        }

        $values = array_keys($values);
        sort($values);

        return $values;
    }
}
