<?php

namespace Mainul\UuiRolePermission\Listeners;

use Mainul\UuiRolePermission\Contracts\AuditRecorder;
use Mainul\UuiRolePermission\Events\AccessControlChanged;

class RecordAudit
{
    public function handle(AccessControlChanged $event): void
    {
        app(AuditRecorder::class)->record($event->action, $event->subject, $event->actor, $event->reason, $event->before, $event->after);
    }
}
