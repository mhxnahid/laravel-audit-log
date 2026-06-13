<?php

use Mxnwire\AuditLog\Activity\Metadata\ActivityMetadata;
use Mxnwire\AuditLog\Services\ActivityLogService;
use Illuminate\Database\Eloquent\Model;

if (! function_exists('activity_log')) {
    /**
     * Record an activity-log entry via {@see ActivityLogService}.
     *
     * @param string           $action      dotted verb, e.g. 'broadsheet.viewed'
     * @param Model|null        $subject     the record acted on
     * @param ActivityMetadata|null $metadata typed detail
     * @param string|null       $description optional human-readable line
     */
    function activity_log(string $action, $subject = null, ?ActivityMetadata $metadata = null, ?string $description = null): void
    {
        app(ActivityLogService::class)->log($action, $subject, $metadata, $description);
    }
}
