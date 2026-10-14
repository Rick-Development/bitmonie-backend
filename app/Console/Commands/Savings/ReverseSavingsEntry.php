<?php

namespace App\Console\Commands\Savings;

use App\Models\SavingsEntry;
use App\Services\Savings\SavingsException;
use App\Services\Savings\SavingsLedger;
use Illuminate\Console\Command;

class ReverseSavingsEntry extends Command
{
    protected $signature = 'savings:reverse {reference : Entry reference} {reason : Why it is being reversed}';

    protected $description = 'Operator tool: reverse a completed savings entry (both legs for transfers)';

    public function handle(SavingsLedger $ledger): int
    {
        $entry = SavingsEntry::where('reference', $this->argument('reference'))->first();
        if (! $entry) {
            $this->error('Entry not found.');

            return self::FAILURE;
        }

        try {
            $reversals = $ledger->reverse($entry->id, (string) $this->argument('reason'), 'operator:cli');
        } catch (SavingsException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        foreach ($reversals as $r) {
            $this->info("Reversal posted: {$r->reference}");
        }

        return self::SUCCESS;
    }
}
