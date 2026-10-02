
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crypto_card_orders', function (Blueprint $table): void {
            $table->string('brand', 30)
                ->nullable()
                ->after('order_type');

            $table->unsignedInteger('allocation')
                ->default(1)
                ->after('currency');

            $table->boolean('expedite')
                ->default(false)
                ->after('allocation');

            $table->string('shipping_method', 30)
                ->nullable()
                ->after('shipping_address');

            $table->string('design', 100)
                ->nullable()
                ->after('shipping_method');

            $table->json('name_on_cards')
                ->nullable()
                ->after('design');
        });
    }

    public function down(): void
    {
        Schema::table('crypto_card_orders', function (Blueprint $table): void {
            $table->dropColumn([
                'brand',
                'allocation',
                'expedite',
                'shipping_method',
                'design',
                'name_on_cards',
            ]);
        });
    }
};
