<?php

namespace Mainul\UuiRolePermission\Contracts;

interface AuditRecorder
{
    public function record(string $action, $subject, $actor = null, ?string $reason = null, ?array $before = null, ?array $after = null): void;
}
