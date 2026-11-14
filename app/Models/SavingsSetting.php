<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Singleton product settings for Flexi / Auto / Target / SafeLock / EduSave.
 * Falls back to config/savings.php when the table is missing or empty.
 */
class SavingsSetting extends Model
{
    protected $table = 'savings_settings';

    protected $fillable = [
        'min_transaction',
        'max_transaction',
        'max_target_amount',
        'max_active_targets',
        'max_auto_plans',
        'flexi_enabled',
        'flexi_interest_enabled',
        'flexi_interest_apr_percent',
        'auto_enabled',
        'target_enabled',
        'target_early_closure_fee_percent',
        'safelock_enabled',
        'safelock_early_break_penalty_percent',
        'edusave_enabled',
        'edusave_min_amount',
        'edusave_termly_months',
    ];

    protected $casts = [
        'min_transaction' => 'decimal:2',
        'max_transaction' => 'decimal:2',
        'max_target_amount' => 'decimal:2',
        'max_active_targets' => 'integer',
        'max_auto_plans' => 'integer',
        'flexi_enabled' => 'boolean',
        'flexi_interest_enabled' => 'boolean',
        'flexi_interest_apr_percent' => 'decimal:2',
        'auto_enabled' => 'boolean',
        'target_enabled' => 'boolean',
        'target_early_closure_fee_percent' => 'decimal:2',
        'safelock_enabled' => 'boolean',
        'safelock_early_break_penalty_percent' => 'decimal:2',
        'edusave_enabled' => 'boolean',
        'edusave_min_amount' => 'decimal:2',
        'edusave_termly_months' => 'integer',
    ];

    public static function cacheKey(): string
    {
        return 'savings_settings.current';
    }

    public static function forgetCache(): void
    {
        Cache::forget(self::cacheKey());
    }

    public static function current(): self
    {
        if (! Schema::hasTable('savings_settings')) {
            return self::fromConfig();
        }

        return Cache::remember(self::cacheKey(), 60, function () {
            $row = static::query()->first();
            if ($row) {
                return $row;
            }

            return static::query()->create(self::defaultsFromConfig());
        });
    }

    /** @return array<string, mixed> */
    public static function defaultsFromConfig(): array
    {
        return [
            'min_transaction' => config('savings.limits.min_transaction', '100.00'),
            'max_transaction' => config('savings.limits.max_transaction', '5000000.00'),
            'max_target_amount' => config('savings.limits.max_target_amount', '1000000000.00'),
            'max_active_targets' => (int) config('savings.limits.max_active_targets', 50),
            'max_auto_plans' => (int) config('savings.limits.max_auto_plans', 20),
            'flexi_enabled' => true,
            'flexi_interest_enabled' => (bool) config('savings.flexi.interest_enabled', true),
            'flexi_interest_apr_percent' => config('savings.flexi.interest_apr_percent', '60'),
            'auto_enabled' => true,
            'target_enabled' => true,
            'target_early_closure_fee_percent' => config('savings.target.early_closure_fee_percent', '2.00'),
            'safelock_enabled' => true,
            'safelock_early_break_penalty_percent' => '1.00',
            'edusave_enabled' => true,
            'edusave_min_amount' => '100.00',
            'edusave_termly_months' => 4,
        ];
    }

    protected static function fromConfig(): self
    {
        $m = new static();
        $m->forceFill(self::defaultsFromConfig());
        $m->exists = false;

        return $m;
    }

    public function minTransaction(): string
    {
        return number_format((float) $this->min_transaction, 2, '.', '');
    }

    public function maxTransaction(): string
    {
        return number_format((float) $this->max_transaction, 2, '.', '');
    }

    public function maxTargetAmount(): string
    {
        return number_format((float) $this->max_target_amount, 2, '.', '');
    }

    public function flexiApr(): string
    {
        return number_format((float) $this->flexi_interest_apr_percent, 2, '.', '');
    }

    /**
     * The admin-configured annual yield used by unified Flexi, Auto and Target
     * accounts. Interest accrual currently uses this same rate for all three.
     */
    public function unifiedApy(): string
    {
        return $this->flexiApr();
    }

    public function targetEarlyClosureFeePercent(): string
    {
        return number_format((float) $this->target_early_closure_fee_percent, 2, '.', '');
    }

    public function safelockBreakPenaltyPercent(): string
    {
        return number_format((float) $this->safelock_early_break_penalty_percent, 2, '.', '');
    }

    public function edusaveMinAmount(): string
    {
        return number_format((float) $this->edusave_min_amount, 2, '.', '');
    }
}
