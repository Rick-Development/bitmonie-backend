<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\QuidaxRampService;
use App\Services\RampTransactionSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class QuidaxRampWebhookController extends Controller
{
    public function handle(
        Request $request,
        QuidaxRampService $rampService,
        RampTransactionSyncService $syncService
    ) {
        $signature = $request->header('x-ramp-signature');
        $payload = $request->getContent();

        if (!$rampService->verifyWebhookSignature($payload, $signature)) {
            Log::warning('Quidax Ramp webhook signature verification failed.');

            return response()->json([
                'status' => 'error',
                'message' => 'Invalid signature',
            ], 401);
        }

        $transaction = $syncService->handleWebhook($request->all());

        return response()->json([
            'status' => $transaction ? 'success' : 'ignored',
        ], 200);
    }
}
