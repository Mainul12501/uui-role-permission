<?php

namespace Mainul\UuiRolePermission\Actions;

use Illuminate\Support\Facades\DB;
use Mainul\UuiRolePermission\Events\RoleRevoked;
use Mainul\UuiRolePermission\Exceptions\AccessControlException;
use Mainul\UuiRolePermission\Guards\EscalationGuard;
use Mainul\UuiRolePermission\Guards\LastSuperAdminGuard;
use Mainul\UuiRolePermission\Support\Config;

class RevokeRole
{
    public function handle($assignment, $actor = null, ?string $reason = null, bool $force = false): void
    {
        if (! $force && $actor && config('uui-role-permission.guardrails.prevent_self_modification', true) && (string) $actor->getKey() === (string) $assignment->user_id) {
            throw new AccessControlException('Users cannot change their own role assignments.', AccessControlException::SELF_MODIFICATION);
        }
        $isCurrentAssignment = ! $assignment->expires_at || $assignment->expires_at->isFuture();
        if (! $force && $isCurrentAssignment && $assignment->role && in_array($assignment->role->code, config('uui-role-permission.guardrails.require_reason_for', []), true) && trim((string) $reason) === '') {
            throw new AccessControlException('A reason is required for this role change.', AccessControlException::REASON_REQUIRED);
        }
        app(EscalationGuard::class)->assertPermission($actor, 'users.assign_roles', $assignment->{Config::tenantKey()}, $force);
        app(EscalationGuard::class)->assertCanManage($actor, $assignment->role, $assignment->{Config::tenantKey()}, $force);
        $tenantKey = Config::tenantKey();
        $before = $assignment->getAttributes();
        DB::transaction(function () use ($assignment, $tenantKey, $isCurrentAssignment): void {
            $locked = $assignment->newQuery()->lockForUpdate()->findOrFail($assignment->getKey());
            $isGlobalAssignment = ! Config::tenancyEnabled() || $locked->{$tenantKey} === null;
            if ($isCurrentAssignment && $locked->role && $locked->role->code === config('uui-role-permission.super_admin_role') && $isGlobalAssignment && config('uui-role-permission.guardrails.protect_last_super_admin', true)) {
                // Serializing on the protected role row prevents two concurrent revocations from both removing the final admins.
                $role = $locked->role->newQuery()->lockForUpdate()->find($locked->role->getKey());
                if ($role) {
                    app(LastSuperAdminGuard::class)->assertCanRevoke($locked);
                }
            }
            $locked->delete();
        });
        RoleRevoked::dispatch($assignment, $actor, $reason, $before);
    }
}
