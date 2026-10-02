<?php

namespace App\Services;

use App\Models\Admin\Currency;
use App\Models\Loan;
use App\Models\LoanBorrowRequest;
use App\Models\LoanOffer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
class LoanService
{
    protected $quidaxService;
    protected $priceService;

    public function __construct(QuidaxService $quidaxService, PriceService $priceService)
    {
        $this->quidaxService = $quidaxService;
        $this->priceService = $priceService;
    }

    public function getSupportedAssets(): array
    {
        return ['USDT', 'USDC', 'BTC', 'SOL', 'BNB'];
    }

    public function normalizeAssetSymbol($asset): ?string
    {
        if ($asset === null) {
            return null;
        }

        $rawAsset = trim((string) $asset);

        if ($rawAsset === '') {
            return null;
        }

        if (ctype_digit($rawAsset)) {
            try {
                $currencyCode = Currency::query()
                    ->whereKey((int) $rawAsset)
                    ->value('code');

                if ($currencyCode) {
                    $rawAsset = (string) $currencyCode;
                }
            } catch (\Throwable $e) {
                // Fall back to the raw request value if currency lookup is unavailable.
            }
        }

        $normalized = strtoupper(str_replace(['_', '-', '/'], ' ', $rawAsset));
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        $aliases = [
            'USDT' => 'USDT',
            'TETHER' => 'USDT',
            'TETHER USD' => 'USDT',
            'USDC' => 'USDC',
            'USD COIN' => 'USDC',
            'BTC' => 'BTC',
            'BITCOIN' => 'BTC',
            'SOL' => 'SOL',
            'SOLANA' => 'SOL',
            'BNB' => 'BNB',
            'BINANCE COIN' => 'BNB',
            'BINANCE' => 'BNB',
        ];

        return $aliases[$normalized] ?? $normalized;
    }

