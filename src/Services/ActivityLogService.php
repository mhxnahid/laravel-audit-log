<?php

namespace Mxnwire\AuditLog\Services;

use Mxnwire\AuditLog\Activity\Metadata\ActivityMetadata;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;

/**
 * Activity logging layered on top of spatie/laravel-activitylog.
 *
 * Spatie owns the storage (the `activity_log` table, polymorphic causer/subject
 * resolution, and the `activitylog:clean` prune command). This service adds:
 *
 *   1. Identity snapshot — actor name + role frozen at action time.
 *   2. Request context — method, route, url, ip, user agent.
 *   3. Best-effort, non-blocking writes — a failed insert is reported and
 *      swallowed; it never surfaces as a user-facing error.
 *
 * The dotted action maps onto spatie's two indexable columns:
 *     'broadsheet.updated'  →  log_name = 'broadsheet', event = 'updated'
 *
 * Properties layout (spatie `properties` JSON column):
 *     { ...caller metadata,
 *       "__actor":   { "name": ..., "role": ... },
 *       "__request": { "method": ..., "route": ..., "url": ..., "ip": ..., "user_agent": ... } }
 */
class ActivityLogService
{
    /**
     * @param string                $action      dotted verb, e.g. 'broadsheet.viewed'
     * @param Model|null             $subject     the record acted on (nullable)
     * @param ActivityMetadata|null  $metadata    typed caller detail (diffs, filters, counts)
     * @param string|null            $description optional human-readable line
     */
    public function log(string $action, $subject = null, ?ActivityMetadata $metadata = null, ?string $description = null): void
    {
        try {
            [$logName, $event] = $this->splitAction($action);

            $user = auth()->user();
            $req  = request();

            $logger = activity($logName)
                ->causedBy($user)
                ->withProperties($this->buildProperties($metadata, $user, $req));

            if ($event !== null) {
                $logger->event($event);
            }

            if ($subject !== null) {
                $logger->performedOn($subject);
            }

            $logger->log($description ?? $action);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** 'broadsheet.updated' => ['broadsheet', 'updated'], 'login' => ['login', null] */
    private function splitAction(string $action): array
    {
        $parts = explode('.', $action, 2);

        return [$parts[0], $parts[1] ?? null];
    }

    private function buildProperties(?ActivityMetadata $metadata, $user, ?Request $req): array
    {
        $resolver = config('audit-log.role_resolver');
        $role     = $resolver
            ? $resolver($user)
            : ($user ? ($user->role ?? $user->urole ?? null) : null);

        $context = [
            '__actor' => [
                'name' => $user ? $user->name : null,
                'role' => $role,
            ],
            '__request' => [
                'method'     => $req ? $req->method() : null,
                'route'      => $req ? optional($req->route())->getName() : null,
                'url'        => $req ? $req->fullUrl() : null,
                'ip'         => $req ? $req->ip() : null,
                'user_agent' => $req ? substr((string) $req->userAgent(), 0, 500) : null,
            ],
        ];

        $caller = $metadata ? $metadata->toProperties() : [];

        return array_merge($caller, $context);
    }
}
