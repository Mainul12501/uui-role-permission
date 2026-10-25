<?php

namespace Mainul\UuiRolePermission\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Mainul\UuiRolePermission\Actions\CreateRole;
use Mainul\UuiRolePermission\Actions\DeleteRole;
use Mainul\UuiRolePermission\Actions\SyncRolePermissions;
use Mainul\UuiRolePermission\Actions\UpdateRole;
use Mainul\UuiRolePermission\Http\Requests\DeleteRoleRequest;
use Mainul\UuiRolePermission\Http\Requests\StoreRoleRequest;
use Mainul\UuiRolePermission\Http\Requests\SyncRolePermissionsRequest;
use Mainul\UuiRolePermission\Http\Requests\UpdateRoleRequest;
use Mainul\UuiRolePermission\Http\Resources\RoleResource;
use Mainul\UuiRolePermission\Support\Config;

class RoleController
{
    public function index(Request $request)
    {
        $query = Config::model('role')::with('permissions')->withCount('assignments')->orderBy('name');
        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->input('search').'%');
        }
        if ($request->filled('scope')) {
            $query->where('assignable_scope', $request->input('scope'));
        }

        return response()->json(['data' => $query->paginate(min((int) $request->input('per_page', 50), 100))]);
    }

    public function tenantIndex(Request $request, $tenant)
    {
        $tenantModel = Config::get('tenancy.model');
        $tenant = $tenantModel::findOrFail($tenant);
        $query = Config::model('role')::with('permissions')->withCount('assignments')
            ->where(function ($builder) use ($tenant): void {
                $builder->where(Config::tenantKey(), $tenant->getKey())
                    ->orWhere(function ($global): void {
                        $global->whereNull(Config::tenantKey())->whereIn('assignable_scope', ['TENANT', 'ANY']);
                    });
            })->orderBy('name');
        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->input('search').'%');
        }

        return response()->json(['data' => $query->paginate(min((int) $request->input('per_page', 50), 100))]);
    }

    public function store(StoreRoleRequest $request, CreateRole $create)
    {
        $data = $request->validated();
        unset($data['reason']);
        $tenantRoute = $request->route('tenant', $request->route('organization'));
        if ($tenantRoute && ! is_object($tenantRoute)) {
            $tenantClass = Config::get('tenancy.model');
            $tenantRoute = $tenantClass::findOrFail($tenantRoute);
        }
        if (Config::tenancyEnabled()) {
            $data[Config::tenantKey()] = $tenantRoute ? $tenantRoute->getKey() : null;
        }
        $role = $create->handle($data, $request->user(), $request->input('reason'));

        return $this->resource($role->load('permissions'))->response()->setStatusCode(201);
    }

    public function show($role)
    {
        return response()->json(['data' => Config::model('role')::with('permissions')->findOrFail($role)]);
    }

    public function tenantShow($tenant, $role)
    {
        $tenantModel = Config::get('tenancy.model');
        $tenant = $tenantModel::findOrFail($tenant);
        $roleModel = Config::model('role');
        $role = $roleModel::whereKey($role)->where(function ($builder) use ($tenant): void {
            $builder->where(Config::tenantKey(), $tenant->getKey())
                ->orWhere(function ($global): void {
                    $global->whereNull(Config::tenantKey())->whereIn('assignable_scope', ['TENANT', 'ANY']);
                });
        })->firstOrFail();

        return response()->json(['data' => $role->load('permissions')]);
    }

    public function update(UpdateRoleRequest $request, $role, UpdateRole $update)
    {
        $role = Config::model('role')::findOrFail($role);
        $data = $request->validated();
        unset($data['reason']);
        $role = $update->handle($role, $data, $request->user(), $request->input('reason'), $request->input('expected_updated_at'));

        return $this->resource($role->load('permissions'))->response();
    }

    public function tenantUpdate(UpdateRoleRequest $request, $tenant, $role, UpdateRole $update)
    {
        $this->tenantRole($tenant, $role);

        return $this->update($request, $role, $update);
    }

    public function destroy(DeleteRoleRequest $request, $role, DeleteRole $delete)
    {
        $role = Config::model('role')::findOrFail($role);
        $data = $request->validated();
        $replacement = null;
        if (! empty($data['reassign_to'])) {
            $roleModel = Config::model('role');
            $replacement = $roleModel::findOrFail($data['reassign_to']);
        }
        $delete->handle($role, $request->user(), $data['reason'] ?? null, $replacement);

        return response()->noContent();
    }

    public function tenantDestroy(DeleteRoleRequest $request, $tenant, $role, DeleteRole $delete)
    {
        $this->tenantRole($tenant, $role);

        return $this->destroy($request, $role, $delete);
    }

    public function permissions(SyncRolePermissionsRequest $request, $role, SyncRolePermissions $sync)
    {
        $role = Config::model('role')::findOrFail($role);
        $data = $request->validated();
        $role = $sync->handle($role, $data['permissions'], $request->user(), $request->input('reason'));

        return $this->resource($role)->response();
    }

    public function tenantPermissions(SyncRolePermissionsRequest $request, $tenant, $role, SyncRolePermissions $sync)
    {
        $this->tenantRole($tenant, $role);

        return $this->permissions($request, $role, $sync);
    }

    private function tenantRole($tenant, $role)
    {
        $tenantModel = Config::get('tenancy.model');
        $tenant = $tenantModel::findOrFail($tenant);
        $roleModel = Config::model('role');

        return $roleModel::where(Config::tenantKey(), $tenant->getKey())->findOrFail($role);
    }

    private function resource($role): JsonResource
    {
        $resource = Config::get('http.resources.role', RoleResource::class);

        return new $resource($role);
    }
}
