<?php

namespace Mainul\UuiRolePermission\Tests;

use Mainul\UuiRolePermission\Tests\Fixtures\Tenant;

abstract class TenantTestCase extends TestCase
{
    protected bool $tenancyEnabled = true;

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);
        $app['config']->set('uui-role-permission.tenancy.model', Tenant::class);
    }
}
