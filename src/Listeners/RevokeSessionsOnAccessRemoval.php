<?php

namespace Mainul\UuiRolePermission\Listeners;

use Mainul\UuiRolePermission\Events\AccessControlChanged;
use Mainul\UuiRolePermission\Jobs\RevokeUserSessions;

class RevokeSessionsOnAccessRemoval
{
    public function handle(AccessControlChanged $event): void
    {
        if (! is_object($event->subject)) {
            return;
        }
        $membershipDeactivated = $event->action === 'membership.changed'
            && ! in_array($event->subject->status, config('uui-role-permission.tenancy.membership_active_values', ['ACTIVE']), true);
        if ((in_array($event->action, ['role.revoked', 'membership.removed'], true) || $membershipDeactivated) && ! empty($event->subject->user_id)) {
            RevokeUserSessions::dispatch($event->subject->user_id);
        } elseif ($event->action === 'user.locked' && method_exists($event->subject, 'getKey')) {
            RevokeUserSessions::dispatch($event->subject->getKey());
        }
    }
}
