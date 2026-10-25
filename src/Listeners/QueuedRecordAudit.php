<?php

namespace Mainul\UuiRolePermission\Listeners;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Mainul\UuiRolePermission\Contracts\AuditRecorder;
use Mainul\UuiRolePermission\Events\AccessControlChanged;

class QueuedRecordAudit implements ShouldQueue
{
    use Queueable;

    public $afterCommit = true;

    public $tries = 3;

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function failed(AccessControlChanged $event, \Throwable $exception): void
    {
        Log::error('Access-control audit recording failed.', ['action' => $event->action, 'exception' => $exception]);
    }

    public function __construct()
    {
        $this->onConnection(config('uui-role-permission.queue.connection'));
        $this->onQueue(config('uui-role-permission.queue.queue', 'default'));
    }

    public function handle(AccessControlChanged $event): void
    {
        app(AuditRecorder::class)->record($event->action, $event->subject, $event->actor, $event->reason, $event->before, $event->after);
    }
}
