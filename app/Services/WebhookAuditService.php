<?php

namespace App\Services;

use App\Models\WebhookEventLog;
use App\Models\WebhookReconciliationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookAuditService
{
    public function recordReceived(Request $request, string $provider): WebhookEventLog
    {
        $rawPayload = $request->getContent();
        $payload = json_decode($rawPayload, true);

        if (!is_array($payload)) {
            $payload = $request->all();
        }

        return WebhookEventLog::create([
            'provider' => $provider,
            'endpoint' => '/' . trim($request->path(), '/'),
            'payload' => $payload,
            'raw_payload' => $rawPayload,
            'headers' => $this->safeHeaders($request),
            'processing_status' => 'received',
            'http_status' => 200,
        ]);
    }

    public function enrich(WebhookEventLog $eventLog, array $attributes): WebhookEventLog
    {
        $eventLog->forceFill(array_filter($attributes, function ($value) {
            return $value !== null && $value !== '';
        }))->save();

        return $eventLog->fresh();
    }

    public function dedupeKey(string $provider, ?string $reference, ?string $status, ?string $eventName = null): ?string
    {
        $reference = strtolower(trim((string) $reference));
        $status = strtolower(trim((string) $status));

        if ($reference === '' || $status === '') {
            return null;
        }

        return sha1(implode('|', [
            strtolower($provider),
            $reference,
            $status,
            strtolower(trim((string) $eventName)),
        ]));
    }

    public function findProcessedDuplicate(?string $dedupeKey, ?int $currentLogId = null): ?WebhookEventLog
    {
        if (!$dedupeKey) {
            return null;
        }

        return WebhookEventLog::query()
            ->where('dedupe_key', $dedupeKey)
            ->where('id', '<>', $currentLogId)
            ->whereIn('processing_status', ['processed', 'reconciled'])
            ->latest('id')
            ->first();
    }

    public function markDuplicate(WebhookEventLog $eventLog, WebhookEventLog $duplicateOf): void
    {
        $eventLog->forceFill([
            'processing_status' => 'duplicate',
            'duplicate' => true,
            'duplicate_of_id' => $duplicateOf->id,
            'http_status' => 200,
            'processed_at' => now(),
        ])->save();

        $this->recordReconciliation($eventLog, [
            'provider' => $eventLog->provider,
            'action' => 'duplicate_ignored',
            'transaction_reference' => $eventLog->transaction_reference,
            'transaction_type' => $eventLog->transaction_type,
            'status_after' => $eventLog->internal_status,
            'duplicate' => true,
            'notes' => 'Duplicate webhook ignored for same reference and status.',
        ]);
    }

    public function markProcessed(WebhookEventLog $eventLog, string $status = 'processed'): void
    {
        $eventLog->forceFill([
            'processing_status' => $status,
            'http_status' => 200,
            'processed_at' => now(),
        ])->save();
    }

    public function markIgnored(WebhookEventLog $eventLog, ?string $reason = null): void
    {
        $eventLog->forceFill([
            'processing_status' => 'ignored',
            'failure_reason' => $reason,
            'http_status' => 200,
            'processed_at' => now(),
        ])->save();
    }

    public function markFailed(WebhookEventLog $eventLog, string $reason, int $httpStatus = 200): void
    {
        $eventLog->forceFill([
            'processing_status' => 'failed',
            'failure_reason' => $reason,
            'http_status' => $httpStatus,
            'processed_at' => now(),
        ])->save();

        Log::warning('Webhook event logged as failed.', [
            'webhook_event_log_id' => $eventLog->id,
            'provider' => $eventLog->provider,
            'endpoint' => $eventLog->endpoint,
            'reason' => $reason,
        ]);
    }

    public function recordReconciliation(WebhookEventLog $eventLog, array $attributes): WebhookReconciliationLog
    {
        return WebhookReconciliationLog::create(array_merge([
            'webhook_event_log_id' => $eventLog->id,
            'provider' => $eventLog->provider,
            'transaction_reference' => $eventLog->transaction_reference,
            'transaction_type' => $eventLog->transaction_type,
        ], $attributes));
    }

    protected function safeHeaders(Request $request): array
    {
        return collect($request->headers->all())
            ->except(['authorization', 'cookie', 'x-api-key'])
            ->map(function ($values) {
                return is_array($values) ? array_values($values) : $values;
            })
            ->all();
    }
}
