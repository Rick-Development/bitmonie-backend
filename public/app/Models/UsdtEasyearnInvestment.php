<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class UsdtEasyearnInvestment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'amount',
        'duration_months',
        'interest_rate',
        'auto_compound',
        'start_date',
        'end_date',
        'status',
        'total_interest_earned',
        'last_interest_credit_date',
    ];

    protected $casts = [
        'amount' => 'decimal:8',
        'interest_rate' => 'decimal:2',
        'total_interest_earned' => 'decimal:8',
        'auto_compound' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
        'last_interest_credit_date' => 'date',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function interestCredits()
    {
        return $this->hasMany(UsdtEasyearnInterestCredit::class, 'investment_id');
    }

    // Business Logic Methods
    
    /**
     * Calculate monthly interest based on principal and rate
     */
    public function calculateMonthlyInterest()
    {
        return bcmul($this->amount, bcdiv($this->interest_rate, 100, 10), 8);
    }

    /**
     * Calculate daily interest for display (0.33% for 10% monthly)
     */
    public function calculateDailyDisplay()
    {
        $monthlyInterest = $this->calculateMonthlyInterest();
        return bcdiv($monthlyInterest, 30, 8);
    }

    /**
     * Check if user can withdraw interest
     */
    public function canWithdrawInterest()
    {
        // Can only withdraw if:
        // 1. Investment is active
        // 2. Auto-compound is disabled
        // 3. Has accumulated interest
        return $this->status === 'active' 
            && !$this->auto_compound 
            && bccomp($this->total_interest_earned, 0, 8) > 0;
    }

    /**
     * Check if user can withdraw principal
     */
    public function canWithdrawPrincipal()
    {
        // Can only withdraw principal if investment has matured
        return $this->isMatured();
    }

    /**
     * Get total withdrawable interest (for non-auto-compound)
     */
    public function getTotalWithdrawableInterest()
    {
        if ($this->auto_compound) {
            return '0.00000000';
        }
        return $this->total_interest_earned;
    }

    /**
     * Get total withdrawable amount at maturity
     */
    public function getTotalWithdrawableAtMaturity()
    {
        if ($this->auto_compound) {
            // Principal + all accumulated interest
            return bcadd($this->amount, $this->total_interest_earned, 8);
        }
        // Only principal (interest already withdrawn monthly)
        return $this->amount;
    }

    /**
     * Check if investment has matured
     */
    public function isMatured()
    {
        return Carbon::now()->greaterThanOrEqualTo($this->end_date);
    }

    /**
     * Check if investment is eligible for interest credit
     */
    public function isEligibleForCredit()
    {
        if ($this->status !== 'active') {
            return false;
        }

        // Check if at least 1 month has passed since start or last credit
        $referenceDate = $this->last_interest_credit_date ?? $this->start_date;
        return Carbon::now()->greaterThanOrEqualTo($referenceDate->addMonth());
    }

    /**
     * Get months completed since start
     */
    public function getMonthsCompleted()
    {
        return $this->start_date->diffInMonths(Carbon::now());
    }

    /**
     * Get days remaining until maturity
     */
    public function getDaysRemaining()
    {
        if ($this->isMatured()) {
            return 0;
        }
        return Carbon::now()->diffInDays($this->end_date);
    }

    // Scopes
    
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeMatured($query)
    {
        return $query->where('end_date', '<=', Carbon::now());
    }

    public function scopeEligibleForCredit($query)
    {
        return $query->active()->where(function($q) {
            $q->whereNull('last_interest_credit_date')
              ->where('start_date', '<=', Carbon::now()->subMonth())
              ->orWhere('last_interest_credit_date', '<=', Carbon::now()->subMonth());
        });
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
}
