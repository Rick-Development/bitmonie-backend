<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_commission_settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('commission_rate_percent', 8, 4)->default('20.0000');
            $table->boolean('auto_credit')->default(true);
            $table->timestamps();
        });

        Schema::create('commission_wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('currency_code', 20)->default('NGN');
            $table->decimal('available_balance', 36, 18)->default('0');
            $table->decimal('pending_balance', 36, 18)->default('0');
            $table->decimal('withdrawn_balance', 36, 18)->default('0');
            $table->timestamps();

            $table->unique(['user_id', 'currency_code']);
        });

        Schema::create('referral_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referrer_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('referred_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('transaction_id');
            $table->string('transaction_type', 120);
            $table->string('currency_code', 20)->default('NGN');
            $table->decimal('platform_fee_amount', 36, 18);
            $table->decimal('commission_rate_percent', 8, 4)->default('20.0000');
            $table->decimal('commission_amount', 36, 18);
            $table->string('status', 40)->default('credited')->index();
            $table->json('metadata')->nullable();
            $table->timestamp('credited_at')->nullable();
            $table->timestamp('flagged_at')->nullable();
            $table->timestamps();

            $table->unique(['transaction_id', 'transaction_type']);
            $table->index(['referrer_user_id', 'status']);
            $table->index(['referred_user_id', 'transaction_type']);
        });

        Schema::create('commission_wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commission_wallet_id')->constrained('commission_wallets')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('referral_commission_id')->nullable()->constrained('referral_commissions')->nullOnDelete();
            $table->string('type', 60);
            $table->string('status', 40)->default('successful');
            $table->string('currency_code', 20)->default('NGN');
            $table->decimal('amount', 36, 18);
            $table->decimal('balance_after', 36, 18)->nullable();
            $table->string('reference')->unique();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        DB::table('referral_commission_settings')->insert([
            'commission_rate_percent' => '20.0000',
            'auto_credit' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_wallet_transactions');
        Schema::dropIfExists('referral_commissions');
        Schema::dropIfExists('commission_wallets');
        Schema::dropIfExists('referral_commission_settings');
    }
};
