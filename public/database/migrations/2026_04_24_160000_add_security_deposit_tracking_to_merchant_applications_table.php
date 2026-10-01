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
        Schema::table('merchant_applications', function (Blueprint $table) {
            $table->decimal('security_deposit_amount', 20, 8)->default(0)->after('min_usdt_required');
            $table->string('security_deposit_currency', 16)->default('usdt')->after('security_deposit_amount');
            $table->string('security_deposit_status', 32)->default('not_locked')->after('security_deposit_currency');
            $table->string('security_deposit_lock_reference')->nullable()->after('security_deposit_status');
            $table->timestamp('security_deposit_locked_at')->nullable()->after('security_deposit_lock_reference');
            $table->string('security_deposit_release_reference')->nullable()->after('security_deposit_locked_at');
            $table->timestamp('security_deposit_released_at')->nullable()->after('security_deposit_release_reference');
            $table->string('security_deposit_release_reason', 100)->nullable()->after('security_deposit_released_at');
            $table->timestamp('merchant_deactivated_at')->nullable()->after('reviewed_at');
            $table->text('merchant_deactivation_reason')->nullable()->after('merchant_deactivated_at');

            $table->index('security_deposit_status');
            $table->index('merchant_deactivated_at');
        });

        DB::table('admin_settings')->updateOrInsert(
            ['setting_key' => 'merchant_security_deposit_usdt'],
            [
                'setting_value' => '200',
                'description' => 'Refundable USDT security deposit required for merchant activation',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('merchant_applications', function (Blueprint $table) {
            $table->dropIndex(['security_deposit_status']);
            $table->dropIndex(['merchant_deactivated_at']);
            $table->dropColumn([
                'security_deposit_amount',
                'security_deposit_currency',
                'security_deposit_status',
                'security_deposit_lock_reference',
                'security_deposit_locked_at',
                'security_deposit_release_reference',
                'security_deposit_released_at',
                'security_deposit_release_reason',
                'merchant_deactivated_at',
                'merchant_deactivation_reason',
            ]);
        });

        DB::table('admin_settings')
            ->where('setting_key', 'merchant_security_deposit_usdt')
            ->delete();
    }
};
