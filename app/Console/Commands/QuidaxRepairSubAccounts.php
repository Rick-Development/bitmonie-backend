<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\QuidaxService;
use Illuminate\Console\Command;

class QuidaxRepairSubAccounts extends Command
{
    protected $signature = 'quidax:repair-subaccounts {--dry-run : Check sub-accounts without updating}';
    protected $description = 'Verify and heal missing or invalid Quidax sub-accounts for all active users';

    public function handle(QuidaxService $quidaxService)
    {
        $this->info('Starting Quidax sub-account audit and repair...');

        $users = User::whereNotNull('email')->get();
        $this->info("Scanning {$users->count()} users...");

        $missingCount = 0;
        $healedCount = 0;
        $validCount = 0;

        foreach ($users as $user) {
            if (!$user->quidax_id) {
                $this->warn("User #{$user->id} ({$user->email}) has no quidax_id.");
                $missingCount++;

                if (!$this->option('dry-run')) {
                    $newId = $quidaxService->ensureUserSubAccount($user);
                    if ($newId) {
                        $this->info(" -> Created sub-account {$newId} for user #{$user->id}");
                        $healedCount++;
                    }
                }
                continue;
            }

            // Test if sub-account actually exists on Quidax
            $walletResponse = $quidaxService->fetchUserWallets($user->quidax_id);
            if (is_array($walletResponse) && $quidaxService->isSubAccountMissingResponse($walletResponse)) {
                $this->error("User #{$user->id} ({$user->email}) sub-account {$user->quidax_id} does NOT exist on provider!");
                $missingCount++;

                if (!$this->option('dry-run')) {
                    $newId = $quidaxService->ensureUserSubAccount($user, true);
                    if ($newId) {
                        $this->info(" -> Healed with new sub-account {$newId} for user #{$user->id}");
                        $healedCount++;
                    }
                }
            } else {
                $validCount++;
            }
        }

        $this->info("Scan completed: {$validCount} valid, {$missingCount} missing/broken, {$healedCount} healed.");
        return 0;
    }
}
