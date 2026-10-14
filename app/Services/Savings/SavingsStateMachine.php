<?php

namespace App\Services\Savings;

use App\Models\RecurringSavingsPlan;
use App\Models\SavingsAccount;
use App\Models\SavingsEntry;
use App\Models\SavingsExecution;
use Illuminate\Support\Facades\Log;

/**
 * Single source of truth for allowed state transitions. Statuses are never
 * accepted from clients; every change goes through assert().
 */
final class SavingsStateMachine
{
    private const ACCOUNT = [
        SavingsAccount::ACTIVE => [SavingsAccount::PAUSED, SavingsAccount::COMPLETED, SavingsAccount::EXPIRED, SavingsAccount::CANCELLED],
        SavingsAccount::PAUSED => [SavingsAccount::ACTIVE, SavingsAccount::EXPIRED, SavingsAccount::COMPLETED, SavingsAccount::CANCELLED],
        SavingsAccount::COMPLETED => [SavingsAccount::CANCELLED],
        SavingsAccount::EXPIRED => [SavingsAccount::CANCELLED],
        SavingsAccount::CANCELLED => [],
    ];

    private const PLAN = [
        RecurringSavingsPlan::ACTIVE => [RecurringSavingsPlan::PAUSED, RecurringSavingsPlan::COMPLETED, RecurringSavingsPlan::CANCELLED],
        RecurringSavingsPlan::PAUSED => [RecurringSavingsPlan::ACTIVE, RecurringSavingsPlan::CANCELLED, RecurringSavingsPlan::COMPLETED],
        RecurringSavingsPlan::COMPLETED => [],
        RecurringSavingsPlan::CANCELLED => [],
    ];

    private const EXECUTION = [
        SavingsExecution::PENDING => [SavingsExecution::PROCESSING, SavingsExecution::SKIPPED, SavingsExecution::FAILED],
        SavingsExecution::PROCESSING => [SavingsExecution::COMPLETED, SavingsExecution::FAILED, SavingsExecution::PENDING, SavingsExecution::SKIPPED],
        SavingsExecution::COMPLETED => [],
        SavingsExecution::FAILED => [],
        SavingsExecution::SKIPPED => [],
    ];

    private const ENTRY = [
        'PENDING' => ['PROCESSING', 'COMPLETED', 'FAILED'],
        'PROCESSING' => ['COMPLETED', 'FAILED'],
        'COMPLETED' => ['REVERSED'],
        'FAILED' => [],
        'REVERSED' => [],
    ];

    public static function account(string $from, string $to): void
    {
        self::assert('account', self::ACCOUNT, $from, $to);
    }

    public static function plan(string $from, string $to): void
    {
        self::assert('plan', self::PLAN, $from, $to);
    }

    public static function execution(string $from, string $to): void
    {
        self::assert('execution', self::EXECUTION, $from, $to);
    }

    public static function entry(string $from, string $to): void
    {
        self::assert('entry', self::ENTRY, $from, $to);
    }

    private static function assert(string $kind, array $map, string $from, string $to): void
    {
        if (! in_array($to, $map[$from] ?? [], true)) {
            Log::warning('savings.invalid_state_transition', compact('kind', 'from', 'to'));

            throw SavingsException::invalidState("Cannot change {$kind} from {$from} to {$to}.");
        }
    }
}
