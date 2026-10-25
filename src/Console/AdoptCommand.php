<?php

namespace Mainul\UuiRolePermission\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

class AdoptCommand extends Command
{
    protected $signature = 'uui-rp:adopt {--dry-run : Report the schema diff without changing it} {--apply : Apply additive package migrations}';

    protected $description = 'Inspect the configured existing role permission schema before adoption.';

    public function handle(): int
    {
        if ($this->option('apply') && $this->option('dry-run')) {
            $this->error('Choose either --dry-run or --apply, not both.');

            return self::INVALID;
        }
        $issues = [];
        $required = [
            'permissions' => ['id', 'code', 'name', 'group', 'scope', 'is_deprecated', 'sort_order'],
            'roles' => ['id', 'code', 'name', 'is_system', 'is_locked', 'assignable_scope', 'priority', 'deleted_at'],
            'role_permissions' => ['role_id', 'permission_id'],
            'assignments' => ['id', 'user_id', 'role_id', 'assigned_by', 'expires_at'],
            'audit_logs' => ['id', 'tenant_id', 'actor_id', 'action', 'subject_type', 'subject_id', 'before', 'after', 'reason', 'created_at'],
        ];
        if (config('uui-role-permission.tenancy.enabled', true)) {
            $required['memberships'] = ['user_id', config('uui-role-permission.tenancy.foreign_key'), 'status'];
        }
        foreach (config('uui-role-permission.tables', []) as $name => $table) {
            if ($name === 'memberships' && ! config('uui-role-permission.tenancy.enabled', true)) {
                continue;
            }
            if ($name === 'audit_logs' && config('uui-role-permission.audit.driver') !== 'database') {
                continue;
            }
            if (! Schema::hasTable($table)) {
                $issues[] = "Missing table: {$table}";

                continue;
            }
            $this->line("Found table: {$table}");
            foreach ($required[$name] ?? [] as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    $issues[] = "Missing column: {$table}.{$column}";
                }
            }
        }
        foreach ($issues as $issue) {
            $this->warn($issue);
        }
        if ($this->option('apply')) {
            Artisan::call('migrate', ['--force' => true, '--path' => realpath(__DIR__.'/../../database/migrations'), '--realpath' => true]);
            $this->line(Artisan::output());
            $this->info('Additive migrations applied.');
        } else {
            $this->info('Dry run complete; no schema changes were made. Use --apply after reviewing the diff.');
        }

        return self::SUCCESS;
    }
}
