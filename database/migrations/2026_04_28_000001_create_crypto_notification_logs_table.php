<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('crypto_notification_logs', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->default('quidax')->index();
            $table->string('event_name')->nullable()->index();
            $table->string('event_id')->nullable()->index();
            $table->string('dedupe_key')->unique();
            $table->string('transaction_reference')->nullable()->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('payload')->nullable();
            $table->string('notification_type')->nullable()->index();
            $table->string('status')->default('processing')->index();
            $table->timestamp('sent_at')->nullable();
            $table->boolean('duplicate')->default(false);
            $table->unsignedInteger('duplicate_count')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('crypto_notification_logs');
    }
};
