<?php

namespace Mxnwire\AuditLog;

use Mxnwire\AuditLog\Contracts\AuditTypeRegistryContract;
use Spatie\Activitylog\Models\Activity;

class AuditTypeRegistry implements AuditTypeRegistryContract
{
    public function logNames(): array
    {
        return Activity::query()
            ->distinct()
            ->whereNotNull('log_name')
            ->orderBy('log_name')
            ->pluck('log_name')
            ->toArray();
    }

    public function events(): array
    {
        return Activity::query()
            ->distinct()
            ->whereNotNull('event')
            ->orderBy('event')
            ->pluck('event')
            ->toArray();
    }

    public function subjectTypes(): array
    {
        return Activity::query()
            ->distinct()
            ->whereNotNull('subject_type')
            ->orderBy('subject_type')
            ->pluck('subject_type')
            ->toArray();
    }
}
