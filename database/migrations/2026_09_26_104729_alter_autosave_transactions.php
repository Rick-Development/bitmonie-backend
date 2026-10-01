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
        Schema::table('autosave_transactions', function (Blueprint $table) {
            /*
             * SafeHaven/provider transfer status.
             *
             * Examples:
             * completed
             * processing
             * created
             * initiated
             * failed
             * canceled
             */
            $table->string('provider_status', 40)
                ->nullable()
                ->after('status');

            /*
             * Unique provider-side transaction identifier.
             *
             * For SafeHaven this should contain:
             *
             * data._id
             *
             * This is the primary identifier used when reconciling
             * a pending autosave against the provider transfer.
             */
            $table->string('provider_transaction_id', 100)
                ->nullable()
                ->after('provider_status');

            /*
             * Status of any local compensating reversal.
             *
             * Examples:
             * null
             * processing
             * completed
             * failed
             */
            $table->string('reversal_status', 40)
                ->nullable()
                ->after('provider_transaction_id');

            /*
             * When the local compensating reversal was completed.
             */
            $table->timestamp('reversed_at')
                ->nullable()
                ->after('reversal_status');

            /*
             * Reason for the reversal.
             */
            $table->string('reversal_reason', 191)
                ->nullable()
                ->after('reversed_at');

            /*
             * Indexes for reconciliation and reporting.
             */
            $table->index('provider_status');

            $table->index('provider_transaction_id');

            $table->index('reversal_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('autosave_transactions', function (Blueprint $table) {
            $table->dropIndex([
                'provider_status',
            ]);

            $table->dropIndex([
                'provider_transaction_id',
            ]);

            $table->dropIndex([
                'reversal_status',
            ]);

            $table->dropColumn([
                'provider_status',
                'provider_transaction_id',
                'reversal_status',
                'reversed_at',
                'reversal_reason',
            ]);
        });
    }
};