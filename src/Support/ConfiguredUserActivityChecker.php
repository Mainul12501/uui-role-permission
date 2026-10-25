<?php

namespace Mainul\UuiRolePermission\Support;

use Mainul\UuiRolePermission\Contracts\UserActivityChecker;

class ConfiguredUserActivityChecker implements UserActivityChecker
{
    public function active($user): bool
    {
        $checker = Config::get('user_active');
        if ($checker === null) {
            return true;
        }
        if (is_string($checker) && class_exists($checker)) {
            $checker = app($checker);
        }

        return is_callable($checker) && (bool) call_user_func($checker, $user);
    }
}
