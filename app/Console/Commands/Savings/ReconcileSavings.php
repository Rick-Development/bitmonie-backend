<?php

namespace App\Console\Commands\Savings;

use App\Services\Savings\SavingsMaintenance;
use Illuminate\Console\Command;

class ReconcileSavings extends Command
{
    protected $signature = 'savings:reconcile';

    protected $description = 'Verify savings balances against the ledger and report stuck executions (read-only)';

    public function handle(SavingsMaintenance $maintenance): int
    {
        $findings = $maintenance->reconcile();

        if ($findings === []) {
            $this->info('Savings ledger reconciled: no discrepancies.');

            return self::SUCCESS;
        }

        foreach ($findings as $f) {
            $this->error(json_encode($f));
        }

        return self::FAILURE;
    }
}
