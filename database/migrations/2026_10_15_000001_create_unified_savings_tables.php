<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Unified savings domain: accounts (Flexi / Auto / Target), an append-only
 * entry ledger, recurring plans, persisted scheduled executions and audit
 * events. Purely additive: legacy tables are left untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('savings_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('product', 16);                       // FLEXI | AUTO | TARGET
            $table->string('title', 120);
            $table->text('description')->nullable();
            $table->string('category', 60)->nullable();
            $table->char('currency', 3)->default('NGN');
            $table->decimal('balance', 20, 2)->default(0);
            $table->decimal('held', 20, 2)->default(0);           // holds / pending withdrawals
            $table->decimal('total_deposited', 20, 2)->default(0);
            $table->decimal('total_withdrawn', 20, 2)->default(0);
            $table->decimal('interest_earned', 20, 2)->default(0);
            $table->decimal('target_amount', 20, 2)->nullable();
            $table->date('start_date')->nullable();
            $table->date('deadline')->nullable();
            $table->string('status', 16)->default('ACTIVE');
            $table->unsignedBigInteger('flexi_key')->nullable();   // = user_id for FLEXI, enforces one per user
            $table->string('legacy_ref', 60)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->unique('flexi_key');
            $table->unique('legacy_ref');
            $table->index(['user_id', 'product', 'status']);
            $table->index(['status', 'deadline']);
        });

        Schema::create('savings_recurring_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('savings_accounts')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->decimal('amount', 20, 2);
            $table->string('frequency', 10);                      // DAILY | WEEKLY | MONTHLY
            $table->unsignedTinyInteger('day_of_week')->nullable();   // 0 (Sun) - 6 (Sat)
            $table->unsignedTinyInteger('day_of_month')->nullable();  // 1 - 31 (clamped to month end)
            $table->string('run_time', 5)->default('08:00');
            $table->string('timezone', 64)->default('Africa/Lagos');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('funding_source', 16)->default('WALLET');
            $table->string('status', 16)->default('ACTIVE');
            $table->timestamp('next_run_at')->nullable();         // UTC
            $table->timestamp('last_success_at')->nullable();
            $table->timestamps();

            $table->unique('account_id');
            $table->index(['status', 'next_run_at']);
            $table->index('user_id');
        });

        Schema::create('savings_executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('savings_recurring_plans')->restrictOnDelete();
            $table->foreignId('account_id')->constrained('savings_accounts')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->decimal('amount', 20, 2);
            $table->timestamp('scheduled_for');                   // UTC
            $table->string('status', 16)->default('PENDING');     // PENDING|PROCESSING|COMPLETED|FAILED|SKIPPED
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->string('failure_code', 40)->nullable();
            $table->string('failure_message', 255)->nullable();
            $table->unsignedBigInteger('entry_id')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['plan_id', 'scheduled_for']);         // one execution per slot
            $table->index(['status', 'next_attempt_at']);
            $table->index(['account_id', 'scheduled_for']);
        });

        Schema::create('savings_entries', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('account_id')->constrained('savings_accounts')->restrictOnDelete();
            $table->string('type', 20);       // DEPOSIT|WITHDRAWAL|TRANSFER_IN|TRANSFER_OUT|INTEREST|FEE|REVERSAL|OPENING
            $table->string('direction', 6);   // CREDIT | DEBIT
            $table->decimal('amount', 20, 2);
            $table->decimal('balance_after', 20, 2)->nullable();
            $table->string('status', 16)->default('COMPLETED');   // PENDING|PROCESSING|COMPLETED|FAILED|REVERSED
            $table->string('source', 24)->nullable();             // wallet|manual|scheduled|flexi|system
            $table->string('group_ref', 40)->nullable();          // links both legs of a transfer
            $table->string('idempotency_key', 80)->nullable();
            $table->string('request_hash', 64)->nullable();
            $table->unsignedBigInteger('reverses_entry_id')->nullable();
            $table->unsignedBigInteger('execution_id')->nullable();
            $table->string('narration', 191)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'idempotency_key']);
            $table->unique('reverses_entry_id');                  // an entry can be reversed once
            $table->unique('execution_id');                       // a scheduled slot credits once
            $table->index(['account_id', 'created_at']);
            $table->index(['user_id', 'type', 'status']);
            $table->index('group_ref');
        });

        Schema::create('savings_audit_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('account_id')->nullable();
            $table->string('actor', 40)->default('user');
            $table->string('event', 60);
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['account_id', 'created_at']);
            $table->index(['user_id', 'event']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('savings_audit_events');
        Schema::dropIfExists('savings_entries');
        Schema::dropIfExists('savings_executions');
        Schema::dropIfExists('savings_recurring_plans');
        Schema::dropIfExists('savings_accounts');
    }
};
