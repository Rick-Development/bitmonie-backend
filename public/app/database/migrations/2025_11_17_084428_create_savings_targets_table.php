<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('savings_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('savings_id')->constrained()->cascadeOnDelete();
            $table->string('plan_id')->nullable();
            $table->string('target_title')->nullable();
            $table->decimal('target_amount', 15, 2)->nullable();
            $table->timestamp('locked_until')->nullable();
            $table->enum('status', ['active', 'frozen', 'closed'])->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('savings_targets');
    }
};
