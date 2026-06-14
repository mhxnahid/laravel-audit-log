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
