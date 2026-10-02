<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('wallet_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('referral_id')->nullable()->constrained('referrals')->nullOnDelete();
            $table->decimal('amount', 28, 8);
            $table->string('currency_code', 10)->default('NGN');
            $table->enum('transaction_type', ['earn', 'withdraw'])->index();
            $table->enum('status', ['pending', 'completed', 'rejected'])->default('completed')->index();
            $table->decimal('balance_before', 28, 8)->default(0);
            $table->decimal('balance_after', 28, 8)->default(0);
            $table->decimal('pending_before', 28, 8)->default(0);
            $table->decimal('pending_after', 28, 8)->default(0);
            $table->string('reference')->unique();
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('transaction_at')->useCurrent();
            $table->timestamps();
        });

        Schema::create('wallet_withdrawal_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('wallet_ledger_id')->nullable()->constrained('wallet_ledger')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('reference')->unique();
            $table->decimal('amount', 28, 8);
            $table->string('currency_code', 10)->default('NGN');
            $table->string('bank_name');
            $table->string('account_number', 30);
            $table->string('account_name');
            $table->enum('status', ['pending', 'completed', 'rejected'])->default('pending')->index();
            $table->text('admin_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        if (Schema::hasTable('referral_earnings')) {
            $runningBalances = [];

            foreach (
                DB::table('referral_earnings')
                    ->orderBy('user_id')
                    ->orderBy('created_at')
                    ->orderBy('id')
                    ->cursor() as $earning
            ) {
                $before = $runningBalances[$earning->user_id] ?? '0';
                $after = bcadd((string) $before, (string) $earning->amount, 8);

                DB::table('wallet_ledger')->insert([
                    'user_id' => $earning->user_id,
                    'referral_id' => $earning->referral_id,
                    'amount' => $earning->amount,
                    'currency_code' => $earning->currency_code ?? 'NGN',
                    'transaction_type' => 'earn',
                    'status' => 'completed',
                    'balance_before' => $before,
                    'balance_after' => $after,
                    'pending_before' => '0',
                    'pending_after' => '0',
                    'reference' => 'REF-EARN-LEGACY-' . $earning->id,
                    'description' => $earning->description,
                    'metadata' => json_encode([
                        'legacy_referral_earning_id' => $earning->id,
                        'legacy_type' => $earning->type,
                    ]),
                    'transaction_at' => $earning->created_at ?? now(),
                    'created_at' => $earning->created_at ?? now(),
                    'updated_at' => $earning->updated_at ?? now(),
                ]);

                $runningBalances[$earning->user_id] = $after;
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallet_withdrawal_requests');
        Schema::dropIfExists('wallet_ledger');
    }
};
