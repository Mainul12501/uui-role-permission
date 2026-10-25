<?php

namespace Mainul\UuiRolePermission\Http\Controllers;

use Illuminate\Http\Request;
use Mainul\UuiRolePermission\Support\Config;

class PermissionController
{
    public function index(Request $request)
    {
        $query = Config::model('permission')::query()->orderBy('sort_order')->orderBy('code');
        if ($request->filled('group')) {
            $query->where('group', $request->input('group'));
        }
        if ($request->filled('search')) {
            $query->where('code', 'like', '%'.$request->input('search').'%');
        }

        return response()->json(['data' => $query->paginate(min((int) $request->input('per_page', 50), 100))]);
    }
}
