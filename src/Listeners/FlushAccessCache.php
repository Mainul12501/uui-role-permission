<?php

namespace Mainul\UuiRolePermission\Listeners;

use Mainul\UuiRolePermission\Contracts\PermissionResolver;
use Mainul\UuiRolePermission\Events\AccessControlChanged;
use Mainul\UuiRolePermission\Support\AccessCache;

class FlushAccessCache
{
    public function handle(AccessControlChanged $event): void
    {
        app(PermissionResolver::class)->flush();
        app(AccessCache::class)->bump();
    }
}
