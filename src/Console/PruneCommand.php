<?php

namespace Mainul\UuiRolePermission\Console;

use Illuminate\Console\Command;
use Mainul\UuiRolePermission\Jobs\PruneExpiredAssignments;

class PruneCommand extends Command
{
    protected $signature = 'uui-rp:prune';

    protected $description = 'Remove expired role assignments.';

    public function handle(): int
    {
        PruneExpiredAssignments::dispatchSync();
        $this->info('Expired assignments pruned.');

        return self::SUCCESS;
    }
}