    /**
     * Create a lending offer
     */
    public function createLendingOffer(User $user, string $asset, float $amount, int $durationDays)
    {
        $asset = $this->normalizeAssetSymbol($asset) ?? $asset;

        // Check if user has active outstanding loan as borrower
        $hasActiveLoan = Loan::where('borrower_id', $user->id)
            ->where('status', 'active')
            ->exists();

        if ($hasActiveLoan) {
            throw new \Exception('You cannot lend while you have an active outstanding loan.');
        }

        // Check the user's available Quidax balance after existing loan reservations.
        $balance = $this->getAvailableQuidaxBalance($user, $asset);
        
        if ($balance < $amount) {
            throw new \Exception('Insufficient available ' . $asset . ' balance. Available: ' . $balance);
        }

        // Create lending offer with status 'pending'
        DB::beginTransaction();
        try {
            $offer = LoanOffer::create([
                'user_id' => $user->id,
                'asset' => $asset,
                'amount' => $amount,
                'remaining_amount' => $amount,
                'min_interest_rate' => 5.00, // 5% monthly
                'duration_days' => $durationDays,
                'status' => 'pending',
            ]);

            DB::commit();

            return $offer;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Cancel a pending lending offer
     */
    public function cancelLendingOffer(User $user, int $offerId)
    {
        $offer = LoanOffer::where('id', $offerId)
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->firstOrFail();

        DB::beginTransaction();
        try {
            // Update status
            $offer->update(['status' => 'cancelled']);

            DB::commit();

            return $offer;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Create a borrow request
     */
    public function createBorrowRequest(User $user, string $asset, float $amount, int $durationDays, string $collateralAsset)
    {
        $asset = $this->normalizeAssetSymbol($asset) ?? $asset;
        $collateralAsset = $this->normalizeAssetSymbol($collateralAsset) ?? $collateralAsset;

        // Check if user has active outstanding loan
        $hasActiveLoan = Loan::where('borrower_id', $user->id)
            ->where('status', 'active')
            ->exists();

        if ($hasActiveLoan) {
            throw new \Exception('You already have an active outstanding loan. Please repay it before borrowing again.');
        }

        // Calculate required collateral (125% of loan value)
        $collateralAmount = $this->calculateCollateralAmount($amount, $asset, $collateralAsset);

        // Check the user's available collateral on Quidax after existing reservations.
        $collateralBalance = $this->getAvailableQuidaxBalance($user, $collateralAsset);
        
        if ($collateralBalance < $collateralAmount) {
            throw new \Exception('Insufficient available ' . $collateralAsset . ' for collateral. Required: ' . $collateralAmount . ', Available: ' . $collateralBalance);
        }

        DB::beginTransaction();
        try {
            // Create borrow request
            $borrowRequest = LoanBorrowRequest::create([
                'user_id' => $user->id,
                'asset' => $asset,
                'amount' => $amount,
                'duration_days' => $durationDays,
                'collateral_asset' => $collateralAsset,
                'collateral_amount' => $collateralAmount,
                'status' => 'pending',
            ]);

            // Attempt to match with lending offers
            $matchResult = $this->matchBorrowRequestWithLendingOffer($borrowRequest);

            DB::commit();

            if ($matchResult['matched']) {
                return [
                    'message' => 'Loan disbursement successful',
                    'data' => $matchResult['loan'],
                ];
            } else {
                return [
                    'message' => 'Borrow request created. Waiting for matching lender.',
                    'data' => $borrowRequest,
                ];
            }
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Match borrow request with lending offers
     */
    protected function matchBorrowRequestWithLendingOffer(LoanBorrowRequest $borrowRequest)
    {
        // Find matching lending offer
        $matchingOffer = LoanOffer::where('asset', $borrowRequest->asset)
            ->where('duration_days', $borrowRequest->duration_days)
            ->where('remaining_amount', '>=', $borrowRequest->amount)
            ->where('status', 'pending')
            ->orderBy('created_at', 'asc')
            ->first();

        if (!$matchingOffer) {
            return ['matched' => false];
        }

        DB::beginTransaction();
        try {
            // Calculate interest using dynamic rate based on collateral asset
            $monthlyRate = $this->getInterestRateForCollateral(
                $borrowRequest->collateral_asset,
                $borrowRequest->duration_days
            );
            $months = $borrowRequest->duration_days / 30;
            $totalInterest = $borrowRequest->amount * ($monthlyRate / 100) * $months;

            // Create loan
            $loan = Loan::create([
                'reference_code' => 'LOAN-' . strtoupper(Str::random(10)),
                'borrow_request_id' => $borrowRequest->id,
                'loan_offer_id' => $matchingOffer->id,
                'borrower_id' => $borrowRequest->user_id,
                'lender_id' => $matchingOffer->user_id,
                'asset' => $borrowRequest->asset,
                'amount' => $borrowRequest->amount,
                'collateral_asset' => $borrowRequest->collateral_asset,
                'collateral_amount' => $borrowRequest->collateral_amount,
                'interest_rate' => $monthlyRate,
                'total_interest' => $totalInterest,
                'start_date' => now(),
                'due_date' => now()->addDays($borrowRequest->duration_days),
                'status' => 'active',
            ]);

            // Update statuses
            $borrowRequest->update(['status' => 'matched']);
            
            $matchingOffer->remaining_amount -= $borrowRequest->amount;
            if ($matchingOffer->remaining_amount <= 0) {
                $matchingOffer->status = 'matched';
            }
            $matchingOffer->save();

            // Transfer funds on Quidax (internal transfer from lender to borrower)
            $this->transferFundsOnQuidax(
                $matchingOffer->user,
                $borrowRequest->user,
                $borrowRequest->asset,
                $borrowRequest->amount
            );

            DB::commit();

            return [
                'matched' => true,
                'loan' => $loan->load(['borrower', 'lender']),
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Repay a loan
     */
    public function repayLoan(User $user, int $loanId)
    {
        $loan = Loan::where('id', $loanId)
            ->where('borrower_id', $user->id)
            ->where('status', 'active')
            ->firstOrFail();

        $totalRepayment = $loan->amount + $loan->total_interest;

        // Check balance
        $balance = $this->getQuidaxBalance($user, $loan->asset);
        
        if ($balance < $totalRepayment) {
            throw new \Exception('Insufficient ' . $loan->asset . ' balance for repayment. Required: ' . $totalRepayment . ', Available: ' . $balance);
        }

        DB::beginTransaction();
        try {
            // Transfer repayment to lender
            $this->transferFundsOnQuidax(
                $user,
                $loan->lender,
                $loan->asset,
                $totalRepayment
            );

            // Update loan status
            $loan->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            DB::commit();

            return $loan;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Calculate collateral amount (125% of loan value)
     */
    public function calculateCollateralAmount(float $loanAmount, string $loanAsset, string $collateralAsset)
    {
        $loanAsset = $this->normalizeAssetSymbol($loanAsset) ?? $loanAsset;
        $collateralAsset = $this->normalizeAssetSymbol($collateralAsset) ?? $collateralAsset;

        // Get current prices from Quidax
        $loanAssetPrice = $this->getAssetPrice($loanAsset);
        $collateralAssetPrice = $this->getAssetPrice($collateralAsset);

        // Calculate loan value in USD
        $loanValueUSD = $loanAmount * $loanAssetPrice;

        // Add 25% (125% collateralization)
        $requiredCollateralValueUSD = $loanValueUSD * 1.25;

        // Convert to collateral asset amount
        $collateralAmount = $requiredCollateralValueUSD / $collateralAssetPrice;

        return round($collateralAmount, 8);
    }

    /**
     * Get Quidax balance for a specific asset
     */
    protected function getQuidaxBalance(User $user, string $asset)
    {
        $asset = $this->normalizeAssetSymbol($asset) ?? $asset;

        if (!$user->quidax_id) {
            return 0.00;
        }

        $walletRes = $this->quidaxService->fetchUserWallet($user->quidax_id, strtolower($asset));
        return (float) ($walletRes['data']['balance'] ?? 0.00);
    }

    protected function getReservedAssetAmount(User $user, string $asset): float
    {
        $asset = $this->normalizeAssetSymbol($asset) ?? $asset;

        $reservedInLendingOffers = (float) LoanOffer::where('user_id', $user->id)
            ->where('asset', $asset)
            ->whereIn('status', ['pending', 'matched'])
            ->sum('remaining_amount');

        $reservedInPendingBorrowRequests = (float) LoanBorrowRequest::where('user_id', $user->id)
            ->where('collateral_asset', $asset)
            ->where('status', 'pending')
            ->sum('collateral_amount');

        $reservedInActiveCollateral = (float) Loan::where('borrower_id', $user->id)
            ->where('collateral_asset', $asset)
            ->whereIn('status', ['active', 'overdue'])
            ->sum('collateral_amount');

        return round(
            $reservedInLendingOffers
            + $reservedInPendingBorrowRequests
            + $reservedInActiveCollateral,
            8
        );
    }

    protected function getAvailableQuidaxBalance(User $user, string $asset): float
    {
        $quidaxBalance = $this->getQuidaxBalance($user, $asset);
        $reservedAmount = $this->getReservedAssetAmount($user, $asset);

        return round(max(0, $quidaxBalance - $reservedAmount), 8);
    }

    /**
     * Get asset price from Quidax
     */
    protected function getAssetPrice(string $asset)
    {
        $asset = $this->normalizeAssetSymbol($asset) ?? strtoupper($asset);
        
        // USDT/USDC are stablecoins, so we can return 1.00 directly for USD valuation
        if (in_array($asset, ['USDT', 'USDC'])) {
            return 1.00;
        }

        try {
            $price = $this->priceService->getPrice($asset);
            if ($price > 0) {
                return $price;
            }
            
            // Fallback to Quidax Ticker if PriceService fails
            $market = strtolower($asset) . 'usdt';
            $tickerRes = $this->quidaxService->getTicker($market);
            
            if (($tickerRes['status'] ?? '') === 'success' && isset($tickerRes['data']['ticker']['last'])) {
                return (float) $tickerRes['data']['ticker']['last'];
            }
            
            // Hard fallbacks
            $fallbacks = [
                'BTC' => 64500.00,
                'ETH' => 3450.00,
                'SOL' => 150.00,
                'BNB' => 580.00,
            ];
            return $fallbacks[$asset] ?? 1.00;

        } catch (\Exception $e) {
            $fallbacks = [
                'BTC' => 64500.00,
                'ETH' => 3450.00,
                'SOL' => 175.00,
                'BNB' => 590.00,
            ];
            return $fallbacks[$asset] ?? 1.00;
        }
    }

    /**
     * Transfer funds on Quidax (internal transfer)
     */
    protected function transferFundsOnQuidax(User $from, User $to, string $asset, float $amount)
    {
        $asset = $this->normalizeAssetSymbol($asset) ?? $asset;

        if (!$from->quidax_id || !$to->quidax_id) {
            throw new \Exception('One or both users do not have a registered Quidax account.');
        }

        $response = $this->quidaxService->transfer(
            $from->quidax_id,
            $to->quidax_id,
            $amount,
            strtolower($asset)
        );

        if (($response['status'] ?? '') !== 'success') {
            throw new \Exception('Fund transfer failed: ' . ($response['message'] ?? 'Unknown Quidax error.'));
        }
    }

    /**
     * Get dynamic interest rate based on collateral asset and duration
     */
    public function getInterestRateForCollateral(string $collateralAsset, int $durationDays)
    {
        $collateralAsset = $this->normalizeAssetSymbol($collateralAsset) ?? $collateralAsset;

        // Base rates by collateral asset (monthly %)
        $baseRates = [
            'BTC' => 4.0,   // Lower risk, lower rate
            'USDT' => 5.0,  // Stablecoin
            'USDC' => 5.0,  // Stablecoin
            'SOL' => 6.0,   // Higher volatility
            'BNB' => 5.5,   // Medium volatility
        ];

        $baseRate = $baseRates[$collateralAsset] ?? 5.0;

        // Duration multiplier (longer duration = slightly lower rate)
        $durationMultiplier = match(true) {
            $durationDays <= 30 => 1.0,
            $durationDays <= 60 => 0.95,
            $durationDays <= 90 => 0.90,
            $durationDays <= 180 => 0.85,
            default => 0.80,
        };

        return round($baseRate * $durationMultiplier, 2);
    }

    /**
     * Get all supported collateral assets with their interest rates
     */
    public function getCollateralOptions(int $durationDays = 30)
    {
        $assets = $this->getSupportedAssets();
        $options = [];

        foreach ($assets as $asset) {
            $interestRate = $this->getInterestRateForCollateral($asset, $durationDays);
            $options[] = [
                'asset' => $asset,
                'interest_rate' => $interestRate,
                'collateralization_ratio' => 125,
                'price_usd' => $this->getAssetPrice($asset),
            ];
        }

        return $options;
    }

    /**
     * Calculate accrued interest for an active loan
     */
    public function calculateAccruedInterest(Loan $loan)
    {
        $startDate = $loan->start_date;
        $currentDate = now();
        
        // Calculate days elapsed
        $daysElapsed = $startDate->diffInDays($currentDate);
        
        // Calculate daily interest rate
        $monthlyRate = $loan->interest_rate / 100;
        $dailyRate = $monthlyRate / 30;
        
        // Calculate accrued interest
        $accruedInterest = $loan->amount * $dailyRate * $daysElapsed;
        
        return round($accruedInterest, 8);
    }

    /**
     * Get detailed balance for a specific loan/bond
     */
    public function getBondBalance(User $user, int $loanId)
    {
        $loan = Loan::with(['borrower', 'lender'])
            ->where(function ($query) use ($user) {
                $query->where('borrower_id', $user->id)
                    ->orWhere('lender_id', $user->id);
            })
            ->findOrFail($loanId);

        $accruedInterest = $this->calculateAccruedInterest($loan);
        $daysRemaining = now()->diffInDays($loan->due_date, false);
        $isOverdue = $daysRemaining < 0;

        return [
            'loan_id' => $loan->id,
            'reference_code' => $loan->reference_code,
            'role' => $loan->borrower_id === $user->id ? 'borrower' : 'lender',
            'asset' => $loan->asset,
            'principal_amount' => $loan->amount,
            'interest_rate' => $loan->interest_rate,
            'accrued_interest' => $accruedInterest,
            'total_interest' => $loan->total_interest,
            'total_repayment_required' => $loan->amount + $loan->total_interest,
            'current_repayment_amount' => $loan->amount + $accruedInterest,
            'collateral_asset' => $loan->collateral_asset,
            'collateral_amount' => $loan->collateral_amount,
            'start_date' => $loan->start_date,
            'due_date' => $loan->due_date,
            'days_remaining' => abs($daysRemaining),
            'is_overdue' => $isOverdue,
            'status' => $loan->status,
        ];
    }

    /**
     * Get total asset balances across all user activities
     */
    public function getUserAssetBalances(User $user)
    {
        $assets = $this->getSupportedAssets();
        $balances = [];

        foreach ($assets as $asset) {
            // Get Quidax balance
            $quidaxBalance = $this->getQuidaxBalance($user, $asset);

            // Calculate locked in lending offers
            $lockedInLending = LoanOffer::where('user_id', $user->id)
                ->where('asset', $asset)
                ->whereIn('status', ['pending', 'matched'])
                ->sum('remaining_amount');

            // Calculate total lent (active loans as lender)
            $totalLent = Loan::where('lender_id', $user->id)
                ->where('asset', $asset)
                ->where('status', 'active')
                ->sum('amount');

            // Calculate total borrowed (active loans as borrower)
            $totalBorrowed = Loan::where('borrower_id', $user->id)
                ->where('asset', $asset)
                ->where('status', 'active')
                ->sum('amount');

            // Calculate locked as collateral (in this asset)
            $lockedInPendingBorrowRequests = LoanBorrowRequest::where('user_id', $user->id)
                ->where('collateral_asset', $asset)
                ->where('status', 'pending')
                ->sum('collateral_amount');

            $lockedAsCollateral = Loan::where('borrower_id', $user->id)
                ->where('collateral_asset', $asset)
                ->whereIn('status', ['active', 'overdue'])
                ->sum('collateral_amount');

            $totalCollateralReserved = $lockedInPendingBorrowRequests + $lockedAsCollateral;

            // Calculate available balance
            $availableBalance = $quidaxBalance - $lockedInLending - $totalCollateralReserved;

            $balances[$asset] = [
                'asset' => $asset,
                'total_balance' => $quidaxBalance,
                'available_balance' => max(0, $availableBalance),
                'locked_in_lending_offers' => $lockedInLending,
                'total_lent' => $totalLent,
                'total_borrowed' => $totalBorrowed,
                'locked_in_pending_borrow_requests' => $lockedInPendingBorrowRequests,
                'locked_as_collateral' => $lockedAsCollateral,
                'total_collateral_reserved' => $totalCollateralReserved,
                'price_usd' => $this->getAssetPrice($asset),
            ];
        }

        // Calculate totals in USD
        $totalValueUSD = 0;
        $totalAvailableUSD = 0;
        $totalLentUSD = 0;
        $totalBorrowedUSD = 0;
        $totalCollateralUSD = 0;

        foreach ($balances as $balance) {
            $price = $balance['price_usd'];
            $totalValueUSD += $balance['total_balance'] * $price;
            $totalAvailableUSD += $balance['available_balance'] * $price;
            $totalLentUSD += $balance['total_lent'] * $price;
            $totalBorrowedUSD += $balance['total_borrowed'] * $price;
            $totalCollateralUSD += $balance['total_collateral_reserved'] * $price;
        }

        return [
            'balances_by_asset' => array_values($balances),
            'summary' => [
                'total_value_usd' => round($totalValueUSD, 2),
                'total_available_usd' => round($totalAvailableUSD, 2),
                'total_lent_usd' => round($totalLentUSD, 2),
                'total_borrowed_usd' => round($totalBorrowedUSD, 2),
                'total_collateral_locked_usd' => round($totalCollateralUSD, 2),
            ],
        ];
    }

    /**
 * Cancel a pending borrow request.
 */
public function cancelBorrowRequest(User $user, int $requestId)
{
    DB::beginTransaction();

    try {
        $borrowRequest = LoanBorrowRequest::where('id', $requestId)
            ->where('user_id', $user->id)
            ->lockForUpdate()
            ->first();

        if (!$borrowRequest) {
            throw new \Exception('Borrow request not found.');
        }

        if ($borrowRequest->status !== 'pending') {
            throw new \Exception(
                'Only pending borrow requests can be cancelled.'
            );
        }

        // Safety check in case the request has already been matched
        if (Loan::where('borrow_request_id', $borrowRequest->id)->exists()) {
            throw new \Exception(
                'This borrow request has already been matched and cannot be cancelled.'
            );
        }

        $borrowRequest->update([
            'status' => 'cancelled',
        ]);

        Log::info('Borrow request cancelled.', [
            'borrow_request_id' => $borrowRequest->id,
            'user_id' => $user->id,
            'asset' => $borrowRequest->asset,
            'amount' => $borrowRequest->amount,
            'cancelled_at' => now()->toDateTimeString(),
        ]);

        DB::commit();

        return $borrowRequest->fresh();

    } catch (\Throwable $e) {
        DB::rollBack();

        Log::error('Failed to cancel borrow request.', [
            'borrow_request_id' => $requestId,
            'user_id' => $user->id,
            'message' => $e->getMessage(),
        ]);

        throw $e;
    }
}
}
