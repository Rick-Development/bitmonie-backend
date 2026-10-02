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
        Schema::create('savings_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., "10 Days", "30 Days - 12%"
            $table->string('slug')->unique();
            $table->integer('duration_days'); // e.g., 10, 30, 60
            $table->decimal('interest_rate', 5, 2); // e.g., 12.00
            $table->decimal('min_amount', 20, 8)->default(0);
            $table->decimal('max_amount', 20, 8)->nullable();
            $table->boolean('status')->default(true);
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
        Schema::dropIfExists('savings_plans');
    }
};
