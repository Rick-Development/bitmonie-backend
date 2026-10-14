<?php

namespace App\Console\Commands\Savings;

use App\Services\Savings\SavingsMaintenance;
use Illuminate\Console\Command;

class ExpireSavingsTargets extends Command
{
    protected $signature = 'savings:expire-targets';

    protected $description = 'Expire targets whose deadline passed without reaching the goal';

    public function handle(SavingsMaintenance $maintenance): int
    {
        $this->info('Targets updated: ' . $maintenance->expireTargets());

        return self::SUCCESS;
    }
}
