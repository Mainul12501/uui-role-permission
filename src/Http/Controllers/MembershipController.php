<?php

namespace Mainul\UuiRolePermission\Http\Controllers;

use Illuminate\Http\Request;
use Mainul\UuiRolePermission\Actions\ChangeMembership;
use Mainul\UuiRolePermission\Actions\RemoveMembership;
use Mainul\UuiRolePermission\Http\Requests\RemoveMembershipRequest;
use Mainul\UuiRolePermission\Http\Requests\StoreMembershipRequest;
use Mainul\UuiRolePermission\Support\Config;

class MembershipController
{
    public function index(Request $request, $tenant)
    {
        $tenantClass = Config::get('tenancy.model');
        $tenant = $tenantClass::findOrFail($tenant);
        $membershipClass = Config::model('membership');
        $query = $membershipClass::with('user')->where(Config::tenantKey(), $tenant->getKey());
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('search')) {
            $userClass = Config::model('user');
            $query->whereIn('user_id', $userClass::query()->where('email', 'like', '%'.$request->input('search').'%')->select((new $userClass)->getKeyName()));
        }

        return response()->json(['data' => $query->orderBy('created_at')->paginate(min((int) $request->input('per_page', 50), 100))]);
    }

    public function store(StoreMembershipRequest $request, $tenant, ChangeMembership $change)
    {
        $data = $request->validated();
        $tenantClass = Config::get('tenancy.model');
        $tenant = $tenantClass::findOrFail($tenant);
        $userClass = Config::model('user');
        $user = $userClass::findOrFail($data['user_id']);
        $membership = $change->handle($tenant, $user, $data['status'] ?? 'ACTIVE', $request->user(), $data['reason'] ?? null);

        return response()->json(['data' => $membership], 201);
    }

    public function destroy(RemoveMembershipRequest $request, $tenant, $user, RemoveMembership $remove)
    {
        $tenantClass = Config::get('tenancy.model');
        $tenant = $tenantClass::findOrFail($tenant);
        $userClass = Config::model('user');
        $userModel = $userClass::findOrFail($user);
        $data = $request->validated();
        $remove->handle($tenant, $userModel, $request->user(), $data['reason'] ?? null);

        return response()->noContent();
    }
}
