<?php

namespace Mainul\UuiRolePermission\Console;

use Illuminate\Console\Command;
use Mainul\UuiRolePermission\Actions\AssignRole;
use Mainul\UuiRolePermission\Support\Config;

class SuperAdminCommand extends Command
{
    protected $signature = 'uui-rp:super-admin {email} {--force}';

    protected $description = 'Grant the protected global super administrator role.';

    public function handle(AssignRole $assign): int
    {
        $userClass = Config::model('user');
        $user = $userClass::where('email', $this->argument('email'))->first();
        $roleClass = Config::model('role');
        $query = $roleClass::where('code', config('uui-role-permission.super_admin_role'));
        if (Config::tenancyEnabled()) {
            $query->whereNull(Config::tenantKey());
        }
        $role = $query->first();
        if (! $user || ! $role) {
            $this->error('User or configured super admin role was not found.');

            return self::FAILURE;
        }
        if (! $this->option('force') && ! $this->confirm('Grant global super administrator to '.$user->email.'?')) {
            return self::SUCCESS;
        }
        $assign->handle($user, $role, null, null, null, 'Granted by uui-rp:super-admin', true);
        $this->info('Super administrator role granted.');

        return self::SUCCESS;
    }
}
