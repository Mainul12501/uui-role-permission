<?php

namespace Mainul\UuiRolePermission\Adapters;

use Mainul\UuiRolePermission\Contracts\AuditRecorder;

class NullAuditRecorder implements AuditRecorder
{
    public function record(string $action, $subject, $actor = null, ?string $reason = null, ?array $before = null, ?array $after = null): void {}
}
