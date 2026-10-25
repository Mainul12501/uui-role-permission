<?php

namespace Mainul\UuiRolePermission\Actions;

use Illuminate\Support\Facades\DB;
use Mainul\UuiRolePermission\Events\RoleCreated;
use Mainul\UuiRolePermission\Guards\EscalationGuard;
use Mainul\UuiRolePermission\Support\Config;

class CreateRole
{
    public function handle(array $attributes, $actor = null, ?string $reason = null)
    {
        app(EscalationGuard::class)->assertCanCreate($actor, $attributes[Config::tenantKey()] ?? null);
        if ($actor) {
            $attributes['created_by'] = $actor->getKey();
            $attributes['updated_by'] = $actor->getKey();
        }
        $role = DB::transaction(function () use ($attributes) {
            $roleClass = Config::model('role');

            return $roleClass::create($attributes);
        });
        RoleCreated::dispatch($role, $actor, $reason);

        return $role;
    }
}
