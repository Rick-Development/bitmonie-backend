<?php

namespace App\Services;

use App\Models\CryptoTransaction;
use Illuminate\Support\Facades\DB;

class CryptoTransactionService
{
    public function create(array $data): CryptoTransaction
    {
        return DB::transaction(function () use ($data) {
            return CryptoTransaction::query()->create([
                'internal_trx_type' => $data['internal_trx_type'] ?? null,
                'internal_trx_ref_id' => $data['internal_trx_ref_id'] ?? null,
                'transaction_type' => $data['transaction_type'],
                'sender_address' => $data['sender_address'] ?? null,
                'receiver_address' => $data['receiver_address'] ?? null,
                'amount' => $data['amount'] ?? null,
                'asset' => $data['asset'] ?? null,
                'block_number' => $data['block_number'] ?? null,
                'txn_hash' => $data['txn_hash'] ?? null,
                'chain' => $data['chain'] ?? null,
                'callback_response' => isset($data['callback_response'])
                    ? json_encode($data['callback_response'])
                    : null,
                'status' => $data['status'] ?? 'pending',
            ]);
        });
    }

    public function updateStatus(
        int $id,
        string $status,
        ?array $response = null
    ): bool {
        $data = ['status' => $status];

        if ($response !== null) {
            $data['callback_response'] = json_encode($response);
        }

        return CryptoTransaction::query()
            ->whereKey($id)
            ->update($data) > 0;
    }
}