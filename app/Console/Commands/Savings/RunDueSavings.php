<?php

namespace App\Console\Commands\Savings;

use App\Services\Savings\SavingsExecutionProcessor;
use App\Services\Savings\SavingsScheduleService;
use Illuminate\Console\Command;

class RunDueSavings extends Command
{
    protected $signature = 'savings:run-due {--limit= : Max plans/executions per run}';

    protected $description = 'Queue due Auto Save / Target schedules and process pending scheduled contributions';

    public function handle(SavingsScheduleService $schedule, SavingsExecutionProcessor $processor): int
    {
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;

        $queued = $schedule->enqueueDue(null, $limit);
        $done = $processor->processDue(null, $limit);

        $this->info('Queued: ' . json_encode($queued));
        $this->info('Processed: ' . json_encode($done));

        return self::SUCCESS;
    }
}
