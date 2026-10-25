<?php

namespace Mainul\UuiRolePermission\Support;

use Illuminate\Http\Request;
use Mainul\UuiRolePermission\Contracts\TenantResolver;

class RouteTenantResolver implements TenantResolver
{
    public function resolve(Request $request, ?string $parameter = null)
    {
        $value = $request->route($parameter ?: (string) Config::get('tenancy.route_parameter', 'organization'));

        return is_object($value) && method_exists($value, 'getKey') ? $value->getKey() : $value;
    }
}
