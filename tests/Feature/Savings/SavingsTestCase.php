<?php

namespace Tests\Feature\Savings;

use App\Models\User;
use App\Models\UserWallet;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Builds only the tables the savings domain touches (users, wallets, wallet
 * trail) in an in-memory SQLite DB and runs the REAL savings migration.
 */
abstract class SavingsTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('app.timezone', 'UTC');
        date_default_timezone_set('UTC');
        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        Schema::dropAllTables();

        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('firstname')->nullable();
            $t->string('lastname')->nullable();
            $t->string('email')->unique();
            $t->string('password')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('user_wallets', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('currency_id')->nullable();
            $t->string('currency_code', 10)->default('NGN');
            $t->decimal('balance', 28, 8)->default(0);
            $t->decimal('reserved', 28, 8)->default(0);
            $t->boolean('status')->default(true);
            $t->timestamps();
        });
        Schema::create('order_transactions', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_wallet_id');
            $t->string('type');
            $t->string('amount');
            $t->string('balance_after')->nullable();
            $t->string('reference')->nullable();
            $t->json('metadata')->nullable();
            $t->timestamps();
        });
        Schema::create('notifications', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('type');
            $t->morphs('notifiable');
            $t->text('data');
            $t->timestamp('read_at')->nullable();
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

        (require base_path('database/migrations/2026_10_15_000001_create_unified_savings_tables.php'))->up();

        Notification::fake();
    }

    protected function makeUser(string $wallet = '0'): User
    {
        static $n = 0;
        $n++;
        $id = DB::table('users')->insertGetId([
            'firstname' => 'Test', 'lastname' => "User{$n}", 'email' => "user{$n}-" . uniqid() . '@example.test',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        UserWallet::create(['user_id' => $id, 'currency_code' => 'NGN', 'balance' => $wallet, 'reserved' => '0', 'status' => true]);

        return User::findOrFail($id);
    }

    protected function walletBalance(User $user): string
    {
        return bcadd((string) UserWallet::where('user_id', $user->id)->value('balance'), '0', 2);
    }
}
