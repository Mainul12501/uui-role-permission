<?php

namespace Mainul\UuiRolePermission\Actions;

use Mainul\UuiRolePermission\Events\MembershipChanged;
use Mainul\UuiRolePermission\Guards\EscalationGuard;
use Mainul\UuiRolePermission\Support\Config;
use Mainul\UuiRolePermission\Support\Transaction;

class ChangeMembership
{
    public function handle($tenant, $user, string $status = 'ACTIVE', $actor = null, ?string $reason = null)
    {
        app(EscalationGuard::class)->assertPermission($actor, 'users.manage', $tenant);
        $result = Transaction::run(function () use ($tenant, $user, $status): array {
            $model = Config::model('membership');
            $key = Config::tenantKey();
            $membership = $model::where($key, $tenant->getKey())->where('user_id', $user->getKey())->first();
            $before = $membership ? $membership->getAttributes() : null;
            $membership = $model::updateOrCreate([$key => $tenant->getKey(), 'user_id' => $user->getKey()], ['status' => $status]);

            return [$membership, $before];
        });
        MembershipChanged::dispatch($result[0], $actor, $reason, $result[1]);

        return $result[0];
    }
}
