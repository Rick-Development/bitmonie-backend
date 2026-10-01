<?php

namespace App\Console\Commands;

use App\Models\AutosavePlan;
use App\Services\AutosaveService;
use Illuminate\Console\Command;

class ProcessAutosaveScheduledDeductions extends Command
{
    protected $signature = 'autosave:process-scheduled {--limit=100}';

    protected $description = 'Process due scheduled AutoSave deductions.';

    public function handle(AutosaveService $autosaveService): int
    {
        $processed = 0;
        $limit = (int) $this->option('limit');

        AutosavePlan::active()
            ->whereIn('mode', [AutosavePlan::MODE_SCHEDULED, AutosavePlan::MODE_BOTH])
            ->whereNotNull('next_due_at')
            ->where('next_due_at', '<=', now())
            ->orderBy('next_due_at')
            ->limit($limit)
            ->get()
            ->each(function (AutosavePlan $plan) use ($autosaveService, &$processed) {
                $autosaveService->processScheduledPlan($plan);
                $processed++;
            });

        $this->info(json_encode(['processed' => $processed]));

        return self::SUCCESS;
    }
}
