<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ramp_transactions')) {
            return;
        }

        if (!Schema::hasColumn('ramp_transactions', 'provider_transaction_id')) {
            Schema::table('ramp_transactions', function (Blueprint $table) {
                $table->string('provider_transaction_id')->nullable()->index()->after('reference');
            });
        }

        if (!Schema::hasColumn('ramp_transactions', 'transaction_hash')) {
            Schema::table('ramp_transactions', function (Blueprint $table) {
                $table->string('transaction_hash')->nullable()->index()->after('provider_transaction_id');
            });
        }

        if (!Schema::hasColumn('ramp_transactions', 'provider_status')) {
            Schema::table('ramp_transactions', function (Blueprint $table) {
                $table->string('provider_status')->nullable()->index()->after('status');
            });
        }

        if (!Schema::hasColumn('ramp_transactions', 'provider_status_checked_at')) {
            Schema::table('ramp_transactions', function (Blueprint $table) {
                $table->timestamp('provider_status_checked_at')->nullable()->after('provider_status');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('ramp_transactions')) {
            return;
        }

        Schema::table('ramp_transactions', function (Blueprint $table) {
            foreach (['provider_status_checked_at', 'provider_status', 'transaction_hash', 'provider_transaction_id'] as $column) {
                if (Schema::hasColumn('ramp_transactions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
