<?php

use Illuminate\Support\Facades\Route;

$api = config('uui-role-permission.api');
$permission = config('uui-role-permission.middleware_aliases.permission', 'permission');
$permissions = config('uui-role-permission.api_permissions');
$writeThrottle = 'throttle:'.($api['rate_limit'] ?? '60,1');
$controllers = config('uui-role-permission.http.controllers');
Route::prefix($api['prefix'])->middleware($api['middleware'])->name($api['name'])->group(function () use ($permission, $permissions, $writeThrottle, $controllers): void {
    Route::get('/permissions', [$controllers['permission'], 'index'])->middleware($permission.':'.$permissions['permissions.read'])->name('permissions.index');
    Route::get('/roles', [$controllers['role'], 'index'])->middleware($permission.':'.$permissions['roles.read'])->name('roles.index');
    Route::post('/roles', [$controllers['role'], 'store'])->middleware([$permission.':'.$permissions['roles.write'], $writeThrottle])->name('roles.store');
    Route::get('/roles/{role}', [$controllers['role'], 'show'])->middleware($permission.':'.$permissions['roles.read'])->name('roles.show');
    Route::patch('/roles/{role}', [$controllers['role'], 'update'])->middleware([$permission.':'.$permissions['roles.write'], $writeThrottle])->name('roles.update');
    Route::delete('/roles/{role}', [$controllers['role'], 'destroy'])->middleware([$permission.':'.$permissions['roles.write'], $writeThrottle])->name('roles.destroy');
    Route::put('/roles/{role}/permissions', [$controllers['role'], 'permissions'])->middleware([$permission.':'.$permissions['roles.write'], $writeThrottle])->name('roles.permissions');
    Route::get('/users/{user}/role-assignments', [$controllers['assignment'], 'index'])->middleware($permission.':'.$permissions['assignments.read'])->name('users.assignments.index');
    Route::post('/users/{user}/role-assignments', [$controllers['assignment'], 'store'])->middleware([$permission.':'.$permissions['assignments.write'], $writeThrottle])->name('users.assignments.store');
    Route::delete('/users/{user}/role-assignments/{assignment}', [$controllers['assignment'], 'destroy'])->middleware([$permission.':'.$permissions['assignments.write'], $writeThrottle])->name('users.assignments.destroy');
    Route::get('/access-control/audit', [$controllers['audit'], 'index'])->middleware($permission.':'.$permissions['audit.read'])->name('access-control.audit');
    if (config('uui-role-permission.tenancy.enabled', true)) {
        Route::get('/tenants/{tenant}/roles', [$controllers['role'], 'tenantIndex'])->middleware($permission.':'.$permissions['roles.read'].',tenant')->name('tenants.roles.index');
        Route::post('/tenants/{tenant}/roles', [$controllers['role'], 'store'])->middleware([$permission.':'.$permissions['roles.write'].',tenant', $writeThrottle])->name('tenants.roles.store');
        Route::get('/tenants/{tenant}/roles/{role}', [$controllers['role'], 'tenantShow'])->middleware($permission.':'.$permissions['roles.read'].',tenant')->name('tenants.roles.show');
        Route::patch('/tenants/{tenant}/roles/{role}', [$controllers['role'], 'tenantUpdate'])->middleware([$permission.':'.$permissions['roles.write'].',tenant', $writeThrottle])->name('tenants.roles.update');
        Route::delete('/tenants/{tenant}/roles/{role}', [$controllers['role'], 'tenantDestroy'])->middleware([$permission.':'.$permissions['roles.write'].',tenant', $writeThrottle])->name('tenants.roles.destroy');
        Route::put('/tenants/{tenant}/roles/{role}/permissions', [$controllers['role'], 'tenantPermissions'])->middleware([$permission.':'.$permissions['roles.write'].',tenant', $writeThrottle])->name('tenants.roles.permissions');
        Route::get('/tenants/{tenant}/members', [$controllers['membership'], 'index'])->middleware($permission.':'.$permissions['memberships.read'].',tenant')->name('tenants.members.index');
        Route::post('/tenants/{tenant}/members', [$controllers['membership'], 'store'])->middleware([$permission.':'.$permissions['memberships.write'].',tenant', $writeThrottle])->name('tenants.members.store');
        Route::delete('/tenants/{tenant}/members/{user}', [$controllers['membership'], 'destroy'])->middleware([$permission.':'.$permissions['memberships.write'].',tenant', $writeThrottle])->name('tenants.members.destroy');
    }
});
