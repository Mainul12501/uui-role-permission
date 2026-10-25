<?php

namespace Mainul\UuiRolePermission\Events;

class MembershipChanged extends AccessControlChanged
{
    public function __construct($membership, $actor, ?string $reason, ?array $before = null)
    {
        parent::__construct('membership.changed', $membership, $actor, $reason, $before, $membership->getAttributes());
    }
}
