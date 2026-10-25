<?php

namespace Mainul\UuiRolePermission\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Mainul\UuiRolePermission\Contracts\TenantResolver;
use Mainul\UuiRolePermission\Exceptions\UnauthorizedException;
use Symfony\Component\HttpFoundation\Response;

class RequirePermission
{
    public function __construct(private TenantResolver $tenants) {}

    public function handle(Request $request, Closure $next, string $permissions, string $tenantParameter = 'organization'): Response
    {
        $tenant = $this->tenants->resolve($request, $tenantParameter);
        $codes = explode('|', $permissions);

        $user = $request->user();
        if ($user && method_exists($user, 'hasAnyPermission') && call_user_func([$user, 'hasAnyPermission'], $codes, $tenant)) {
            return $next($request);
        }

        throw new UnauthorizedException;
    }
}
