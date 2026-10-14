<?php

namespace App\Console\Commands\Savings;

use App\Services\Savings\SavingsMaintenance;
use Carbon\Carbon;
use Illuminate\Console\Command;

class AccrueFlexiInterest extends Command
{
    protected $signature = 'savings:flexi-interest {--date= : Day to accrue (YYYY-MM-DD, default: yesterday)}';

    protected $description = 'Credit idempotent daily Flexi Save interest';

    public function handle(SavingsMaintenance $maintenance): int
    {
        $tz = (string) config('savings.scheduler.default_timezone');
        $day = $this->option('date') ? Carbon::parse($this->option('date'), $tz) : Carbon::now($tz)->subDay();

        $this->info(json_encode($maintenance->accrueFlexiInterest($day)));

        return self::SUCCESS;
    }
}
