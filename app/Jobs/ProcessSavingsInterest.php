<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\Savings;
use App\Models\Interest;
use App\Models\UserWallet;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Carbon;
use App\Models\ReceivedInterest;
use Illuminate\Support\Facades\DB;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;

class ProcessSavingsInterest implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $activeInterests = Interest::where('is_active', true)->get();
        $users = Savings::all(); // fetch once

        DB::transaction(function () use ($activeInterests, $users) {
            foreach ($activeInterests as $interest) {
                foreach ($users as $user) {
                    $principal = $user->balance;
                    $accruedInterest = $interest->interest_rate;

                    // Update balance
                    $user->balance += $accruedInterest;
                    $user->save();

                    // Record interest
                    ReceivedInterest::create([
                        'interest_id' => $interest->id,
                        'user_id' => $user->user_id,
                        'principal_amount' => $principal,
                        'interest_rate' => $accruedInterest,
                        'accrued_interest' => $accruedInterest,
                    ]);
                }
            }
        });
    }
}
