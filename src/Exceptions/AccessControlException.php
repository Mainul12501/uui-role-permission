<?php

namespace Mainul\UuiRolePermission\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

class AccessControlException extends RuntimeException
{
    public const PROTECTED_ROLE = 'PROTECTED_ROLE';

    public const ESCALATION_DENIED = 'ESCALATION_DENIED';

    public const LAST_SUPER_ADMIN = 'LAST_SUPER_ADMIN';

    public const STALE_ROLE = 'STALE_ROLE';

    public const TENANT_MISMATCH = 'TENANT_MISMATCH';

    public const REASON_REQUIRED = 'REASON_REQUIRED';

    public const ROLE_IN_USE = 'ROLE_IN_USE';

    public const SELF_MODIFICATION = 'SELF_MODIFICATION';

    public const UNKNOWN_PERMISSION = 'UNKNOWN_PERMISSION';

    public function __construct(string $message, public string $codeName = 'ACCESS_CONTROL_ERROR')
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        $status = in_array($this->codeName, [self::ESCALATION_DENIED, self::SELF_MODIFICATION], true) ? 403
            : (in_array($this->codeName, [self::PROTECTED_ROLE, self::LAST_SUPER_ADMIN, self::STALE_ROLE, self::ROLE_IN_USE], true) ? 409 : 422);

        return response()->json(['success' => false, 'code' => $this->codeName, 'message' => $this->getMessage()], $status);
    }
}
