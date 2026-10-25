<?php

namespace Mainul\UuiRolePermission\Actions;

use Mainul\UuiRolePermission\Events\RoleDeleted;
use Mainul\UuiRolePermission\Exceptions\AccessControlException;
use Mainul\UuiRolePermission\Guards\EscalationGuard;
use Mainul\UuiRolePermission\Guards\SystemRoleGuard;
use Mainul\UuiRolePermission\Support\Config;
use Mainul\UuiRolePermission\Support\Transaction;

class DeleteRole
{
    public function handle($role, $actor = null, ?string $reason = null, $reassignTo = null): void
    {
        $result = Transaction::run(function () use ($role, $actor, $reassignTo): array {
            $role = $role->newQuery()->lockForUpdate()->findOrFail($role->getKey());
            app(SystemRoleGuard::class)->assertMutable($role);
            app(EscalationGuard::class)->assertPermission($actor, 'roles.manage', $role->{Config::tenantKey()});
            app(EscalationGuard::class)->assertNotHoldingRole($actor, $role);
            app(EscalationGuard::class)->assertCanManage($actor, $role, $role->{Config::tenantKey()});
            $before = $role->getAttributes();
            if ($role->assignments()->exists()) {
                if (! $reassignTo) {
                    throw new AccessControlException('Role still has assignments; choose a replacement role.', AccessControlException::ROLE_IN_USE);
                }
                $replacementClass = Config::model('role');
                $replacement = is_object($reassignTo) ? $reassignTo : $replacementClass::findOrFail($reassignTo);
                if ((string) $replacement->getKey() === (string) $role->getKey()) {
                    throw new AccessControlException('A role cannot be reassigned to itself.', AccessControlException::TENANT_MISMATCH);
                }
                app(EscalationGuard::class)->assertCanManage($actor, $replacement, $role->{Config::tenantKey()});
                $sourcePermissions = $role->permissions()->pluck('code')->all();
                $replacementPermissions = $replacement->permissions()->pluck('code')->all();
                if (array_diff($replacementPermissions, $sourcePermissions)) {
                    throw new AccessControlException('The replacement role grants additional permissions.', AccessControlException::ESCALATION_DENIED);
                }
                if ((int) $replacement->priority > (int) $role->priority) {
                    throw new AccessControlException('The replacement role has a higher priority.', AccessControlException::ESCALATION_DENIED);
                }
                $tenantKey = Config::tenantKey();
                $reassignedCount = 0;
                $role->assignments()->chunkById(200, function ($assignments) use ($replacement, $tenantKey, &$reassignedCount): void {
                    foreach ($assignments as $assignment) {
                        if (Config::tenancyEnabled()) {
                            $assignmentTenant = $assignment->{$tenantKey};
                            if (($replacement->{$tenantKey} !== null && (string) $replacement->{$tenantKey} !== (string) $assignmentTenant)
                                || ($assignmentTenant === null && $replacement->assignable_scope === 'TENANT')
                                || ($assignmentTenant !== null && $replacement->assignable_scope === 'GLOBAL')) {
                                throw new AccessControlException('Replacement role scope is incompatible with an assignment.', AccessControlException::TENANT_MISMATCH);
                            }
                        }
                        $duplicateQuery = $assignment->newQuery()->where('user_id', $assignment->user_id)->where('role_id', $replacement->getKey());
                        if (Config::tenancyEnabled()) {
                            $assignmentTenant = $assignment->{$tenantKey};
                            $assignmentTenant === null ? $duplicateQuery->whereNull($tenantKey) : $duplicateQuery->where($tenantKey, $assignmentTenant);
                        }
                        $duplicate = $duplicateQuery->first();
                        if ($duplicate) {
                            if (! $duplicate->expires_at || ! $assignment->expires_at) {
                                $duplicate->expires_at = null;
                            } elseif ($assignment->expires_at->gt($duplicate->expires_at)) {
                                $duplicate->expires_at = $assignment->expires_at;
                            }
                            $duplicate->save();
                            $assignment->delete();
                        } else {
                            $assignment->role_id = $replacement->getKey();
                            $assignment->save();
                        }
                        $reassignedCount++;
                    }
                });
            } else {
                $reassignedCount = 0;
            }
            $role->permissions()->detach();
            $role->delete();

            return [$role, $before, $reassignTo ? ['reassigned_to' => is_object($reassignTo) ? $reassignTo->getKey() : $reassignTo, 'assignments_reassigned' => $reassignedCount] : null];
        });
        RoleDeleted::dispatch($result[0], $actor, $reason, $result[1], $result[2]);
    }
}
