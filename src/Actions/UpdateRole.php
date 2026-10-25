<?php

namespace Mainul\UuiRolePermission\Actions;

use Illuminate\Support\Facades\DB;
use Mainul\UuiRolePermission\Events\RoleUpdated;
use Mainul\UuiRolePermission\Exceptions\AccessControlException;
use Mainul\UuiRolePermission\Guards\EscalationGuard;
use Mainul\UuiRolePermission\Guards\SystemRoleGuard;
use Mainul\UuiRolePermission\Support\Config;

class UpdateRole
{
    public function handle($role, array $attributes, $actor = null, ?string $reason = null, ?string $expectedUpdatedAt = null)
    {
        $result = DB::transaction(function () use ($role, $attributes, $expectedUpdatedAt, $actor) {
            $role = $role->newQuery()->lockForUpdate()->findOrFail($role->getKey());
            if ($expectedUpdatedAt && (string) $role->updated_at !== $expectedUpdatedAt) {
                throw new AccessControlException('The role was changed by another request.', AccessControlException::STALE_ROLE);
            }
            app(SystemRoleGuard::class)->assertMutable($role);
            app(EscalationGuard::class)->assertPermission($actor, 'roles.manage', $role->{Config::tenantKey()});
            app(EscalationGuard::class)->assertNotHoldingRole($actor, $role);
            app(EscalationGuard::class)->assertCanManage($actor, $role, $role->{Config::tenantKey()});
            $before = $role->getAttributes();
            if ($actor) {
                $attributes['updated_by'] = $actor->getKey();
            }
            $role->fill($attributes)->save();

            return ['role' => $role, 'before' => $before];
        });
        $role = $result['role'];
        RoleUpdated::dispatch($role, $actor, $reason, $result['before']);

        return $role;
    }
}
