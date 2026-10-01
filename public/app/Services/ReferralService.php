<?php

namespace App\Services;

use App\Models\User;
use App\Models\Referral;
use App\Models\ReferralEarning;
use App\Services\WalletService;
use Illuminate\Support\Str;
use App\Providers\Admin\BasicSettingsProvider;
use Illuminate\Support\Facades\DB;
use Exception;

class ReferralService
{
    const CURRENCY = 'NGN';

    protected ReferralWalletService $referralWalletService;

    public function __construct(ReferralWalletService $referralWalletService)
    {
        $this->referralWalletService = $referralWalletService;
    }

    /**
     * Generate a unique referral code for a user
     */
    public function generateReferralCode(User $user): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (User::where('referral_code', $code)->exists());

        $user->update(['referral_code' => $code]);

        return $code;
    }

    /**
     * Validate a referral code
     */
    public function validateReferralCode(string $code): ?User
    {
        return User::where('referral_code', $code)->first();
    }

    /**
     * Track a referral (called during user registration)
     */
    public function trackReferral(User $referred, string $referralCode): ?Referral
    {
        $referrer = $this->validateReferralCode($referralCode);

        if (!$referrer || $referrer->id === $referred->id) {
            return null;
        }

        // Check if referral already exists
        $existing = Referral::where('referrer_id', $referrer->id)
            ->where('referred_id', $referred->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        return Referral::create([
            'referrer_id' => $referrer->id,
            'referred_id' => $referred->id,
            'status' => 'pending',
        ]);
    }

    /**
     * Complete a referral and credit signup bonus
     * Called when referred user completes KYC or first transaction
     */
    public function completeReferral(User $referred, $depositAmount = null): bool
    {
        $referral = Referral::where('referred_id', $referred->id)
            ->where('status', 'pending')
            ->first();

        if (!$referral) {
            return false;
        }

        $minDeposit = \App\Providers\Admin\BasicSettingsProvider::get()->referral_min_deposit ?? 0;
        
        if ($minDeposit > 0 && $depositAmount !== null) {
            if (bccomp((string)$depositAmount, (string)$minDeposit, 8) < 0) {
                // Deposit is less than minimum required, don't complete the referral yet
                return false;
            }
        }

        return DB::transaction(function () use ($referral) {
            // Update referral status
            $referral->update(['status' => 'completed']);

            // Credit signup bonus to referrer
            $this->creditSignupBonus($referral);

            return true;
        });
    }

    /**
     * Credit signup bonus to referrer
     */
    protected function creditSignupBonus(Referral $referral): void
    {
        $referrer = $referral->referrer;
        $referred = $referral->referred;

        // Get or create NGN wallet
        $wallet = $referrer->wallets()->where('currency_code', self::CURRENCY)->first();

        if (!$wallet) {
            throw new Exception("Referrer does not have an NGN wallet.");
        }

        $signupBonus = BasicSettingsProvider::get()->referral_bonus ?? 500;

        // Credit the wallet
        $reference = 'REF-BONUS-' . strtoupper(Str::random(10));
        $this->referralWalletService->recordEarning($referrer, (string) $signupBonus, $reference, [
            'type' => 'referral_bonus',
            'description' => "Referral signup bonus for {$referred->username}",
            'referred_user_id' => $referred->id,
        ], $referral);

        // Record the earning
        ReferralEarning::create([
            'user_id' => $referrer->id,
            'referral_id' => $referral->id,
            'amount' => $signupBonus,
            'currency_code' => self::CURRENCY,
            'type' => 'signup_bonus',
            'description' => "Signup bonus for referring {$referred->username}",
        ]);

        // Update referral status to rewarded
        $referral->update(['status' => 'rewarded']);
    }

    /**
     * Get referral statistics for a user
     */
    public function getStatistics(User $user): array
    {
        $totalReferrals = Referral::where('referrer_id', $user->id)->count();
        $pendingReferrals = Referral::where('referrer_id', $user->id)
            ->where('status', 'pending')
            ->count();
        $completedReferrals = Referral::where('referrer_id', $user->id)
            ->whereIn('status', ['completed', 'rewarded'])
            ->count();

        $totalEarnings = ReferralEarning::where('user_id', $user->id)
            ->where('currency_code', self::CURRENCY)
            ->sum('amount');

        return [
            'referral_code' => $user->referral_code,
            'total_referrals' => $totalReferrals,
            'pending_referrals' => $pendingReferrals,
            'completed_referrals' => $completedReferrals,
            'total_earnings' => (float)$totalEarnings,
            'currency' => self::CURRENCY,
        ];
    }

    /**
     * Get list of referrals for a user
     */
    public function getReferralList(User $user, int $perPage = 20)
    {
        return Referral::where('referrer_id', $user->id)
            ->with(['referred:id,username,created_at', 'earnings'])
            ->latest()
            ->paginate($perPage)
            ->through(function ($referral) {
                $earned = $referral->earnings->sum('amount');
                return [
                    'username' => $this->maskUsername($referral->referred->username),
                    'status' => $referral->status,
                    'joined_at' => $referral->referred->created_at->format('Y-m-d'),
                    'earned' => (float)$earned,
                ];
            });
    }

    /**
     * Get referral earnings for a user
     */
    public function getEarnings(User $user, int $perPage = 20)
    {
        return ReferralEarning::where('user_id', $user->id)
            ->with('referral.referred:id,username')
            ->latest()
            ->paginate($perPage)
            ->through(function ($earning) {
                return [
                    'amount' => (float)$earning->amount,
                    'currency' => $earning->currency_code,
                    'type' => $earning->type,
                    'description' => $earning->description,
                    'date' => $earning->created_at->format('Y-m-d H:i:s'),
                ];
            });
    }

    /**
     * Mask username for privacy (e.g., john*** )
     */
    protected function maskUsername(string $username): string
    {
        if (strlen($username) <= 4) {
            return substr($username, 0, 2) . '***';
        }
        return substr($username, 0, 4) . '***';
    }
}
