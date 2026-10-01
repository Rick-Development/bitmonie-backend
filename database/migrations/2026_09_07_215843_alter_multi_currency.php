<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('multi_currency_wallets', function (Blueprint $table): void {
            $table->renameColumn(
                'provider_account_reference',
                'provider_reference'
            );
        });
    }

    public function down(): void
    {
        Schema::table('multi_currency_wallets', function (Blueprint $table): void {
            $table->renameColumn(
                'provider_reference',
                'provider_account_reference'
            );
        });
    }
};