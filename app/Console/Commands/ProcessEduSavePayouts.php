<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\EduSave;
use App\Models\UserWallet;
use App\Models\SavingsTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ProcessEduSavePayouts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'edusave:process-payouts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process termly/yearly payouts for EduSave plans';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Starting EduSave Payout Processing...');

        $plans = EduSave::where('status', 'active')
            ->whereDate('next_payout_date', '<=', now())
            ->get();

        $count = 0;

        foreach ($plans as $plan) {
            DB::beginTransaction();
            try {
                // 1. Credit Wallet
                $wallet = UserWallet::where('user_id', $plan->user_id)->where('currency_code', 'NGN')->first();
                if ($wallet) {
                    $wallet->balance += $plan->amount;
                    $wallet->save();

                    // 2. Log Transaction
                    SavingsTransaction::create([
                        'user_id' => $plan->user_id,
                        'savingsable_id' => $plan->id,
                        'savingsable_type' => EduSave::class,
                        'amount' => $plan->amount,
                        'balance_after' => 0, // N/A
                        'type' => 'payout', // Custom type or reuse 'interest'
                        'status' => 'success',
                        'source' => 'edusave_payout',
                        'narration' => 'EduSave Payout: ' . $plan->title
                    ]);
                }

                // 3. Calculate Next Date
                $nextDate = Carbon::parse($plan->next_payout_date);
                if ($plan->period === 'yearly') {
                    $nextDate->addYear();
                } else {
                    $nextDate->addMonths(4);
                }

                // 4. Check Graduation
                if ($nextDate->gt($plan->graduation_date)) {
                    $plan->status = 'completed';
                    $this->info("Plan {$plan->id} completed (Graduated).");
                } else {
                    $plan->next_payout_date = $nextDate;
                }
                
                $plan->save();
                DB::commit();
                $count++;

            } catch (\Exception $e) {
                DB::rollBack();
                $this->error("Error processing plan {$plan->id}: " . $e->getMessage());
            }
        }

        $this->info("Processed {$count} EduSave payouts.");
        return Command::SUCCESS;
    }
}
