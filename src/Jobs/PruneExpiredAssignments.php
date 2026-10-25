<?php

namespace Mainul\UuiRolePermission\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Mainul\UuiRolePermission\Actions\RevokeRole;
use Mainul\UuiRolePermission\Support\Config;

class PruneExpiredAssignments implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public function __construct()
    {
        $this->onConnection(config('uui-role-permission.queue.connection'));
        $this->onQueue(config('uui-role-permission.queue.queue', 'default'));
    }

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('uui-rp-prune-assignments'))->expireAfter(900)];
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Expired role assignment pruning failed.', ['exception' => $exception]);
    }

    public function handle(RevokeRole $revoke): void
    {
        $model = Config::model('assignment');
        $model::where('expires_at', '<=', now())->chunkById(200, function ($assignments) use ($revoke): void {
            foreach ($assignments as $assignment) {
                $revoke->handle($assignment, null, 'Expired assignment cleanup', true);
            }
        });
    }
}
