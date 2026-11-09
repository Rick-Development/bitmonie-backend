<?php

namespace Tests\Feature\CryptoCards;

use App\Models\CryptoCardEscrow;
use App\Models\CryptoCardHolders;
use App\Models\CryptoCardsModel;
use App\Models\CryptoCardTransactions;
use App\Models\User;
use App\Models\UserWallet;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * In-memory SQLite with only the tables the async card-creation flow touches.
 * Card tables + notification templates come from the REAL migrations.
 * No network: Sudo / Quidax are always mocked in the tests.
 */
abstract class CryptoCardTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        Config::set('queue.default', 'sync');
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        Schema::dropAllTables();
        Cache::flush();

        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('firstname')->nullable();
            $t->string('lastname')->nullable();
            $t->string('email')->unique();
            $t->string('password')->nullable();
            $t->boolean('email_verified')->default(true);
            $t->unsignedBigInteger('language_id')->nullable();
            $t->string('quidax_id')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('user_wallets', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('currency_id')->nullable();
            $t->string('currency_code', 10)->default('NGN');
            $t->string('quote_currency_code', 10)->nullable();
            $t->decimal('balance', 28, 8)->default(0);
            $t->boolean('status')->default(true);
            $t->timestamps();
        });
        Schema::create('currencies', function (Blueprint $t) {
            $t->id();
            $t->string('code', 10);
            $t->timestamps();
        });
        Schema::create('basic_controls', function (Blueprint $t) {
            $t->id();
            $t->boolean('email_notification')->default(true);
            $t->boolean('push_notification')->default(false);
            $t->text('email_description')->nullable();
            $t->string('sender_email')->nullable();
            $t->string('site_title')->nullable();
            $t->timestamps();
        });
        Schema::create('system_maintenances', function (Blueprint $t) {
            $t->id();
            $t->string('slug')->nullable();
            $t->string('title')->nullable();
            $t->text('details')->nullable();
            $t->integer('status')->default(0);
            $t->timestamps();
        });
        DB::table('basic_controls')->insert([
            'email_notification' => 1,
            'push_notification' => 0,
            'email_description' => '<p>Hi [[name]],</p>[[message]]',
            'sender_email' => 'noreply@example.test',
            'site_title' => 'Bitmonie',
        ]);

        foreach ([
            '2026_08_15_145001_create_crypto_cards_table.php',
            '2026_08_15_145110_create_crypto_card_transactions_table.php',
            '2026_08_15_145202_create_crypto_card_holders_table.php',
            '2026_08_19_011824_alter_crypto_cards.php',
            '2026_08_19_012131_create_crypto_card_escrows_table.php',
            '2026_08_26_220147_alter_crypto_cards.php',
            '2026_07_20_150752_create_notification_templates_table.php',
            '2026_11_09_120000_add_crypto_card_creation_notification_templates.php',
        ] as $migration) {
            (require base_path('database/migrations/' . $migration))->up();
        }
    }

    protected function makeUser(string $usdt = '0', bool $verified = true): User
    {
        static $n = 0;
        $n++;
        $id = DB::table('users')->insertGetId([
            'firstname' => 'Ada', 'lastname' => "User{$n}",
            'email' => "card{$n}-" . uniqid() . '@example.test',
            'email_verified' => $verified,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        UserWallet::create(['user_id' => $id, 'currency_code' => 'USDT', 'balance' => $usdt, 'status' => true]);

        return User::findOrFail($id);
    }

    /** @return array{0: CryptoCardsModel, 1: CryptoCardTransactions, 2: CryptoCardEscrow} */
    protected function makePendingCard(User $user, float $usdt = 5.0, string $status = 'processing', array $txMeta = []): array
    {
        $holder = CryptoCardHolders::findOrFail(DB::table('crypto_card_holders')->insertGetId($this->holderAttributes($user)));

        $charge = ['charged' => $usdt > 0, 'escrow_deducted' => false, 'usdt_amount' => (string) $usdt, 'reference' => 'REF-' . uniqid()];
        $payload = ['type' => 'virtual', 'currency' => 'NGN', 'brand' => 'Visa'];

        $card = CryptoCardsModel::create([
            'user_id' => $user->id,
            'card_holder_id' => $holder->id,
            'card_provider' => 'sudo',
            'card_type' => 'virtual',
            'card_brand' => 'Visa',
            'card_currency' => 'NGN',
            'card_status' => $status,
            'card_balance' => 0,
            'metadata' => ['create_payload' => $payload, 'charge' => $charge],
        ]);
        $tx = CryptoCardTransactions::create([
            'user_id' => $user->id,
            'crypto_card_id' => $card->id,
            'card_provider' => 'sudo',
            'transaction_type' => 'issuance',
            'transaction_status' => $status === 'pending' ? 'initiated' : 'processing',
            'entry_type' => 'debit',
            'amount' => $usdt,
            'fee' => $usdt,
            'currency' => 'USDT',
            'channel' => 'platform',
            'transaction_date' => now(),
            'metadata' => array_merge(['purpose' => 'card_creation', 'create_payload' => $payload, 'charge' => $charge], $txMeta),
        ]);
        $escrow = CryptoCardEscrow::create([
            'user_id' => $user->id,
            'crypto_card_id' => $card->id,
            'crypto_card_transaction_id' => $tx->id,
            'card_provider' => 'sudo',
            'type' => 'issuance',
            'direction' => 'debit',
            'status' => 'processing',
            'amount' => $usdt,
            'currency' => 'USDT',
        ]);

        return [$card, $tx, $escrow];
    }

    protected function holderAttributes(User $user): array
    {
        $columns = Schema::getColumnListing('crypto_card_holders');
        $attrs = ['user_id' => $user->id, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()];
        $attrs += [
            'name' => 'Ada Test', 'first_name' => 'Ada', 'last_name' => 'Test', 'email' => $user->email,
            'provider_customers' => json_encode(['sudo' => ['customer_id' => 'cus_test_' . $user->id]]),
        ];

        return array_intersect_key($attrs, array_flip($columns));
    }

    protected function usdtBalance(User $user): float
    {
        return (float) UserWallet::where('user_id', $user->id)->where('currency_code', 'USDT')->value('balance');
    }
}
