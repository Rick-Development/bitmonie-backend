<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();

            // Recipient
            $table->foreignId('firebase_token_id')->nullable()->constrained('firebase_tokens') ->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Notification information
            $table->string('template_key')->nullable();
            $table->string('reference_id')->nullable();           // ← Added
            $table->string('title');
            $table->text('body');

            // Status
            $table->enum('status', [
                'pending',
                'sending',
                'sent',
                'failed',
                'delivered',
                'read',
            ])->default('pending');

            // FCM response
            $table->string('message_id')->nullable();
            $table->text('error')->nullable();
            $table->json('response')->nullable();

            // Timestamps for state changes
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('user_id');
            $table->index('firebase_token_id');
            $table->index('status');
            $table->index('template_key');
            $table->index('message_id');
            $table->index('reference_id');                        // ← Added
          $table->index(
    [
        'firebase_token_id',
        'template_key',
        'reference_id'
    ],
    'notif_log_dedup_idx'
);// Composite index for deduplication
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};