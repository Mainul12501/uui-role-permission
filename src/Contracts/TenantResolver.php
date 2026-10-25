<?php

namespace Mainul\UuiRolePermission\Contracts;

use Illuminate\Http\Request;

interface TenantResolver
{
    public function resolve(Request $request, ?string $parameter = null);
}
