<?php

namespace Mxnwire\AuditLog\Services;

use Mxnwire\AuditLog\Audit\Metadata\AuditMetadata;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;

/**
 * Audit logging layered on top of spatie/laravel-activitylog.
 *
 * Spatie owns the storage (the `activity_log` table, polymorphic causer/subject
 * resolution, and the `activitylog:clean` prune command). This service adds:
 *
 *   1. Identity snapshot — actor name + role frozen at action time.
 *   2. Request context — every property enabled in config `request_context`
 *      (the published config ships with method, route, url, ip, user_agent, query,
 *      and a `headers` group; body logging is opt-in), each overridable per-call
 *      via the metadata bag. Logged query/body input is filtered through the
 *      config `redact` list so secrets never reach the log.
 *   3. Best-effort, non-blocking writes — a failed insert is reported and
 *      swallowed; it never surfaces as a user-facing error.
 *
 * The dotted action maps onto spatie's two indexable columns:
 *     'broadsheet.updated'  →  log_name = 'broadsheet', event = 'updated'
 *
 * Properties layout (spatie `properties` JSON column):
 *     { ...caller metadata,
 *       "__actor":   { "name": ..., "role": ... },
 *       "__request": { ...config('audit-log.request_context') resolved per request } }
 */
class AuditLogService
{
    /**
     * @param string                $action      dotted verb, e.g. 'broadsheet.viewed'
     * @param Model|null             $subject     the record acted on (nullable)
     * @param AuditMetadata|null     $metadata    typed caller detail (diffs, filters, counts)
     * @param string|null            $description optional human-readable line
     */
    public function log(string $action, $subject = null, ?AuditMetadata $metadata = null, ?string $description = null): void
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

    private function buildProperties(?AuditMetadata $metadata, $user, ?Request $req): array
    {
        $resolver = config('audit-log.role_resolver');
        $role     = $resolver
            ? $resolver($user)
            : ($user ? ($user->role ?? null) : null);

        $caller = $metadata ? $metadata->toProperties() : [];

        // __request is defined by config('audit-log.request_context'): a list of
        // built-in property names (resolved by $this->builtinResolvers()) plus
        // any keyed `name => source` overrides (header string or `fn ($request)`),
        // with header-sourced properties under a nested `headers` key. An explicit
        // value in the metadata bag wins over the source and is pulled out of
        // $caller so it lives only under __request, not also at the top level.
        // Null/empty results are dropped.
        $request = [];

        foreach ($this->requestContextSources() as $key => $source) {
            if ($source === false) {
                unset($caller[$key]);
                continue;
            }

            if ($key === 'headers') {
                $headers = $this->resolveContextGroup((array) $source, $caller, $req);

                if ($headers !== []) {
                    $request['headers'] = $headers;
                }

                continue;
            }

            $value = $this->resolveContextProperty($key, $source, $caller, $req);

            if ($value !== null && $value !== '') {
                $request[$key] = $value;
            }
        }

        $context = [
            '__actor' => [
                'name' => $user ? $user->name : null,
                'role' => $role,
            ],
            '__request' => $request,
        ];

        return array_merge($caller, $context);
    }

    /**
     * Built-in request-context resolvers, keyed by property name. Listing a name
     * in config `request_context` enables the matching resolver; a keyed override
     * in config replaces it.
     *
     * @return array<string, callable>
     */
    private function builtinResolvers(): array
    {
        return [
            'method'     => fn ($request) => $request->method(),
            'route'      => fn ($request) => optional($request->route())->getName(),
            'url'        => fn ($request) => $request->fullUrl(),
            'ip'         => fn ($request) => $request->ip(),
            'user_agent' => fn ($request) => substr((string) $request->userAgent(), 0, 500),
            'query'      => fn ($request) => $this->redactInput($request->query()) ?: null,
            'body'       => fn ($request) => $this->redactInput($this->requestBody($request)) ?: null,
        ];
    }

    /**
     * Pull the request body (form input plus any JSON payload), independent of
     * the query string.
     *
     * @return array<string, mixed>
     */
    private function requestBody(Request $request): array
    {
        $body = $request->post();

        if ($request->isJson()) {
            $body = array_merge($body, (array) $request->json()->all());
        }

        return $body;
    }

    /**
     * Strip sensitive keys from logged input (query/body). Matching is
     * case-insensitive and recurses into nested arrays; matched values become
     * '[REDACTED]'. The key list comes from config `redact`, falling back to the
     * package defaults when unset.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function redactInput(array $input): array
    {
        $keys = config('audit-log.redact');
        $keys = is_array($keys) ? $keys : $this->defaultRedactKeys();

        return $this->redactKeys($input, array_map('strtolower', $keys));
    }

    /**
     * @param array<string, mixed> $input
     * @param array<int, string>   $redact lower-cased key names to redact
     * @return array<string, mixed>
     */
    private function redactKeys(array $input, array $redact): array
    {
        foreach ($input as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), $redact, true)) {
                $input[$key] = '[REDACTED]';
                continue;
            }

            if (is_array($value)) {
                $input[$key] = $this->redactKeys($value, $redact);
            }
        }

        return $input;
    }

    /** @return array<int, string> */
    private function defaultRedactKeys(): array
    {
        return [
            'password', 'password_confirmation', 'current_password',
            'token', '_token', 'secret', 'api_key', 'authorization',
            'credit_card', 'card_number', 'cvv',
        ];
    }

    /**
     * Normalise config `request_context` into a `name => source` map. Bare list
     * entries (`'method'`) resolve to their built-in resolver; keyed entries
     * (`'name' => source`) pass through as-is, letting users add properties or
     * override a built-in. Unknown bare names are skipped. The `headers` group is
     * preserved under its key for separate handling.
     *
     * @return array<string, string|callable|false|array<string, mixed>>
     */
    private function requestContextSources(): array
    {
        $builtins = $this->builtinResolvers();
        $sources  = [];

        foreach ((array) config('audit-log.request_context', []) as $key => $value) {
            if ($key === 'headers') {
                $sources['headers'] = $value;
                continue;
            }

            if (is_int($key)) {
                if (isset($builtins[$value])) {
                    $sources[$value] = $builtins[$value];
                }
                continue;
            }

            $sources[$key] = $value;
        }

        return $sources;
    }

    /**
     * Resolve a group of request-context properties (e.g. the `headers` group)
     * into a flat array, dropping null/empty results.
     *
     * @param array<string, string|callable|false> $sources
     * @param array<string, mixed>                 $caller
     * @return array<string, mixed>
     */
    private function resolveContextGroup(array $sources, array &$caller, ?Request $req): array
    {
        $out = [];

        foreach ($sources as $key => $source) {
            if ($source === false) {
                unset($caller[$key]);
                continue;
            }

            $value = $this->resolveContextProperty($key, $source, $caller, $req);

            if ($value !== null && $value !== '') {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    /**
     * Resolve a single request-context property, honouring an explicit override
     * in the caller metadata over the configured source. The source is either an
     * inbound header name (string) or a `fn ($request) => mixed` callable. The
     * key is removed from $caller so it is not also emitted at the top level.
     *
     * @param string|callable      $source
     * @param array<string, mixed> $caller
     * @return mixed
     */
    private function resolveContextProperty(string $key, $source, array &$caller, ?Request $req)
    {
        if (array_key_exists($key, $caller)) {
            $override = $caller[$key];
            unset($caller[$key]);

            if ($override !== null && $override !== '') {
                return $override;
            }
        }

        if ($req === null) {
            return null;
        }

        if (is_callable($source)) {
            return $source($req);
        }

        return is_string($source) ? $req->header($source) : null;
    }
}
