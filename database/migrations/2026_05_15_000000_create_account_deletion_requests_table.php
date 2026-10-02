<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('account_deletion_requests', function (Blueprint $table) {
            $table->id();
            $table->string('full_name', 120);
            $table->string('email');
            $table->string('phone_number', 32);
            $table->text('reason')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->string('reference_id', 40)->unique();
            $table->string('submitted_ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['email', 'created_at']);
            $table->index(['phone_number', 'created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('account_deletion_requests');
    }
};
