<?php

namespace Mainul\UuiRolePermission\Events;

class MembershipRemoved extends AccessControlChanged
{
    public function __construct($membership, $actor, ?string $reason, array $before)
    {
        parent::__construct('membership.removed', $membership, $actor, $reason, $before, null);
    }
}
