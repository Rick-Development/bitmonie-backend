<?php

namespace App\Services\Savings;

use App\Models\SavingsAuditEvent;
use Illuminate\Support\Facades\Log;

/** Persists audit events and emits structured logs. Never logs secrets. */
class SavingsAuditor
{
    public function record(string $event, ?int $userId, ?int $accountId = null, array $payload = [], string $actor = 'user'): void
    {
        try {
            SavingsAuditEvent::create([
                'user_id' => $userId,
                'account_id' => $accountId,
                'actor' => $actor,
                'event' => $event,
                'payload' => $payload ?: null,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Audit persistence must never break a committed money movement.
            Log::error('savings.audit_failed', ['event' => $event, 'error' => $e->getMessage()]);
        }

        Log::info('savings.' . $event, ['user_id' => $userId, 'account_id' => $accountId] + $payload);
    }
}
