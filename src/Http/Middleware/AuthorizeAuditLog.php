<?php

namespace Mxnwire\AuditLog\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Gate the audit-log viewer using the callable in config('audit-log.gate').
 *
 * The gate receives the authenticated user (or null) and must return a boolean.
 * Implemented as a class-based middleware because Laravel casts every route
 * middleware entry to a string — a Closure passed inline would fatal with
 * "Object of class Closure could not be converted to string".
 */
class AuthorizeAuditLog
{
    public function handle(Request $request, Closure $next)
    {
        $gate = config('audit-log.gate');

        abort_unless(is_callable($gate) ? $gate($request->user()) : (bool) $gate, 403);

        return $next($request);
    }
}
