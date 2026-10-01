<?php

declare(strict_types=1);

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
        Schema::create('features', function (Blueprint $table): void {
            $table->id();

            /*
             * Human-readable feature name.
             *
             * Examples:
             *
             * Cashback
             * Virtual Card
             * Crypto Swap
             * International Transfer
             */
            $table->string('name')->unique();

            /*
             * Optional explanation of what the feature represents.
             */
            $table->text('description')->nullable();

            /*
             * Master feature status.
             *
             * This is separate from promo status.
             *
             * A feature can be globally active while being
             * temporarily assigned to a promo.
             */
            $table->string('status')
                ->default('active')
                ->index();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('features');
    }
};