<?php

namespace Mainul\UuiRolePermission\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mainul\UuiRolePermission\Support\Config;

class PruneAuditLogs implements ShouldQueue
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
        return [30, 60, 120];
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('uui-rp-prune-audit'))->expireAfter(3600)];
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Audit log retention pruning failed.', ['exception' => $exception]);
    }

    public function handle(): void
    {
        if (config('uui-role-permission.audit.driver') !== 'database') {
            return;
        }
        do {
            $query = DB::table(Config::table('audit_logs'))
                ->where('created_at', '<', now()->subDays((int) config('uui-role-permission.audit.retention_days', 365)))
                ->orderBy('created_at')->limit(1000);
            $ids = $query->pluck('id');
            if ($ids->isEmpty()) {
                break;
            }
            DB::table(Config::table('audit_logs'))->whereIn('id', $ids)->delete();
        } while ($ids->count() === 1000);
    }
}
