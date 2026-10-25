<?php

namespace Mainul\UuiRolePermission\Exceptions;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class UnauthorizedException extends HttpException
{
    public function __construct()
    {
        parent::__construct(403, 'You are not authorized to perform this action.');
    }

    public function render(): JsonResponse
    {
        return response()->json(['success' => false, 'code' => 'FORBIDDEN', 'message' => $this->getMessage()], 403);
    }
}
