<?php

namespace App\Console\Commands;

use App\Models\EasyEarnPlan;
use App\Services\EasyEarnService;
use Illuminate\Console\Command;

class ProcessEasyEarnPlans extends Command
{
    protected $signature = 'easyearn:process {--limit=100}';

    protected $description = 'Process EasyEarn midpoint reminders and maturity state updates';

    public function handle(EasyEarnService $easyEarnService): int
    {
        $limit = (int) $this->option('limit');
        $midpoint = 0;
        $matured = 0;

        EasyEarnPlan::active()
            ->whereNull('midpoint_notified_at')
            ->whereNotNull('midpoint_notify_at')
            ->where('midpoint_notify_at', '<=', now())
            ->limit($limit)
            ->get()
            ->each(function (EasyEarnPlan $plan) use ($easyEarnService, &$midpoint) {
                $easyEarnService->sendMidpointReminder($plan);
                $midpoint++;
            });

        EasyEarnPlan::active()
            ->where('maturity_date', '<=', now())
            ->limit($limit)
            ->get()
            ->each(function (EasyEarnPlan $plan) use ($easyEarnService, &$matured) {
                $plan = $easyEarnService->markMatured($plan);
                $easyEarnService->withdraw($plan, true);
                $matured++;
            });

        $this->line(json_encode(['midpoint_notified' => $midpoint, 'matured' => $matured]));

        return self::SUCCESS;
    }
}
