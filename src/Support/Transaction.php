<?php

namespace Mainul\UuiRolePermission\Support;

use Illuminate\Support\Facades\DB;
use Throwable;

class Transaction
{
    public static function run(callable $callback)
    {
        DB::beginTransaction();
        try {
            $result = $callback();
            DB::commit();

            return $result;
        } catch (Throwable $exception) {
            DB::rollBack();
            throw $exception;
        }
    }
}
