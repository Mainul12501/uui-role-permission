<?php

namespace Mainul\UuiRolePermission\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Mainul\UuiRolePermission\Contracts\TenantResolver;
use Mainul\UuiRolePermission\Exceptions\UnauthorizedException;
use Symfony\Component\HttpFoundation\Response;

class RequireRole
{
    public function __construct(private TenantResolver $tenants) {}

    public function handle(Request $request, Closure $next, string $role, string $tenantParameter = 'organization'): Response
    {
        $tenant = $this->tenants->resolve($request, $tenantParameter);
        $user = $request->user();
        if ($user && method_exists($user, 'hasRole') && call_user_func([$user, 'hasRole'], $role, $tenant)) {
            return $next($request);
        }
        throw new UnauthorizedException;
    }
}
