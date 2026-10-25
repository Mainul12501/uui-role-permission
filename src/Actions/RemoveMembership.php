<?php

namespace Mainul\UuiRolePermission\Actions;

use Illuminate\Support\Facades\DB;
use Mainul\UuiRolePermission\Events\MembershipRemoved;
use Mainul\UuiRolePermission\Exceptions\AccessControlException;
use Mainul\UuiRolePermission\Guards\EscalationGuard;
use Mainul\UuiRolePermission\Support\Config;

class RemoveMembership
{
    public function handle($tenant, $user, $actor = null, ?string $reason = null): void
    {
        if ($actor && config('uui-role-permission.guardrails.prevent_self_modification', true) && (string) $actor->getKey() === (string) $user->getKey()) {
            throw new AccessControlException('Users cannot remove their own tenant membership.', AccessControlException::SELF_MODIFICATION);
        }
        app(EscalationGuard::class)->assertPermission($actor, 'users.manage', $tenant);
        $model = Config::model('membership');
        $key = Config::tenantKey();
        $result = DB::transaction(function () use ($model, $key, $tenant, $user) {
            $membership = $model::where($key, $tenant->getKey())->where('user_id', $user->getKey())->lockForUpdate()->firstOrFail();
            $before = $membership->getAttributes();
            $membership->delete();

            return [$membership, $before];
        });
        MembershipRemoved::dispatch($result[0], $actor, $reason, $result[1]);
    }
}
