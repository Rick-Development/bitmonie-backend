<?php

namespace Tests\Feature\Savings;

use App\Models\SavingsAccount;
use App\Models\SavingsEntry;
use App\Models\User;
use App\Models\UserWallet;
use App\Models\VirtualAccounts;
use App\Services\SafeHavenService;
use App\Services\Savings\Contracts\SavingsFundingProviderInterface;
use App\Services\Savings\SavingsLedger;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProviderFundingTest extends SavingsTestCase
{
    protected FakeFundingProvider $fakeProvider;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('virtual_accounts', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->string('account_id')->nullable();
            $t->string('account_number')->nullable();
            $t->string('bank_name')->nullable();
            $t->string('bank_code')->nullable();
            $t->string('provider')->nullable();
            $t->timestamps();
        });

        $this->fakeProvider = new FakeFundingProvider();
        $this->app->instance(SavingsFundingProviderInterface::class, $this->fakeProvider);
    }

    private function createUserWithVirtualAccount(string $walletBalance = '50000.00'): User
    {
        $user = User::create([
            'firstname' => 'Provider',
            'lastname' => 'Tester',
            'email' => 'provider' . uniqid() . '@example.com',
            'password' => bcrypt('secret'),
        ]);

        UserWallet::create([
            'user_id' => $user->id,
            'currency_code' => 'NGN',
            'balance' => $walletBalance,
            'status' => true,
        ]);

        VirtualAccounts::create([
            'user_id' => $user->id,
            'account_id' => 'sh-sub-001',
            'account_number' => '1234567890',
            'bank_name' => 'SafeHaven Microfinance Bank',
            'bank_code' => '090286',
            'provider' => 'safehaven',
        ]);

        return $user;
    }

    public function test_deposit_triggers_provider_deposit_when_user_has_safehaven_account(): void
    {
        $user = $this->createUserWithVirtualAccount('50000.00');
        $ledger = app(SavingsLedger::class);

        $account = SavingsAccount::create([
            'user_id' => $user->id,
            'product' => SavingsAccount::FLEXI,
            'title' => 'Flexi Save',
            'currency' => 'NGN',
            'status' => SavingsAccount::ACTIVE,
            'balance' => '0.00',
        ]);

        $res = $ledger->deposit($user->id, $account->id, '10000.00');

        $this->assertCount(1, $this->fakeProvider->deposits);
        $this->assertEquals('10000.00', $this->fakeProvider->deposits[0]['amount']);
        $this->assertEquals($user->id, $this->fakeProvider->deposits[0]['user']->id);

        $entry = $res['entry'];
        $this->assertEquals('safehaven', $entry->meta['provider']);
        $this->assertNotEmpty($entry->meta['provider_reference']);

        $wallet = UserWallet::where('user_id', $user->id)->first();
        $this->assertEquals('40000.00000000', (string) $wallet->balance);
    }

    public function test_withdraw_triggers_provider_withdraw(): void
    {
        $user = $this->createUserWithVirtualAccount('10000.00');
        $ledger = app(SavingsLedger::class);

        $account = SavingsAccount::create([
            'user_id' => $user->id,
            'product' => SavingsAccount::FLEXI,
            'title' => 'Flexi Save',
            'currency' => 'NGN',
            'status' => SavingsAccount::ACTIVE,
            'balance' => '20000.00',
        ]);

        $ledger->withdraw($user->id, $account->id, '5000.00');

        $this->assertCount(1, $this->fakeProvider->withdrawals);
        $this->assertEquals('5000.00', $this->fakeProvider->withdrawals[0]['amount']);
        $this->assertEquals($user->id, $this->fakeProvider->withdrawals[0]['user']->id);

        $wallet = UserWallet::where('user_id', $user->id)->first();
        $this->assertEquals('15000.00000000', (string) $wallet->balance);
    }

    public function test_reconcile_unfunded_savings_command(): void
    {
        $user = $this->createUserWithVirtualAccount('10000.00');

        // Create an unbacked savings account (deposit without provider_reference)
        $account = SavingsAccount::create([
            'user_id' => $user->id,
            'product' => SavingsAccount::TARGET,
            'title' => 'Unbacked Kigali Trip',
            'currency' => 'NGN',
            'status' => SavingsAccount::ACTIVE,
            'balance' => '5000.00',
            'target_amount' => '100000.00',
        ]);

        SavingsEntry::create([
            'reference' => 'UNFUNDED-DEP-001',
            'user_id' => $user->id,
            'account_id' => $account->id,
            'type' => SavingsEntry::DEPOSIT,
            'direction' => SavingsEntry::CREDIT,
            'amount' => '5000.00',
            'balance_after' => '5000.00',
            'status' => 'COMPLETED',
            'source' => 'wallet',
            'meta' => null, // No provider reference
        ]);

        // Dry-run inspection
        $this->artisan('savings:reconcile-unfunded')
            ->expectsOutputToContain('Unbacked Kigali Trip')
            ->assertExitCode(0);

        // Fix mode: resets unfunded balance
        $this->artisan('savings:reconcile-unfunded', ['--fix' => true])
            ->assertExitCode(0);

        $refreshed = $account->refresh();
        $this->assertEquals(0, (float) $refreshed->balance);
        $this->assertEquals(SavingsAccount::CANCELLED, $refreshed->status);
    }
}

class FakeFundingProvider implements SavingsFundingProviderInterface
{
    public array $deposits = [];
    public array $withdrawals = [];
    public bool $failDeposit = false;
    public bool $failWithdraw = false;

    public function deposit(User $user, string $amount, string $reference, $narration = 'Savings Deposit'): array
    {
        if ($this->failDeposit) {
            throw new \RuntimeException('Provider deposit failed');
        }

        $this->deposits[] = compact('user', 'amount', 'reference', 'narration');

        return ['status' => 'success', 'reference' => $reference];
    }

    public function withdraw(User $user, string $amount, string $reference, string $narration = 'Savings Withdrawal'): array
    {
        if ($this->failWithdraw) {
            throw new \RuntimeException('Provider withdraw failed');
        }

        $this->withdrawals[] = compact('user', 'amount', 'reference', 'narration');

        return ['status' => 'success', 'reference' => $reference];
    }

    public function getTransferStatus(string $sessionId, string $paymentReference): array
    {
        return ['status' => 'success', 'reference' => $paymentReference];
    }
}
