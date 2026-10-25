<?php

namespace Mainul\UuiRolePermission\Console;

use Illuminate\Console\Command;
use Mainul\UuiRolePermission\Actions\AssignRole;
use Mainul\UuiRolePermission\Support\Config;

class AssignCommand extends Command
{
    protected $signature = 'uui-rp:assign {email} {role} {tenant?} {--expires=} {--actor=} {--force} {--reason=}';

    protected $description = 'Assign a role to a user through package guardrails.';

    public function handle(AssignRole $assign): int
    {
        $userClass = Config::model('user');
        $user = $userClass::where('email', $this->argument('email'))->first();
        if (! $user) {
            $this->error('User was not found.');

            return self::FAILURE;
        }
        $actor = null;
        if ($this->option('actor')) {
            $actor = $userClass::where('email', $this->option('actor'))->first();
            if (! $actor) {
                $this->error('Acting administrator was not found.');

                return self::FAILURE;
            }
        }
        if (! $actor && ! $this->option('force')) {
            $this->error('Provide --actor for guardrail checks, or use --force for an explicitly privileged console assignment.');

            return self::FAILURE;
        }
        $tenant = null;
        if ($this->argument('tenant')) {
            $tenantClass = Config::get('tenancy.model');
            $tenant = $tenantClass::find($this->argument('tenant'));
            if (! $tenant) {
                $this->error('Tenant not found.');

                return self::FAILURE;
            }
        }
        $roleClass = Config::model('role');
        $roleQuery = $roleClass::where('code', $this->argument('role'));
        if (Config::tenancyEnabled()) {
            $role = $tenant ? (clone $roleQuery)->where(Config::tenantKey(), $tenant->getKey())->first() : null;
            $role = $role ?? $roleQuery->whereNull(Config::tenantKey())->first();
        } else {
            $role = $roleQuery->first();
        }
        if (! $role) {
            $this->error('Role was not found in the requested scope.');

            return self::FAILURE;
        }
        if (! $this->option('force') && ! $this->confirm('Assign '.$role->code.' to '.$user->email.'?')) {
            return self::SUCCESS;
        }
        $assign->handle($user, $role, $tenant, $actor, $this->option('expires'), $this->option('reason'), (bool) $this->option('force'));
        $this->info('Role assigned.');

        return self::SUCCESS;
    }
}
