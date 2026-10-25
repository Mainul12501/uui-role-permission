<?php

namespace Mainul\UuiRolePermission\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Mainul\UuiRolePermission\Contracts\PermissionRegistry;
use Mainul\UuiRolePermission\Events\AccessControlChanged;
use Mainul\UuiRolePermission\Support\Config;

class SyncCommand extends Command
{
    protected $signature = 'uui-rp:sync {--dry-run : Show changes without writing} {--reset-defaults : Replace configured default role permissions}';

    protected $description = 'Synchronize the configured permission registry.';

    public function handle(PermissionRegistry $registry): int
    {
        $model = Config::model('permission');
        $codes = array_keys($registry->all());
        foreach ($registry->all() as $code => $definition) {
            $attributes = array_merge([
                'name' => Str::of($code)->replace('.', ' ')->replace('_', ' ')->title()->toString(),
                'description' => null, 'group' => null, 'scope' => 'ANY', 'is_deprecated' => false, 'sort_order' => 0,
            ], $definition);
            if ($this->option('dry-run')) {
                $this->line('Would sync '.$code);

                continue;
            }
            $model::updateOrCreate(['code' => $code], $attributes);
        }
        if (! $this->option('dry-run')) {
            $model::whereNotIn('code', $codes)->update(['is_deprecated' => true]);
        }
        foreach (config('uui-role-permission.default_roles', []) as $code => $definition) {
            $permissions = $definition['permissions'] ?? [];
            unset($definition['permissions']);
            if ($this->option('dry-run')) {
                $this->line('Would seed default role '.$code);

                continue;
            }
            $roleClass = Config::model('role');
            $identity = ['code' => $code];
            if (Config::tenancyEnabled()) {
                $identity[Config::tenantKey()] = null;
            }
            $role = $roleClass::firstOrCreate($identity, array_merge(['name' => $code], $definition));
            if ($role->wasRecentlyCreated) {
                $protected = array_intersect_key($definition, array_flip(['is_system', 'is_locked']));
                if ($protected) {
                    $role->forceFill($protected)->save();
                }
            }
            if ($this->option('reset-defaults') || $role->wasRecentlyCreated) {
                $permissionIds = $permissions === '*' ? $model::query()->pluck('id') : $model::whereIn('code', (array) $permissions)->pluck('id');
                $role->permissions()->sync($permissionIds);
            }
        }
        if (! $this->option('dry-run')) {
            AccessControlChanged::dispatch('permission.registry_synced', null, null, 'Permission registry synchronization');
            $this->info('Permission registry synchronized.');
        }

        return self::SUCCESS;
    }
}
