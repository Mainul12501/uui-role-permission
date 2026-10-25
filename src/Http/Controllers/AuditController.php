<?php

namespace Mainul\UuiRolePermission\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Mainul\UuiRolePermission\Support\Config;

class AuditController
{
    public function index(Request $request)
    {
        abort_unless(config('uui-role-permission.audit.driver') === 'database', 404);
        $query = DB::table(Config::table('audit_logs'))->orderByDesc('created_at');
        if ($request->filled('tenant_id')) {
            $query->where('tenant_id', $request->input('tenant_id'));
        }
        if ($request->filled('action')) {
            $query->where('action', $request->input('action'));
        }
        if ($request->filled('subject_id')) {
            $query->where('subject_id', $request->input('subject_id'));
        }

        return response()->json(['data' => $query->paginate(min((int) $request->input('per_page', 50), 100))]);
    }
}
