<?php

namespace Mainul\UuiRolePermission\Http\Controllers;

use Illuminate\Http\Request;
use Mainul\UuiRolePermission\Actions\AssignRole;
use Mainul\UuiRolePermission\Actions\RevokeRole;
use Mainul\UuiRolePermission\Http\Requests\RevokeAssignmentRequest;
use Mainul\UuiRolePermission\Http\Requests\StoreAssignmentRequest;
use Mainul\UuiRolePermission\Support\Config;

class AssignmentController
{
    public function index(Request $request, $user)
    {
        $userModel = Config::model('user');
        $user = $userModel::findOrFail($user);

        return response()->json(['data' => $user->roleAssignments()->with('role')->orderByDesc('created_at')->paginate(min((int) $request->input('per_page', 50), 100))]);
    }

    public function store(StoreAssignmentRequest $request, $user, AssignRole $assign)
    {
        $data = $request->validated();
        $userModel = Config::model('user');
        $user = $userModel::findOrFail($user);
        $tenant = null;
        if (! empty($data['tenant_id'])) {
            $tenantModel = Config::get('tenancy.model');
            $tenant = $tenantModel::findOrFail($data['tenant_id']);
        }
        $roleModel = Config::model('role');
        $roleQuery = $roleModel::where('code', $data['role']);
        if ($tenant && Config::tenancyEnabled()) {
            $tenantKey = Config::tenantKey();
            $role = $roleQuery->where($tenantKey, $tenant->getKey())->first();
            if (! $role) {
                $role = $roleModel::where('code', $data['role'])->whereNull($tenantKey)->firstOrFail();
            }
        } else {
            $role = Config::tenancyEnabled() ? $roleQuery->whereNull(Config::tenantKey())->firstOrFail() : $roleQuery->firstOrFail();
        }

        return response()->json(['data' => $assign->handle($user, $role, $tenant, $request->user(), $data['expires_at'] ?? null, $data['reason'] ?? null)], 201);
    }

    public function destroy(RevokeAssignmentRequest $request, $user, $assignment, RevokeRole $revoke)
    {
        $assignmentModel = Config::model('assignment');
        $assignment = $assignmentModel::where('user_id', $user)->findOrFail($assignment);
        $data = $request->validated();
        $revoke->handle($assignment, $request->user(), $data['reason'] ?? null);

        return response()->noContent();
    }
}
