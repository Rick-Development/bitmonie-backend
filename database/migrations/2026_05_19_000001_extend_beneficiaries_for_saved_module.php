<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('beneficiaries', function (Blueprint $table) {
            if (!Schema::hasColumn('beneficiaries', 'beneficiary_name')) {
                $table->string('beneficiary_name')->nullable()->after('slug')->index();
            }

            if (!Schema::hasColumn('beneficiaries', 'transaction_type')) {
                $table->string('transaction_type', 80)->nullable()->after('beneficiary_name')->index();
            }

            if (!Schema::hasColumn('beneficiaries', 'details')) {
                $table->json('details')->nullable()->after('transaction_type');
            }

            if (!Schema::hasColumn('beneficiaries', 'is_favorite')) {
                $table->boolean('is_favorite')->default(false)->after('details')->index();
            }

            if (!Schema::hasColumn('beneficiaries', 'pinned_at')) {
                $table->timestamp('pinned_at')->nullable()->after('is_favorite');
            }
        });
    }

    public function down(): void
    {
        Schema::table('beneficiaries', function (Blueprint $table) {
            foreach (['pinned_at', 'is_favorite', 'details', 'transaction_type', 'beneficiary_name'] as $column) {
                if (Schema::hasColumn('beneficiaries', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
