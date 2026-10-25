<?php

namespace Mainul\UuiRolePermission\Guards;

use Mainul\UuiRolePermission\Exceptions\AccessControlException;
use Mainul\UuiRolePermission\Support\Config;

class EscalationGuard
{
    public function assertPermission($actor, string $permission, $tenant = null, bool $bypass = false): void
    {
        if ($bypass || ! config('uui-role-permission.guardrails.prevent_escalation', true)) {
            return;
        }
        if (! $actor || ! method_exists($actor, 'hasPermission') || ! $actor->hasPermission($permission, $tenant)) {
            throw new AccessControlException('The actor does not hold the required management permission.', AccessControlException::ESCALATION_DENIED);
        }
    }

    public function assertNotHoldingRole($actor, $role, bool $bypass = false): void
    {
        if ($bypass || ! config('uui-role-permission.guardrails.prevent_self_modification', true) || ! $actor || ! method_exists($actor, 'roleAssignments')) {
            return;
        }
        if ($actor->roleAssignments()->where('role_id', $role->getKey())->where(function ($query): void {
            $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
        })->exists()) {
            throw new AccessControlException('Users cannot modify a role they currently hold.', AccessControlException::SELF_MODIFICATION);
        }
    }

    public function assertCanManage($actor, $targetRole, $tenant = null, bool $bypass = false): void
    {
        if ($bypass || ! config('uui-role-permission.guardrails.prevent_escalation', true)) {
            return;
        }
        if (! $actor) {
            throw new AccessControlException('An acting user is required for role management.', AccessControlException::ESCALATION_DENIED);
        }
        if (! $targetRole) {
            return;
        }
        if (method_exists($actor, 'isSuperAdmin') && $actor->isSuperAdmin()) {
            return;
        }
        if ($targetRole->is_system || $targetRole->code === config('uui-role-permission.super_admin_role')) {
            throw new AccessControlException('Only a super administrator may manage this role.', AccessControlException::ESCALATION_DENIED);
        }
        if (! method_exists($actor, 'roleAssignments')) {
            throw new AccessControlException('The actor cannot be verified for role management.', AccessControlException::ESCALATION_DENIED);
        }
        $tenantId = $tenant && method_exists($tenant, 'getKey') ? $tenant->getKey() : $tenant;
        $query = $actor->roleAssignments()->with('role.permissions');
        if (Config::tenancyEnabled()) {
            $query->where(function ($scope) use ($tenantId): void {
                $scope->whereNull(Config::tenantKey());
                if ($tenantId !== null) {
                    $scope->orWhere(Config::tenantKey(), $tenantId);
                }
            });
        }
        $assignments = $query->whereHas('role')->where(function ($query): void {
            $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
        })->get();
        if ($assignments->isEmpty()) {
            throw new AccessControlException('The actor has no role management authority.', AccessControlException::ESCALATION_DENIED);
        }
        $maxPriority = $assignments->max(fn ($assignment) => (int) ($assignment->role->priority ?? 0));
        if ((int) $targetRole->priority > $maxPriority) {
            throw new AccessControlException('The target role exceeds the actor role priority.', AccessControlException::ESCALATION_DENIED);
        }
        $held = [];
        foreach ($assignments as $assignment) {
            foreach ($assignment->role->permissions as $permission) {
                $held[$permission->code] = true;
            }
        }
        foreach ($targetRole->permissions as $permission) {
            if (! isset($held[$permission->code])) {
                throw new AccessControlException('The actor cannot manage a role with permissions they do not hold.', AccessControlException::ESCALATION_DENIED);
            }
        }
    }

    public function assertCanCreate($actor, $tenant = null): void
    {
        if (! config('uui-role-permission.guardrails.prevent_escalation', true)) {
            return;
        }
        if (! $actor) {
            throw new AccessControlException('An acting user is required for role management.', AccessControlException::ESCALATION_DENIED);
        }
        if (method_exists($actor, 'isSuperAdmin') && $actor->isSuperAdmin()) {
            return;
        }
        $this->assertPermission($actor, 'roles.manage', $tenant);
    }

    public function assertCanGrantPermissions($actor, array $permissionCodes, $tenant = null): void
    {
        if (! config('uui-role-permission.guardrails.prevent_escalation', true)) {
            return;
        }
        if (! $actor) {
            throw new AccessControlException('An acting user is required for permission changes.', AccessControlException::ESCALATION_DENIED);
        }
        if (method_exists($actor, 'isSuperAdmin') && $actor->isSuperAdmin()) {
            return;
        }
        if (! method_exists($actor, 'hasPermission')) {
            throw new AccessControlException('The actor cannot be verified for role management.', AccessControlException::ESCALATION_DENIED);
        }
        foreach ($permissionCodes as $permissionCode) {
            if (! $actor->hasPermission($permissionCode, $tenant)) {
                throw new AccessControlException('The actor cannot grant permissions they do not hold.', AccessControlException::ESCALATION_DENIED);
            }
        }
    }
}
