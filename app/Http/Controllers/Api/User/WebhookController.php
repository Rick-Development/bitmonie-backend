<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Models\KycVerification;
use App\Models\SafeHavenWebhookEvent;
use App\Models\User;
use App\Services\WebhookAuditService;
use App\Services\YouVerifyService;
use App\Services\SafeHavenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;

class WebhookController extends Controller
{
    protected $youVerifyService;
    protected $safeHavenService;

    public function __construct(
        YouVerifyService $youVerifyService,
        SafeHavenService $safeHavenService
    ) {
        $this->youVerifyService = $youVerifyService;
        $this->safeHavenService = $safeHavenService;
    }

    /**
     * Handle YouVerify Webhook Callbacks.
     */
    public function handleYouVerify(Request $request)
    {
        $payload = $request->all();
        $signature = $request->header('X-YouVerify-Signature');

        Log::info("YouVerify Webhook Received", ['payload' => $payload]);

        // 1. Verify Signature (Security)
        if (!$this->youVerifyService->verifyWebhookSignature($signature, $payload)) {
            Log::warning("YouVerify Webhook Signature Mismatch");
            // return response()->json(['message' => 'Invalid signature'], 401); 
        }

        $event = $request->input('event');
        $data = $request->input('data', []);
        $transactionId = $data['id'] ?? null;

        // 2. Find the verification record
        $verification = KycVerification::where('transaction_id', $transactionId)->first();

        if (!$verification) {
            Log::error("KycVerification record not found for transaction: " . $transactionId);
            return response()->json(['status' => 'ignored'], 200);
        }

        $user = $verification->user;

        // 3. Handle Events
        switch ($event) {
            case 'vform.completed':
            case 'identity.verified':
                $verification->update([
                    'status' => 'verified',
                    'data' => $data
                ]);
                
                if ($user) {
                    // Update user's tier if the completed verification is higher than current
                    if ($verification->level > $user->kyc_tier) {
                        $user->update([
                            'kyc_tier' => $verification->level,
                            'kyc_verified' => 1 // Mark as verified generally as well
                        ]);
                        Log::info("User KYC Upgraded to Level $verification->level: " . $user->id);
                    }
                }
                break;

            case 'identity.failed':
                $verification->update([
                    'status' => 'failed',
                    'data' => $data
                ]);
                
                Log::info("User KYC Failed via Webhook for Level $verification->level, User: " . ($user->id ?? 'unknown'));
                break;

            default:
                Log::info("Unhandled YouVerify Event: " . $event);
        }

        return response()->json(['status' => 'success'], 200);
    }

