<?php

namespace Mainul\UuiRolePermission\Listeners;

use Mainul\UuiRolePermission\Events\AccessControlChanged;
use Mainul\UuiRolePermission\Jobs\NotifyRoleAssigned;
use Mainul\UuiRolePermission\Jobs\WarmUserPermissions;
use Mainul\UuiRolePermission\Support\Config;

class WarmPermissionsOnAccessChange
{
    public function handle(AccessControlChanged $event): void
    {
        if (in_array($event->action, ['role.assigned', 'role.revoked', 'membership.changed', 'membership.removed'], true)) {
            if (is_object($event->subject) && ! empty($event->subject->user_id)) {
                WarmUserPermissions::dispatch($event->subject->user_id);
            }
            if ($event->action === 'role.assigned' && Config::get('notifications.role_assigned') && is_object($event->subject)) {
                NotifyRoleAssigned::dispatch($event->subject->getKey());
            }

            return;
        }
        if (! is_object($event->subject) || ! in_array($event->action, ['role.permissions_synced', 'role.updated', 'role.deleted'], true)) {
            return;
        }
        $event->subject->assignments()->select('user_id')->distinct()->chunkById(200, function ($assignments): void {
            foreach ($assignments as $assignment) {
                WarmUserPermissions::dispatch($assignment->user_id);
            }
        }, 'user_id');
    }
}
