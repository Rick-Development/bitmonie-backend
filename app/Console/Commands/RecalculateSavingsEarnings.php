<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\SavingsService;
use Illuminate\Console\Command;

class RecalculateSavingsEarnings extends Command
{
    protected $signature = 'savings:recalculate {user_id? : Recalculate one user by ID} {--all : Recalculate every active savings account}';

    protected $description = 'Recalculate missed daily savings earnings for one user or all users';

    public function handle(SavingsService $savingsService): int
    {
        $userId = $this->argument('user_id');

        if ($userId !== null) {
            $summary = $savingsService->recalculateUserSavings((int) $userId);
            $this->info('Savings earnings recalculated for user ' . $userId . '.');
            $this->line(json_encode($summary, JSON_PRETTY_PRINT));

            return Command::SUCCESS;
        }

        if (!$this->option('all')) {
            $this->error('Provide a user_id or pass --all.');

            return Command::FAILURE;
        }

        User::query()
            ->orderBy('id')
            ->chunkById(100, function ($users) use ($savingsService) {
                foreach ($users as $user) {
                    $summary = $savingsService->recalculateUserSavings((int) $user->id);
                    $this->line('User ' . $user->id . ': ' . json_encode($summary));
                }
            });

        $this->info('Savings earnings recalculation completed.');

        return Command::SUCCESS;
    }
}
