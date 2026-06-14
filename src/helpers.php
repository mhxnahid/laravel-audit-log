<?php

use Mxnwire\AuditLog\Audit\Metadata\AuditMetadata;
use Mxnwire\AuditLog\Services\AuditLogService;
use Illuminate\Database\Eloquent\Model;

if (! function_exists('audit_log')) {
    /**
     * Record an audit-log entry via {@see AuditLogService}.
     *
     * @param string            $action      dotted verb, e.g. 'broadsheet.viewed'
     * @param Model|null        $subject     the record acted on
     * @param AuditMetadata|null $metadata   typed detail
     * @param string|null       $description optional human-readable line
     */
    function audit_log(string $action, $subject = null, ?AuditMetadata $metadata = null, ?string $description = null): void
    {
        app(AuditLogService::class)->log($action, $subject, $metadata, $description);
    }
}

if (! function_exists('activity_log')) {
    /**
     * @deprecated 2.0.0 Use {@see audit_log()} instead. Kept as a thin forwarding
     *             alias so existing callers keep working; will be removed in a
     *             future release.
     *
     * @param string            $action
     * @param Model|null        $subject
     * @param AuditMetadata|null $metadata
     * @param string|null       $description
     */
    function activity_log(string $action, $subject = null, ?AuditMetadata $metadata = null, ?string $description = null): void
    {
        audit_log($action, $subject, $metadata, $description);
    }
}
