<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('savings_settings', function (Blueprint $table) {
            $table->id();

            // Shared limits
            $table->decimal('min_transaction', 20, 2)->default(100);
            $table->decimal('max_transaction', 20, 2)->default(5000000);
            $table->decimal('max_target_amount', 20, 2)->default(1000000000);
            $table->unsignedInteger('max_active_targets')->default(50);
            $table->unsignedInteger('max_auto_plans')->default(20);

            // Flexi
            $table->boolean('flexi_enabled')->default(true);
            $table->boolean('flexi_interest_enabled')->default(true);
            $table->decimal('flexi_interest_apr_percent', 8, 2)->default(60);

            // Auto Save
            $table->boolean('auto_enabled')->default(true);

            // Target
            $table->boolean('target_enabled')->default(true);
            $table->decimal('target_early_closure_fee_percent', 8, 2)->default(2);

            // SafeLock (plans stay in savings_plans; these are global knobs)
            $table->boolean('safelock_enabled')->default(true);
            $table->decimal('safelock_early_break_penalty_percent', 8, 2)->default(1);

            // EduSave
            $table->boolean('edusave_enabled')->default(true);
            $table->decimal('edusave_min_amount', 20, 2)->default(100);
            $table->unsignedTinyInteger('edusave_termly_months')->default(4);

            $table->timestamps();
        });

        DB::table('savings_settings')->insert([
            'min_transaction' => config('savings.limits.min_transaction', 100),
            'max_transaction' => config('savings.limits.max_transaction', 5000000),
            'max_target_amount' => config('savings.limits.max_target_amount', 1000000000),
            'max_active_targets' => (int) config('savings.limits.max_active_targets', 50),
            'max_auto_plans' => (int) config('savings.limits.max_auto_plans', 20),
            'flexi_enabled' => true,
            'flexi_interest_enabled' => (bool) config('savings.flexi.interest_enabled', true),
            'flexi_interest_apr_percent' => config('savings.flexi.interest_apr_percent', 60),
            'auto_enabled' => true,
            'target_enabled' => true,
            'target_early_closure_fee_percent' => config('savings.target.early_closure_fee_percent', 2),
            'safelock_enabled' => true,
            'safelock_early_break_penalty_percent' => 1,
            'edusave_enabled' => true,
            'edusave_min_amount' => 100,
            'edusave_termly_months' => 4,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('savings_settings');
    }
};
