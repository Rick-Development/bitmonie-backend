<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\V1\User\InternalTransferController;
use App\Http\Controllers\Api\V1\User\UsdtEasyearnController;
use App\Models\User;
use App\Notifications\User\LoanNotification;
use App\Services\LoanService;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Notification;
use Mockery;
use Tests\TestCase;

class LoanAndEasyearnApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_borrow_request_normalizes_asset_aliases_before_validation(): void
    {
        Notification::fake();

        $user = new User();
        $user->forceFill([
            'id' => 999,
            'firstname' => 'Loan',
            'lastname' => 'Tester',
            'email' => 'loan.tester@example.com',
        ]);
        $user->exists = true;

        $mock = Mockery::mock(LoanService::class);
        $mock->shouldReceive('getSupportedAssets')
            ->once()
            ->andReturn(['USDT', 'USDC', 'BTC', 'SOL', 'BNB']);
        $mock->shouldReceive('normalizeAssetSymbol')
            ->once()
            ->with('usdt')
            ->andReturn('USDT');
        $mock->shouldReceive('normalizeAssetSymbol')
            ->once()
            ->with('solana')
            ->andReturn('SOL');
        $mock->shouldReceive('createBorrowRequest')
            ->once()
            ->with(Mockery::type(User::class), 'USDT', 50.0, 30, 'SOL')
            ->andReturn([
                'message' => 'Loan disbursement successful',
                'data' => [
                    'id' => 101,
                    'asset' => 'USDT',
                    'collateral_asset' => 'SOL',
                ],
            ]);

        $this->app->instance(LoanService::class, $mock);

        $response = $this->actingAs($user, 'api')->postJson('/api/v1/loans/borrow', [
            'asset' => 'usdt',
            'amount' => 50,
            'duration_days' => '30 Days',
            'collateral_asset' => 'solana',
        ]);

        $response->assertCreated()
            ->assertJsonPath('type', 'success')
            ->assertJsonPath('message', 'Loan disbursement successful')
            ->assertJsonPath('data.asset', 'USDT')
            ->assertJsonPath('data.collateral_asset', 'SOL');

        Notification::assertSentTo($user, LoanNotification::class);
    }

    public function test_easyearn_top_up_route_is_registered_for_v1_and_user_prefixes(): void
    {
        $routes = app('router')->getRoutes();

        $v1Route = $routes->match(HttpRequest::create('/api/v1/usdt-easyearn/top-up/2', 'POST'));
        $userRoute = $routes->match(HttpRequest::create('/api/user/usdt-easyearn/top-up/2', 'POST'));

        $this->assertSame(UsdtEasyearnController::class . '@topUp', $v1Route->getActionName());
        $this->assertSame(UsdtEasyearnController::class . '@topUp', $userRoute->getActionName());
    }

    public function test_internal_transfer_check_user_alias_route_is_registered(): void
    {
        $routes = app('router')->getRoutes();

        $aliasRoute = $routes->match(HttpRequest::create('/api/user/internal-transfer/check-user', 'POST'));
        $fundTransferRoute = $routes->match(HttpRequest::create('/api/user/fund-transfer/check-user', 'POST'));

        $this->assertSame(InternalTransferController::class . '@checkUser', $aliasRoute->getActionName());
        $this->assertSame(InternalTransferController::class . '@checkUser', $fundTransferRoute->getActionName());
    }
}
