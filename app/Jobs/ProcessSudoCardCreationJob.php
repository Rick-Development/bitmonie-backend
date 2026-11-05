<?php

namespace App\Jobs;

use App\Models\CryptoCardEscrow;
use App\Models\CryptoCardsModel;
use App\Models\CryptoCardTransactions;
use App\Services\CryptoCardService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Async Sudo virtual-card creation.
 *
 * 1) USDT → fiat offramp into the Sudo settlement account (when fee/amount > 0)
 * 2) On offramp success (webhook) → create card at Sudo
 * 3) On any failure → refund USDT + notify
 *
 * Zero-fee cards skip offramp and create at Sudo in this job.
 */
class ProcessSudoCardCreationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 180;

    public array $backoff = [15, 45, 90];

    public function __construct(
        protected int $userId,
        protected int $cardId,
        protected int $transactionId,
        protected int $escrowId,
        protected array $context = []
    ) {
        $this->onQueue(config('queue.queues.crypto_cards', 'crypto-cards'));
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('sudo-card-create:' . $this->cardId))
                ->releaseAfter(20)
                ->expireAfter(300),
        ];
    }

    public function handle(CryptoCardService $cards): void
    {
        $card = CryptoCardsModel::find($this->cardId);
        $transaction = CryptoCardTransactions::find($this->transactionId);
        $escrow = CryptoCardEscrow::find($this->escrowId);

        if (! $card || ! $transaction || ! $escrow) {
            Log::error('Sudo card creation job missing records.', [
                'card_id' => $this->cardId,
                'transaction_id' => $this->transactionId,
                'escrow_id' => $this->escrowId,
            ]);

            return;
        }

        if (! in_array(strtolower((string) $card->card_status), ['pending', 'processing'], true)) {
            Log::info('Sudo card creation skipped; card already terminal.', [
                'card_id' => $card->id,
                'status' => $card->card_status,
            ]);

            return;
        }

        $usdtAmount = (float) ($this->context['usdt_amount'] ?? $transaction->amount ?? 0);

        try {
            $card->update(['card_status' => 'processing']);
            $transaction->update(['transaction_status' => 'processing']);
            $escrow->update(['status' => 'processing']);

            // No USDT to convert — create at Sudo immediately (platform settlement account).
            if ($usdtAmount <= 0) {
                $cards->finalizePendingCardCreation($card, $transaction, $escrow, $this->context);

                return;
            }

            // Debit USDT → NGN into Sudo settlement bank via Quidax offramp.
            // Webhook success continues in ProcessQuidaxOfframpForCardFunding → finalizePendingCardCreation.
            InitiateQuidaxCardFundingJob::dispatch(
                $this->userId,
                $card->id,
                $usdtAmount,
                $transaction->id,
                $escrow->id,
                array_merge($this->context, [
                    'purpose' => 'card_creation',
                    'crypto_currency' => 'usdt',
                    'fiat_currency' => 'ngn',
                    'network' => $this->context['network'] ?? 'trc20',
                    'usdt_debited' => true,
                ])
            );

            Log::info('Sudo card creation queued Quidax offramp.', [
                'card_id' => $card->id,
                'transaction_id' => $transaction->id,
                'usdt_amount' => $usdtAmount,
            ]);
        } catch (Throwable $e) {
            Log::error('Sudo card creation job failed.', [
                'card_id' => $this->cardId,
                'error' => $e->getMessage(),
            ]);

            $cards->failPendingCardCreation(
                $card,
                $transaction,
                $escrow,
                $e->getMessage(),
                $this->context
            );

            throw $e;
        }
    }

    public function failed(?Throwable $e): void
    {
        try {
            $cards = app(CryptoCardService::class);
            $card = CryptoCardsModel::find($this->cardId);
            $transaction = CryptoCardTransactions::find($this->transactionId);
            $escrow = CryptoCardEscrow::find($this->escrowId);

            if ($card && $transaction) {
                $cards->failPendingCardCreation(
                    $card,
                    $transaction,
                    $escrow,
                    $e?->getMessage() ?: 'Card creation job exhausted retries.',
                    $this->context
                );
            }
        } catch (Throwable $inner) {
            Log::error('Sudo card creation failed() cleanup error.', [
                'card_id' => $this->cardId,
                'error' => $inner->getMessage(),
            ]);
        }
    }
}