    /**
     * Handle SafeHaven Settlement Webhook Callbacks.
     */
    public function handleSafeHaven(Request $request, WebhookAuditService $audit)
    {
        $eventLog = $audit->recordReceived($request, 'safehaven');
        $payload = $request->all();
        $details = $this->extractSafeHavenWebhookDetails($payload);
        $dedupeKey = $audit->dedupeKey('safehaven', $details['reference'], $details['internal_status'], $details['event_name']);

        $audit->enrich($eventLog, [
            'event_name' => $details['event_name'],
            'event_id' => $details['event_id'],
            'dedupe_key' => $dedupeKey,
            'transaction_reference' => $details['reference'],
            'transaction_type' => 'settlement',
            'provider_status' => $details['provider_status'],
            'internal_status' => $details['internal_status'],
        ]);

        if (empty($payload)) {
            $audit->markFailed($eventLog, 'Malformed or empty SafeHaven webhook payload.');

            return response()->json(['status' => 'error_logged'], 200);
        }

        if ($details['reference'] === null) {
            $audit->markFailed($eventLog, 'SafeHaven webhook missing provider reference.');

            return response()->json(['status' => 'error_logged'], 200);
        }

        if ($duplicate = $audit->findProcessedDuplicate($dedupeKey, $eventLog->id)) {
            $audit->markDuplicate($eventLog, $duplicate);

            return response()->json(['status' => 'duplicate'], 200);
        }

        $existingSafeHavenEvent = SafeHavenWebhookEvent::where('provider_reference', $details['reference'])->first();
        if ($existingSafeHavenEvent && $existingSafeHavenEvent->status === 'processed') {
            $eventLog->forceFill([
                'processing_status' => 'duplicate',
                'duplicate' => true,
                'http_status' => 200,
                'processed_at' => now(),
            ])->save();

            $audit->recordReconciliation($eventLog, [
                'action' => 'safehaven_duplicate_ignored',
                'transaction_reference' => $details['reference'],
                'user_id' => $existingSafeHavenEvent->user_id,
                'currency' => 'NGN',
                'amount' => $existingSafeHavenEvent->credit_amount,
                'status_after' => 'successful',
                'balance_after' => $existingSafeHavenEvent->balance_after,
                'duplicate' => true,
                'notes' => 'Duplicate SafeHaven provider reference already processed.',
            ]);

            return response()->json(['status' => 'duplicate'], 200);
        }

        if ($details['internal_status'] !== 'successful') {
            $audit->recordReconciliation($eventLog, [
                'action' => 'safehaven_non_success_no_balance_change',
                'transaction_reference' => $details['reference'],
                'currency' => 'NGN',
                'amount' => is_numeric($details['amount']) ? $details['amount'] : null,
                'status_after' => $details['internal_status'],
                'notes' => 'SafeHaven event was not a confirmed successful incoming settlement; balance was not changed.',
            ]);

            if ($details['internal_status'] === 'failed') {
                $audit->markFailed($eventLog, 'SafeHaven webhook status indicates failed/reversed transaction.');
            } else {
                $audit->markIgnored($eventLog, 'SafeHaven webhook is not yet a completed incoming settlement.');
            }

            return response()->json(['status' => 'ignored'], 200);
        }

        try {
            $processed = $this->safeHavenService->handleSettlement($request->all());
            $safeHavenEvent = SafeHavenWebhookEvent::where('provider_reference', $details['reference'])->first();

            if ($safeHavenEvent && $safeHavenEvent->status === 'processed') {
                $audit->enrich($eventLog, ['user_id' => $safeHavenEvent->user_id]);
                $audit->recordReconciliation($eventLog, [
                    'action' => 'safehaven_settlement_reconciled',
                    'transaction_reference' => $details['reference'],
                    'user_id' => $safeHavenEvent->user_id,
                    'currency' => 'NGN',
                    'amount' => $safeHavenEvent->credit_amount,
                    'status_after' => 'successful',
                    'balance_after' => $safeHavenEvent->balance_after,
                    'changes' => [
                        'safehaven_webhook_event_id' => $safeHavenEvent->id,
                        'wallet_id' => $safeHavenEvent->wallet_id,
                        'account_number' => $safeHavenEvent->account_number,
                    ],
                ]);
                $audit->markProcessed($eventLog, 'reconciled');

                return response()->json([
                    'status' => $processed ? 'success' : 'duplicate',
                ], 200);
            }

            $failureReason = $safeHavenEvent?->error_message ?: 'SafeHaven settlement payload was ignored or could not be reconciled.';
            $audit->markFailed($eventLog, $failureReason);

            return response()->json([
                'status' => $processed ? 'success' : 'ignored',
            ], 200);
        } catch (Exception $e) {
            $audit->markFailed($eventLog, $e->getMessage());

            return response()->json(['status' => 'error_logged'], 200);
        }
    }

    protected function extractSafeHavenWebhookDetails(array $payload): array
    {
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $status = strtolower((string) ($data['status'] ?? $payload['status'] ?? ''));
        $responseCode = (string) ($data['responseCode'] ?? $payload['responseCode'] ?? '');
        $isReversed = (bool) ($data['isReversed'] ?? false);
        $reference = $data['paymentReference']
            ?? $data['sessionId']
            ?? $data['reference']
            ?? $payload['reference']
            ?? $data['_id']
            ?? null;

        $internalStatus = 'pending';
        if ($isReversed || in_array($status, ['failed', 'rejected', 'reversed', 'cancelled', 'canceled', 'error'], true)) {
            $internalStatus = 'failed';
        } elseif (in_array($status, ['completed', 'success', 'successful', 'approved'], true) || $responseCode === '00') {
            $internalStatus = 'successful';
        }

        return [
            'event_name' => strtolower((string) ($payload['eventType'] ?? $payload['type'] ?? $data['type'] ?? '')),
            'event_id' => $payload['id'] ?? $payload['_id'] ?? $data['id'] ?? $data['_id'] ?? null,
            'reference' => $reference ? (string) $reference : null,
            'provider_status' => $status ?: ($responseCode !== '' ? $responseCode : null),
            'internal_status' => $internalStatus,
            'amount' => $data['amount'] ?? $payload['amount'] ?? null,
        ];
    }
}
