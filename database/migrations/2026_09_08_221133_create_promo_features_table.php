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
        Schema::create('promo_features', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('promo_id')
                ->constrained('promos')
                ->cascadeOnDelete();

            $table->foreignId('feature_id')
                ->constrained('features')
                ->cascadeOnDelete();

            $table->timestamps();

            /*
             * The same feature cannot be assigned to the same
             * promo more than once.
             *
             * IMPORTANT:
             *
             * Do NOT make feature_id unique by itself.
             *
             * A feature can belong to multiple promos over time.
             * Expired/inactive promo assignments are released by
             * PromoService.
             */
            $table->unique([
                'promo_id',
                'feature_id',
            ]);

            /*
             * Useful for finding all promo assignments for
             * a particular feature.
             */
            $table->index('feature_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promo_features');
    }
};