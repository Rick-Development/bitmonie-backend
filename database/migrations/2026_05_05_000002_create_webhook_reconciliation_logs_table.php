<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('webhook_reconciliation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_event_log_id')->nullable()->constrained('webhook_event_logs')->nullOnDelete();
            $table->string('provider')->index();
            $table->string('action')->index();
            $table->string('transaction_type')->nullable()->index();
            $table->unsignedBigInteger('transaction_id')->nullable()->index();
            $table->string('transaction_reference')->nullable()->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('currency')->nullable()->index();
            $table->decimal('amount', 28, 8)->nullable();
            $table->string('status_before')->nullable();
            $table->string('status_after')->nullable();
            $table->decimal('balance_before', 28, 8)->nullable();
            $table->decimal('balance_after', 28, 8)->nullable();
            $table->boolean('duplicate')->default(false)->index();
            $table->json('changes')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('webhook_reconciliation_logs');
    }
};
