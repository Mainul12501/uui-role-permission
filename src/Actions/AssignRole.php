<?php

namespace Mainul\UuiRolePermission\Actions;

use Mainul\UuiRolePermission\Events\RoleAssigned;
use Mainul\UuiRolePermission\Exceptions\AccessControlException;
use Mainul\UuiRolePermission\Guards\EscalationGuard;
use Mainul\UuiRolePermission\Guards\ScopeGuard;
use Mainul\UuiRolePermission\Support\Config;
use Mainul\UuiRolePermission\Support\Transaction;

class AssignRole
{
    public function handle($user, $role, $tenant = null, $actor = null, ?string $expiresAt = null, ?string $reason = null, bool $force = false)
    {
        if ($tenant !== null && ! is_object($tenant) && Config::tenancyEnabled()) {
            $tenantModel = Config::get('tenancy.model');
            $tenant = $tenantModel::findOrFail($tenant);
        }
        $activityChecker = Config::get('user_active');
        if (is_string($activityChecker) && class_exists($activityChecker)) {
            $activityChecker = app($activityChecker);
        }
        if ($activityChecker !== null && (! is_callable($activityChecker) || ! $activityChecker($user))) {
            throw new AccessControlException('Inactive users cannot receive role assignments.', AccessControlException::TENANT_MISMATCH);
        }
        if (! $force && $actor && config('uui-role-permission.guardrails.prevent_self_modification', true) && (string) $actor->getKey() === (string) $user->getKey()) {
            throw new AccessControlException('Users cannot change their own role assignments.', AccessControlException::SELF_MODIFICATION);
        }
        if (! $force && in_array($role->code, config('uui-role-permission.guardrails.require_reason_for', []), true) && trim((string) $reason) === '') {
            throw new AccessControlException('A reason is required for this role assignment.', AccessControlException::REASON_REQUIRED);
        }
        $tenantKey = Config::tenantKey();
        app(EscalationGuard::class)->assertPermission($actor, 'users.assign_roles', $tenant, $force);
        app(EscalationGuard::class)->assertCanManage($actor, $role, $tenant, $force);
        app(ScopeGuard::class)->assertRoleAssignmentCompatible($role, $tenant);
        if (Config::tenancyEnabled()) {
            $tenantId = $tenant && method_exists($tenant, 'getKey') ? $tenant->getKey() : $tenant;
            if ($tenantId !== null && config('uui-role-permission.tenancy.require_membership', true)) {
                $membershipModel = Config::model('membership');
                $member = $membershipModel::where($tenantKey, $tenantId)->where('user_id', $user->getKey())->whereIn('status', config('uui-role-permission.tenancy.membership_active_values', ['ACTIVE']))->exists();
                if (! $member) {
                    throw new AccessControlException('An active tenant membership is required.', AccessControlException::TENANT_MISMATCH);
                }
            }
            if ($tenant && is_object($tenant)) {
                $activeColumn = config('uui-role-permission.tenancy.active_column');
                $activeValues = config('uui-role-permission.tenancy.active_values', []);
                if ($activeColumn && $activeValues && ! in_array($tenant->{$activeColumn}, $activeValues, true)) {
                    throw new AccessControlException('Inactive tenants cannot receive role assignments.', AccessControlException::TENANT_MISMATCH);
                }
            }
        }
        $result = Transaction::run(function () use ($user, $role, $tenant, $actor, $expiresAt): array {
            $model = Config::model('assignment');
            $key = Config::tenantKey();
            $attributes = ['user_id' => $user->getKey(), 'role_id' => $role->getKey()];
            if (Config::tenancyEnabled()) {
                $attributes[$key] = $tenant ? (method_exists($tenant, 'getKey') ? $tenant->getKey() : $tenant) : null;
            }
            $assignment = $model::firstOrNew($attributes);
            $before = $assignment->exists ? $assignment->getAttributes() : null;
            $assignment->assigned_by = $actor ? $actor->getKey() : null;
            $assignment->expires_at = $expiresAt;
            $assignment->save();

            return [$assignment, $assignment->wasRecentlyCreated || $assignment->wasChanged(), $before];
        });
        $assignment = $result[0];
        if ($result[1]) {
            RoleAssigned::dispatch($assignment, $actor, $reason, $result[2]);
        }

        return $assignment;
    }
}
