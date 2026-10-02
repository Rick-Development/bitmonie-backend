<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('autosave_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->enum('mode', ['scheduled', 'percentage', 'both']);
            $table->decimal('amount', 36, 8)->nullable();
            $table->decimal('percentage', 8, 4)->nullable();
            $table->enum('frequency', ['daily', 'weekly', 'monthly'])->nullable();
            $table->decimal('goal_amount', 36, 8)->nullable();
            $table->date('maturity_date')->nullable();
            $table->enum('status', ['active', 'paused', 'cancelled'])->default('active')->index();
            $table->decimal('balance', 36, 8)->default(0);
            $table->timestamp('next_due_at')->nullable()->index();
            $table->timestamp('last_deducted_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'mode']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('autosave_plans');
    }
};
