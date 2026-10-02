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
        Schema::create('promos', function (Blueprint $table): void {
            $table->id();

            /*
             * Basic promo information.
             */
            $table->string('name');
            $table->text('description')->nullable();

            /*
             * Lifecycle:
             *
             * pending
             * active
             * inactive
             */
            $table->string('status')
                ->default('pending')
                ->index();

            /*
             * Promo availability period.
             */
            $table->dateTime('start_date');
            $table->dateTime('end_date');

            /*
             * Percentage used by the expiry-payment logic.
             */
            $table->decimal(
                'expiry_payment_percentage',
                5,
                2
            )->nullable();

            /*
             * Administration audit fields.
             *
             * Nullable because the referenced administrator could
             * potentially be deleted later.
             */
            $table->unsignedBigInteger('created_by')
                ->nullable()
                ->index();

            $table->unsignedBigInteger('updated_by')
                ->nullable()
                ->index();

            $table->unsignedBigInteger('deleted_by')
                ->nullable()
                ->index();

            $table->timestamps();

            /*
             * Soft-delete timestamp.
             */
            $table->softDeletes();

            /*
             * Date indexes improve lifecycle queries such as:
             *
             * WHERE status = 'pending'
             * AND start_date <= NOW()
             *
             * and:
             *
             * WHERE status = 'active'
             * AND end_date < NOW()
             */
            $table->index([
                'status',
                'start_date',
                'end_date',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promos');
    }
};