<?php

namespace App\Services;

use App\Models\GiftCardTradeCategory;
use App\Models\GiftCardTradeCountry;
use App\Models\GiftCardTradeRate;
use App\Models\GiftCardTradeSubmission;
use App\Models\GiftCardTradeType;
use App\Models\GiftCardTradeImage;
use App\Services\WalletService;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Exception;

class GiftCardTradeService
{
    /**
     * Get active rates filtered by parameters
     */
    public function getRates(array $filters = [])
    {
        $query = GiftCardTradeRate::with(['category', 'type', 'country'])
            ->where('status', true);

        if (isset($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (isset($filters['type_id'])) {
            $query->where('type_id', $filters['type_id']);
        }

        if (isset($filters['country_id'])) {
            $query->where('country_id', $filters['country_id']);
        }

        return $query->get();
    }

    /**
     * Calculate NGN amount based on rate
     */
    public function calculateAmount(int $categoryId, int $typeId, int $countryId, float $amount): array
    {
        $rate = GiftCardTradeRate::where([
            ['category_id', '=', $categoryId],
            ['type_id', '=', $typeId],
            ['country_id', '=', $countryId],
            ['status', '=', true],
        ])
        ->where('min_amount', '<=', $amount)
        ->where('max_amount', '>=', $amount)
        ->first();

        if (!$rate) {
            throw new Exception("No active rate found for this card amount.");
        }

        $ngnAmount = $amount * $rate->rate_per_dollar;

        return [
            'rate_id' => $rate->id,
            'rate_per_dollar' => $rate->rate_per_dollar,
            'ngn_amount' => $ngnAmount,
            'currency' => $rate->currency
        ];
    }

    /**
     * Submit a new trade
     */
    public function submitTrade(User $user, array $data): GiftCardTradeSubmission
    {
        return DB::transaction(function () use ($user, $data) {
            // Calculate amount and validate rate
            $calculation = $this->calculateAmount(
                $data['category_id'],
                $data['type_id'],
                $data['country_id'],
                $data['card_amount']
            );

            // Create submission
            $submission = GiftCardTradeSubmission::create([
                'user_id' => $user->id,
                'category_id' => $data['category_id'],
                'type_id' => $data['type_id'],
                'country_id' => $data['country_id'],
                'rate_id' => $calculation['rate_id'],
                'card_amount' => $data['card_amount'],
                'card_currency' => $calculation['currency'],
                'ngn_amount' => $calculation['ngn_amount'],
                'card_code' => $data['card_code'] ?? null,
                'status' => 'pending',
            ]);

            // Handle images
            if (isset($data['images']) && is_array($data['images'])) {
                foreach ($data['images'] as $imageFile) {
                    $path = $this->uploadImage($imageFile);
                    GiftCardTradeImage::create([
                        'trade_id' => $submission->id,
                        'image_path' => $path,
                    ]);
                }
            }

            return $submission->load(['category', 'type', 'country', 'images']);
        });
    }

    /**
     * Approve a trade
     */
    public function approveTrade(int $tradeId, int $adminId): GiftCardTradeSubmission
    {
        $trade = GiftCardTradeSubmission::findOrFail($tradeId);

        if ($trade->status !== 'pending') {
            throw new Exception("Trade is already {$trade->status}");
        }

        return DB::transaction(function () use ($trade, $adminId) {
            // Update status
            $trade->update([
                'status' => 'approved',
                'admin_id' => $adminId,
            ]);

            // Credit user's wallet
            $wallet = $trade->user->wallets()->where('currency_code', 'NGN')->firstOrFail();
            
            $reference = 'GFT-' . strtoupper(uniqid());
            WalletService::credit($wallet->id, (string)$trade->ngn_amount, $reference, [
                'type' => 'gift_card_trade',
                'description' => "Gift Card Sale: {$trade->category->name} ({$trade->card_amount} {$trade->card_currency})"
            ]);

            // TODO: Send notification to user

            return $trade;
        });
    }

    /**
     * Reject a trade
     */
    public function rejectTrade(int $tradeId, int $adminId, string $reason): GiftCardTradeSubmission
    {
        $trade = GiftCardTradeSubmission::findOrFail($tradeId);

        if ($trade->status !== 'pending') {
            throw new Exception("Trade is already {$trade->status}");
        }

        $trade->update([
            'status' => 'rejected',
            'admin_id' => $adminId,
            'rejection_reason' => $reason,
        ]);

        // TODO: Send notification to user

        return $trade;
    }

    /**
     * Upload image helper
     */
    protected function uploadImage($file): string
    {
        // If it's a base64 string
        if (is_string($file) && strpos($file, 'base64') !== false) {
             // Decode base64 and save
             // Implementation depends on how frontend sends images
             // For now assuming file upload via Request
        }
        
        // Setup for standard file upload
        $filename = uniqid() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('gift-cards', $filename, 'public');
        
        return $path;
    }
}
