<?php

namespace Mainul\UuiRolePermission\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AccessControlChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public string $action, public $subject, public $actor = null, public ?string $reason = null, public ?array $before = null, public ?array $after = null) {}
}
