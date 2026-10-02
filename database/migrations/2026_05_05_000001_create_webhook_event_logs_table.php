<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('webhook_event_logs', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->index();
            $table->string('endpoint')->nullable()->index();
            $table->string('event_name')->nullable()->index();
            $table->string('event_id')->nullable()->index();
            $table->string('dedupe_key')->nullable()->index();
            $table->string('transaction_reference')->nullable()->index();
            $table->string('transaction_type')->nullable()->index();
            $table->string('provider_status')->nullable()->index();
            $table->string('internal_status')->nullable()->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('payload')->nullable();
            $table->longText('raw_payload')->nullable();
            $table->json('headers')->nullable();
            $table->string('processing_status')->default('received')->index();
            $table->unsignedSmallInteger('http_status')->default(200);
            $table->boolean('duplicate')->default(false)->index();
            $table->unsignedBigInteger('duplicate_of_id')->nullable()->index();
            $table->text('failure_reason')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('webhook_event_logs');
    }
};
