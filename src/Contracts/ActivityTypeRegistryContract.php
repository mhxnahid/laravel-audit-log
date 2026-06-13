<?php

namespace Mxnwire\AuditLog\Contracts;

interface ActivityTypeRegistryContract
{
    /** All distinct log_name values (resource names before the dot). */
    public function logNames(): array;

    /** All distinct event values (verbs after the dot). */
    public function events(): array;

    /** All distinct subject model FQCNs registered in this app. */
    public function subjectTypes(): array;
}
