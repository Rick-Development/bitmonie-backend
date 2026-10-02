<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\LoanBorrowRequest;
use Illuminate\Support\Facades\Log;

class CancelStaleBorrowRequests extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'loans:cancel-stale-requests';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically cancel unmatched borrow requests that have been pending for more than 48 hours.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $cutoffTime = now()->subHours(48);

        $staleRequests = LoanBorrowRequest::where('status', 'pending')
            ->where('created_at', '<=', $cutoffTime)
            ->get();

        $count = 0;

        foreach ($staleRequests as $request) {
            $request->update(['status' => 'cancelled']);
            $count++;
            
            Log::info("Borrow request #{$request->id} cancelled automatically due to 48h timeout.");
        }

        $this->info("Successfully cancelled {$count} stale borrow request(s).");

        return Command::SUCCESS;
    }
}
