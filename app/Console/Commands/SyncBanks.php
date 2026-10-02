<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SyncBanks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'banks:safehaven-sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch and sync bank list from SafeHaven to local database';

    /**
     * Execute the console command.
     */
    public function handle(\App\Services\SafeHavenService $safeHaven)
    {
        $this->info('Starting SafeHaven bank list sync...');
        
        try {
            $count = $safeHaven->syncBanks();
            $this->info("Successfully synced {$count} banks.");
        } catch (\Exception $e) {
            $this->error('Sync failed: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
