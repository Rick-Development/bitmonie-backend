<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('p2p_orders', function (Blueprint $table) {
            $table->foreignId('ad_id')->nullable()->after('id')->constrained('p2p_ads')->nullOnDelete();
            $table->decimal('locked_price', 20, 8)->nullable()->after('price');
            $table->timestamp('payment_deadline')->nullable()->after('expires_at');
            $table->enum('appeal_status', ['none', 'pending', 'resolved'])->default('none')->after('status');
            $table->text('appeal_reason')->nullable()->after('appeal_status');
            $table->json('evidence')->nullable()->after('appeal_reason');
        });
    }

    public function down()
    {
        Schema::table('p2p_orders', function (Blueprint $table) {
            $table->dropForeign(['ad_id']);
            $table->dropColumn(['ad_id', 'locked_price', 'payment_deadline', 'appeal_status', 'appeal_reason', 'evidence']);
        });
    }
};
