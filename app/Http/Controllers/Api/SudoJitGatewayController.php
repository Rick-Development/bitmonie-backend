<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Sudo\SudoJitGatewayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class SudoJitGatewayController extends Controller
{
    private const LOG_CHANNEL = 'sudo_cards';

    public function __construct(
        private readonly SudoJitGatewayService $gateway
    ) {
    }

    /**
     * Handle Sudo JIT Gateway webhook requests.
     */
    public function handle(Request $request): JsonResponse
    {
        $startedAt = microtime(true);

        /*
         * ---------------------------------------------------------------
         * Request identification
         * ---------------------------------------------------------------
         */
        $eventId = (string) $request->input('_id', '');
        $eventType = (string) (
            $request->input('type')
            ?? $request->input('event')
            ?? $request->input('name')
            ?? ''
        );

        /*
         * ---------------------------------------------------------------
         * Authentication
         * ---------------------------------------------------------------
         */
        if (!$this->gateway->verifyAuthorizationHeader($request)) {
            Log::channel(self::LOG_CHANNEL)->warning(
                'Sudo JIT unauthorized request',
                [
                    'event_id' => $eventId ?: null,
                    'event_type' => $eventType ?: null,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]
            );

            return response()->json([
                'message' => 'Unauthorized',
            ], 401);
        }

        /*
         * ---------------------------------------------------------------
         * Basic request validation
         * ---------------------------------------------------------------
         */
        if (!$request->isJson()) {
            Log::channel(self::LOG_CHANNEL)->warning(
                'Sudo JIT invalid content type',
                [
                    'event_id' => $eventId ?: null,
                    'event_type' => $eventType ?: null,
                    'content_type' => $request->header('Content-Type'),
                    'ip_address' => $request->ip(),
                ]
            );

            return response()->json([
                'message' => 'Content-Type must be application/json',
            ], 415);
        }

        if ($eventType === '') {
            Log::channel(self::LOG_CHANNEL)->warning(
                'Sudo JIT missing event type',
                [
                    'event_id' => $eventId ?: null,
                    'ip_address' => $request->ip(),
                ]
            );

            return response()->json([
                'message' => 'Missing event type',
            ], 400);
        }

        /*
         * ---------------------------------------------------------------
         * Request received
         * ---------------------------------------------------------------
         */
        Log::channel(self::LOG_CHANNEL)->info(
            'Sudo JIT request received',
            [
                'event_id' => $eventId ?: null,
                'event_type' => $eventType,
                'ip_address' => $request->ip(),
            ]
        );

        try {
            /*
             * -----------------------------------------------------------
             * Authorization request
             * -----------------------------------------------------------
             */
            if ($eventType === 'authorization.request') {
                $decision = $this->gateway->handleAuthorizationRequest(
                    $request->all(),
                    $request
                );

                $processingTime = $this->processingTime($startedAt);

                Log::channel(self::LOG_CHANNEL)->info(
                    'Sudo JIT authorization completed',
                    [
                        'event_id' => $eventId ?: null,
                        'event_type' => $eventType,
                        'approved' => (bool) $decision->approve,
                        'response_code' => $decision->responseCode,
                        'processing_time_ms' => $processingTime,
                    ]
                );

                return response()->json([
                    'approved' => (bool) $decision->approve,
                    'responseCode' => $decision->responseCode,
                    'message' => $decision->message,
                ], 200);
            }

            /*
             * -----------------------------------------------------------
             * Card balance request
             * -----------------------------------------------------------
             */
            if ($eventType === 'card.balance') {
                $balance = $this->gateway->handleBalanceRequest(
                    $request->all(),
                    $request
                );

                $processingTime = $this->processingTime($startedAt);

                Log::channel(self::LOG_CHANNEL)->info(
                    'Sudo JIT balance request completed',
                    [
                        'event_id' => $eventId ?: null,
                        'event_type' => $eventType,
                        'processing_time_ms' => $processingTime,
                    ]
                );

                return response()->json($balance, 200);
            }

            /*
             * -----------------------------------------------------------
             * Unsupported event
             * -----------------------------------------------------------
             */
            Log::channel(self::LOG_CHANNEL)->warning(
                'Sudo JIT unsupported event',
                [
                    'event_id' => $eventId ?: null,
                    'event_type' => $eventType,
                    'processing_time_ms' => $this->processingTime($startedAt),
                ]
            );

            return response()->json([
                'message' => 'Unsupported event type',
            ], 400);

        } catch (Throwable $e) {
            /*
             * -----------------------------------------------------------
             * Production exception handling
             * -----------------------------------------------------------
             *
             * Do NOT return the exception message to Sudo.
             * It may expose database, application, or infrastructure
             * information.
             */
            Log::channel(self::LOG_CHANNEL)->error(
                'Sudo JIT request processing failed',
                [
                    'event_id' => $eventId ?: null,
                    'event_type' => $eventType ?: null,
                    'exception' => get_class($e),
                    'error' => $e->getMessage(),
                    'processing_time_ms' => $this->processingTime($startedAt),
                ]
            );

            return response()->json([
                'message' => 'Unable to process request',
            ], 500);
        }
    }

    /**
     * Calculate request processing time in milliseconds.
     */
    private function processingTime(float $startedAt): int
    {
        return (int) round(
            (microtime(true) - $startedAt) * 1000
        );
    }
}
