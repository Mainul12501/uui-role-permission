<?php

use App\Models\Organization;
use App\Models\User;
use Mainul\UuiRolePermission\Http\Controllers\AssignmentController;
use Mainul\UuiRolePermission\Http\Controllers\AuditController;
use Mainul\UuiRolePermission\Http\Controllers\MembershipController;
use Mainul\UuiRolePermission\Http\Controllers\PermissionController;
use Mainul\UuiRolePermission\Http\Controllers\RoleController;
use Mainul\UuiRolePermission\Http\Resources\RoleResource;
use Mainul\UuiRolePermission\Models\Membership;
use Mainul\UuiRolePermission\Models\Permission;
use Mainul\UuiRolePermission\Models\Role;
use Mainul\UuiRolePermission\Models\RoleAssignment;
use Mainul\UuiRolePermission\Support\ConfiguredUserActivityChecker;
use Mainul\UuiRolePermission\Support\RouteTenantResolver;

return [
    'key_type' => 'uuid',
    'user_key_type' => 'uuid',
    'models' => [
        'user' => User::class,
        'role' => Role::class,
        'permission' => Permission::class,
        'assignment' => RoleAssignment::class,
        'membership' => Membership::class,
    ],
    'tenancy' => [
        'enabled' => true,
        'model' => Organization::class,
        'table' => 'organizations',
        'primary_key' => 'id',
        'key_type' => 'uuid',
        'route_parameter' => 'organization',
        'resolver' => RouteTenantResolver::class,
        'foreign_key' => 'organization_id',
        'active_column' => 'status',
        'active_values' => ['ACTIVE'],
        'require_membership' => true,
        'membership_active_values' => ['ACTIVE'],
    ],
    'tables' => [
        'users' => 'users',
        'roles' => 'roles',
        'permissions' => 'permissions',
        'role_permissions' => 'role_permissions',
        'assignments' => 'user_role_assignments',
        'memberships' => 'organization_user',
        'audit_logs' => 'rp_audit_logs',
    ],
    'run_migrations' => true,
    'super_admin_role' => 'SUPER_ADMIN',
    'protected_roles' => ['SUPER_ADMIN', 'SYSTEM_ADMIN'],
    'register_gate' => true,
    'middleware_aliases' => ['permission' => 'permission', 'role' => 'role', 'any_permission' => 'permission.any'],
    'api_permissions' => [
        'permissions.read' => 'permissions.view',
        'roles.read' => 'roles.view',
        'roles.write' => 'roles.manage',
        'assignments.read' => 'users.view',
        'assignments.write' => 'users.assign_roles',
        'memberships.read' => 'users.view',
        'memberships.write' => 'users.manage',
        'audit.read' => 'audit.view',
    ],
    'user_active' => null,
    'user_activity_checker' => ConfiguredUserActivityChecker::class,
    'permissions' => [],
    'registry_class' => null,
    'default_roles' => [],
    'cache' => ['store' => null, 'ttl' => 600, 'prefix' => 'uui-rp'],
    'queue' => ['connection' => null, 'queue' => 'default', 'audit_async' => true],
    'audit' => ['driver' => 'database', 'retention_days' => 365],
    'sessions' => ['revoker' => 'auto'],
    'notifications' => ['role_assigned' => null],
    'schedule' => ['prune_expired_assignments' => '*/15 * * * *', 'prune_audit_logs' => '0 3 * * *', 'warm_cache' => null],
    'api' => ['enabled' => false, 'prefix' => 'api/v1', 'middleware' => ['api', 'auth:sanctum'], 'name' => 'api.v1.', 'rate_limit' => '60,1'],
    'http' => [
        'controllers' => [
            'permission' => PermissionController::class,
            'role' => RoleController::class,
            'assignment' => AssignmentController::class,
            'membership' => MembershipController::class,
            'audit' => AuditController::class,
        ],
        'resources' => ['role' => RoleResource::class],
    ],
    'guardrails' => ['prevent_escalation' => true, 'prevent_self_modification' => true, 'protect_last_super_admin' => true, 'require_reason_for' => ['SUPER_ADMIN', 'SYSTEM_ADMIN']],
];
