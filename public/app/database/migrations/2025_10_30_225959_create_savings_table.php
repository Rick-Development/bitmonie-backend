<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('savings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // $table->string('title');
            $table->decimal('balance', 15, 2)->default(0);
            // $table->decimal('goal_target', 15, 2)->nullable();
            // $table->decimal('interest_rate', 5, 2)->nullable();
            // $table->timestamp('locked_until')->nullable();
            // $table->enum('status', ['active', 'frozen', 'closed'])->default('active');
            $table->timestamps();
            $table->unique(['user_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('savings');
    }
};
