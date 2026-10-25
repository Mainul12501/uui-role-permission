# mainul/uui-role-permission

Tenant-aware, UUID-first roles and permissions for Laravel 10–13 and PHP 8.1–8.4.

The package provides global and tenant roles, expiring assignments, permission caching, Laravel Gate and middleware integration, privilege-escalation guardrails, audit records, optional Sanctum session revocation, scheduled maintenance, and an optional management API.

## Installation

```sh
composer require mainul/uui-role-permission
php artisan uui-rp:install
```

Add `Mainul\UuiRolePermission\Concerns\HasRoles` to your authenticatable user model. Laravel package discovery registers the provider and `RolePermission` facade automatically.

For an existing RBAC database, publish and edit the configuration first, disable automatic migrations until the table mapping is correct, then inspect adoption without modifying data:

```sh
php artisan vendor:publish --tag=uui-role-permission-config
php artisan uui-rp:adopt --dry-run
php artisan uui-rp:adopt --apply
```

Back up production data before applying adoption migrations.

## Configuration

`config/uui-role-permission.php` controls:

- UUID, ULID, or integer package/user/tenant keys;
- replaceable model and table names;
- tenant model, route parameter, activity columns, and membership enforcement;
- permission registry and default roles;
- cache store, TTL, audit driver, queue, session revoker, and schedules;
- middleware aliases and the optional management API.

Set `tenancy.enabled` to `false` for global-only applications. When tenancy is enabled, tenant role assignments can be constrained to an active membership and active tenant.

Register permissions by stable code:

```php
'permissions' => [
    'orders.view' => [
        'name' => 'View orders',
        'description' => 'Read orders in an authorized tenant.',
        'group' => 'Orders',
        'scope' => 'TENANT', // GLOBAL, TENANT, or ANY
        'sort_order' => 10,
    ],
],
```

Synchronize the registry during deployment:

```sh
php artisan uui-rp:sync
```

Synchronization is idempotent. Removed codes are deprecated instead of deleted. `--dry-run` previews changes; `--reset-defaults` explicitly resets configured default-role permissions.

## Authorization

```php
$user->hasPermission('orders.view', $organization);
$user->hasAnyPermission(['orders.view', 'orders.manage'], $organization);
$user->hasRole('WAREHOUSE_MANAGER', $organization);
$user->isSuperAdmin();
$user->effectivePermissions();
```

Middleware aliases:

```php
Route::get('/orders', $handler)->middleware('permission:orders.view,organization');
Route::get('/operations', $handler)->middleware('permission.any:orders.view|orders.manage,organization');
Route::get('/warehouse', $handler)->middleware('role:WAREHOUSE_MANAGER,organization');
```

Registered permission codes work with `$user->can(...)`. Blade directives `@permission` and `@role` are also available. Unknown permissions fail closed. Resolver failures are logged and fail closed.

The resolver loads a user's grants in one query, memoizes them for the request, and stores them under a versioned cache key. Every package action advances the global version, so changes are visible on the next check without cache-tag support.

## Safe writes

Use package actions or the trait's `assignRole()` and `revokeRole()` methods. Actions run in transactions and enforce:

- no permission or role escalation;
- no self-assignment or modification of a role the actor holds;
- protected and locked role rules;
- last-super-admin protection;
- tenant, membership, and permission-scope compatibility;
- role-in-use reassignment and optimistic role-update concurrency;
- required reasons for configured sensitive roles.

Successful writes dispatch typed events derived from `AccessControlChanged`, invalidate permission caches, and use the configured `AuditRecorder`. Set `audit.driver` to `database`, `null`, or a custom recorder class. Session removal uses Sanctum automatically when installed or a custom `SessionRevoker`.

## Optional management API

Set `api.enabled=true`. Routes use the configured API middleware plus permission middleware for reads and writes. The API includes permission and role listing, role CRUD and permission sync, user role assignments, tenant roles and memberships, and access-control audit records. Write endpoints are rate limited.

Defaults assume these registry codes exist:

- `permissions.view`, `roles.view`, `roles.manage`
- `users.view`, `users.manage`, `users.assign_roles`
- `audit.view`

Override `api_permissions` when your application uses different codes. The API does not create users; user lifecycle remains application-owned. See `docs/openapi.yaml`.

## Commands and operations

```text
uui-rp:install
uui-rp:adopt --dry-run|--apply
uui-rp:sync [--dry-run] [--reset-defaults]
uui-rp:assign email role [tenant] [--expires=] [--actor=] [--force]
uui-rp:super-admin email
uui-rp:prune
uui-rp:cache flush|warm [--user=]
uui-rp:doctor
```

Run Laravel's scheduler and a queue worker when the corresponding features are enabled:

```cron
* * * * * cd /path/to/application && php artisan schedule:run >> /dev/null 2>&1
```

```sh
php artisan queue:work --queue=default
```

Expired assignments and audit retention are scheduled by the service provider. Set a schedule entry to `null` to disable it.

## Extending

Container contracts are available for `PermissionRegistry`, `PermissionResolver`, `AuditRecorder`, `SessionRevoker`, `TenantResolver`, and `UserActivityChecker`. Models can be replaced through configuration. `Mainul\UuiRolePermission\Testing\InteractsWithRolePermissions` provides test setup and permission assertions for host applications.

## Development and support

Source and issues: [github.com/Mainul12501/uui-role-permission](https://github.com/Mainul12501/uui-role-permission)

Run `composer test`, `composer format:test`, and `composer analyse` before release. The CI matrix tests Laravel 10–13 against SQLite, MySQL 8, and PostgreSQL 15. This package is released under the MIT license.
