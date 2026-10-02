<?php

namespace App\Console\Commands;

use App\Models\AutosaveTransaction;
use App\Services\AutosaveService;
use App\Services\Savings\Contracts\SavingsFundingProviderInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReconcileAutosaveTransactions extends Command
{
    protected $signature = 'autosave:reconcile
                            {--limit=100 : Maximum number of transactions to process}
                            {--id= : Reconcile a specific autosave transaction}';

    protected $description = 'Reconcile pending autosave transactions with the funding provider';

    public function __construct(
        protected SavingsFundingProviderInterface $fundingProvider,
        protected AutosaveService $autosaveService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $transactionId = $this->option('id');

        $query = AutosaveTransaction::query()
            ->where('status', 'pending')
            ->where(function ($query) {
                $query
                    ->whereNull('provider_status')
                    ->orWhereIn('provider_status', [
                        'processing',
                        'created',
                        'initiated',
                        'pending',
                    ]);
            })
            ->orderBy('id');

        if ($transactionId) {
            $query->where('id', $transactionId);
        }

        $transactions = $query
            ->limit($limit)
            ->get();

        if ($transactions->isEmpty()) {
            $this->info(
                'No pending autosave transactions require reconciliation.'
            );

            return Command::SUCCESS;
        }

        $this->info(
            "Reconciling {$transactions->count()} autosave transaction(s)..."
        );

        $processed = 0;
        $completed = 0;
        $failed = 0;
        $pending = 0;
        $errors = 0;

        foreach ($transactions as $transaction) {
            try {
                $result = $this->reconcileTransaction($transaction);

                $processed++;

                match ($result) {
                    'completed' => $completed++,
                    'failed' => $failed++,
                    'pending' => $pending++,
                    default => null,
                };
            } catch (Throwable $e) {
                $errors++;

                Log::error('Autosave reconciliation failed.', [
                    'autosave_transaction_id' => $transaction->id,
                    'user_id' => $transaction->user_id,
                    'error' => $e->getMessage(),
                ]);

                $this->error(
                    "Transaction #{$transaction->id}: {$e->getMessage()}"
                );
            }
        }

        $this->newLine();

        $this->info("Processed: {$processed}");
        $this->info("Completed: {$completed}");
        $this->info("Failed: {$failed}");
        $this->info("Still pending: {$pending}");

        if ($errors > 0) {
            $this->warn("Errors: {$errors}");

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    protected function reconcileTransaction(
        AutosaveTransaction $transaction
    ): string {
        $metadata = $this->transactionMetadata($transaction);

        $providerResponse = $metadata['provider_response'] ?? [];

        if (!is_array($providerResponse)) {
            $providerResponse = [];
        }

        /*
         * SafeHaven requires both sessionId and paymentReference
         * to check transfer status.
         */
        $sessionId = $providerResponse['sessionId']
            ?? $providerResponse['data']['sessionId']
            ?? $metadata['provider_session_id']
            ?? null;

        /*
         * SafeHaven may return an empty paymentReference in its
         * transfer response. The local autosave reference is therefore
         * the reliable payment reference.
         */
        $paymentReference = $transaction->reference
            ?? $metadata['payment_reference']
            ?? $providerResponse['paymentReference']
            ?? $providerResponse['data']['paymentReference']
            ?? null;

        if (empty($sessionId)) {
            throw new \RuntimeException(
                'Provider session ID is missing.'
            );
        }

        if (empty($paymentReference)) {
            throw new \RuntimeException(
                'Provider payment reference is missing.'
            );
        }

        /*
         * IMPORTANT:
         * The provider call happens OUTSIDE the local DB transaction.
         */
        $response = $this->fundingProvider->getTransferStatus(
            (string) $sessionId,
            (string) $paymentReference
        );

        $status = $this->providerStatus($response);

        $providerTransactionId = $this->providerTransactionId($response);

        $this->updateProviderInformation(
            $transaction,
            $status,
            $providerTransactionId,
            $response,
            (string) $sessionId,
            (string) $paymentReference
        );

        if ($this->isCompleted($status)) {
            /*
             * AutosaveService performs the local accounting inside
             * a DB transaction and is idempotent.
             */
            $this->autosaveService->reconcileCompletedTransaction(
                $transaction,
                $response
            );

            $this->info(
                "Transaction #{$transaction->id}: completed."
            );

            return 'completed';
        }

        if ($this->isFailed($status)) {
            $transaction->forceFill([
                'status' => 'failed',
                'provider_status' => $status,
            ])->save();

            $this->warn(
                "Transaction #{$transaction->id}: {$status}."
            );

            return 'failed';
        }

        $transaction->forceFill([
            'status' => 'pending',
            'provider_status' => $status ?: 'pending',
        ])->save();

        $this->line(
            "Transaction #{$transaction->id}: still " .
            ($status ?: 'pending') . '.'
        );

        return 'pending';
    }

    protected function transactionMetadata(
        AutosaveTransaction $transaction
    ): array {
        $metadata = $transaction->metadata;

        if (is_array($metadata)) {
            return $metadata;
        }

        if (is_string($metadata) && $metadata !== '') {
            $decoded = json_decode($metadata, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    protected function providerStatus(array $response): string
    {
        return strtolower(
            trim(
                (string) (
                    $response['status']
                    ?? $response['data']['status']
                    ?? ''
                )
            )
        );
    }

    protected function providerTransactionId(
        array $response
    ): ?string {
        $id = $response['_id']
            ?? $response['data']['_id']
            ?? null;

        return $id !== null ? (string) $id : null;
    }

    protected function updateProviderInformation(
        AutosaveTransaction $transaction,
        string $status,
        ?string $providerTransactionId,
        array $response,
        string $sessionId,
        string $paymentReference
    ): void {
        $metadata = $this->transactionMetadata($transaction);

        $metadata['funding_provider'] =
            $metadata['funding_provider'] ?? 'safehaven';

        $metadata['provider_status'] = $status;
        $metadata['provider_session_id'] = $sessionId;
        $metadata['payment_reference'] = $paymentReference;
        $metadata['provider_response'] = $response;

        $transaction->forceFill([
            'provider_status' => $status ?: 'pending',
            'provider_transaction_id' =>
                $providerTransactionId
                ?? $transaction->provider_transaction_id,
            'metadata' => $metadata,
        ])->save();
    }

    protected function isCompleted(string $status): bool
    {
        return $status === 'completed';
    }

    protected function isFailed(string $status): bool
    {
        return in_array($status, [
            'failed',
            'canceled',
            'cancelled',
        ], true);
    }
}
