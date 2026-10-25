<?php

namespace Mainul\UuiRolePermission\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Mainul\UuiRolePermission\Support\Config;

class DoctorCommand extends Command
{
    protected $signature = 'uui-rp:doctor';

    protected $description = 'Check package configuration and database health.';

    public function handle(): int
    {
        $failed = false;
        foreach (config('uui-role-permission.tables', []) as $name => $table) {
            if ($name === 'memberships' && ! Config::tenancyEnabled()) {
                continue;
            }
            if ($name === 'audit_logs' && config('uui-role-permission.audit.driver') !== 'database') {
                continue;
            }
            if (! Schema::hasTable($table)) {
                $this->error("Missing {$name} table: {$table}");
                $failed = true;
            } else {
                $this->info("OK {$table}");
            }
        }
        try {
            DB::connection()->getPdo();
            $this->info('Database connection OK');
        } catch (\Throwable $exception) {
            $this->error('Database unavailable: '.$exception->getMessage());
            $failed = true;
        }
        try {
            $store = config('uui-role-permission.cache.store') ? Cache::store(config('uui-role-permission.cache.store')) : Cache::store();
            $probe = config('uui-role-permission.cache.prefix', 'uui-rp').':doctor:'.uniqid('', true);
            $store->put($probe, 'ok', 10);
            if ($store->get($probe) !== 'ok') {
                throw new \RuntimeException('Cache read did not return the written value.');
            }
            $store->forget($probe);
            $this->info('Cache store reachable');
        } catch (\Throwable $exception) {
            $this->error('Cache unavailable: '.$exception->getMessage());
            $failed = true;
        }
        if (config('uui-role-permission.queue.audit_async', true) && config('queue.default') === 'sync') {
            $this->warn('Audit is configured for async delivery but the queue driver is sync.');
        }
        $registered = array_keys((array) config('uui-role-permission.permissions', []));
        $permissionModel = Config::model('permission');
        if (Schema::hasTable(Config::table('permissions'))) {
            $known = $permissionModel::query()->pluck('code')->all();
            foreach (array_diff($registered, $known) as $code) {
                $this->error('Registry permission has not been synchronized: '.$code);
                $failed = true;
            }
            $routeRoot = base_path('routes');
            if (File::isDirectory($routeRoot)) {
                foreach (File::allFiles($routeRoot) as $file) {
                    preg_match_all('/permission:([A-Za-z0-9_.-]+)/', File::get($file->getPathname()), $matches);
                    foreach ($matches[1] as $code) {
                        if (! in_array($code, $registered, true) && ! in_array($code, $known, true)) {
                            $this->warn('Route references unregistered permission: '.$code);
                            $failed = true;
                        }
                    }
                }
            }
            $roleModel = Config::model('role');
            foreach ($roleModel::whereDoesntHave('assignments')->pluck('code') as $code) {
                $this->line('Role has no assignments: '.$code);
            }
            $assignmentModel = Config::model('assignment');
            foreach ($assignmentModel::query()->whereNotNull('expires_at')->where('expires_at', '<=', now())->distinct()->pluck('user_id') as $userId) {
                $hasCurrent = $assignmentModel::query()->where('user_id', $userId)->where(function ($query): void {
                    $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })->exists();
                if (! $hasCurrent) {
                    $this->warn('User has expired-only access: '.$userId);
                }
            }
            if (Schema::hasTable('failed_jobs') && DB::table('failed_jobs')->exists()) {
                $this->warn('The failed_jobs table contains failed queue jobs.');
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
