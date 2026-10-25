<?php

namespace Mainul\UuiRolePermission\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Mainul\UuiRolePermission\Contracts\SessionRevoker;
use Mainul\UuiRolePermission\Support\Config;

class RevokeUserSessions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Role permission session revocation failed.', ['user_id' => $this->userId, 'exception' => $exception]);
    }

    public function __construct(public $userId)
    {
        $this->onConnection(config('uui-role-permission.queue.connection'));
        $this->onQueue(config('uui-role-permission.queue.queue', 'default'));
    }

    public function handle(SessionRevoker $revoker): void
    {
        $userModel = Config::model('user');
        if ($user = $userModel::find($this->userId)) {
            $revoker->revoke($user);
        }
    }
}
