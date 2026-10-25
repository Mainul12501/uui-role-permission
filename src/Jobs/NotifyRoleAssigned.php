<?php

namespace Mainul\UuiRolePermission\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Mainul\UuiRolePermission\Support\Config;

class NotifyRoleAssigned implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public function __construct(public $assignmentId)
    {
        $this->onConnection(config('uui-role-permission.queue.connection'));
        $this->onQueue(config('uui-role-permission.queue.queue', 'default'));
    }

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Role assignment notification failed.', ['assignment_id' => $this->assignmentId, 'exception' => $exception]);
    }

    public function handle(): void
    {
        $notifier = Config::get('notifications.role_assigned');
        if (is_string($notifier) && class_exists($notifier)) {
            $notifier = app($notifier);
        }
        $model = Config::model('assignment');
        if (is_callable($notifier) && ($assignment = $model::find($this->assignmentId))) {
            call_user_func($notifier, $assignment);
        }
    }
}
