<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// KYC Tiers (Public endpoint)
Route::get('/kyc/tiers', [\App\Http\Controllers\Api\KycController::class, 'tiers']);

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Fallback login route to prevent 500 RouteNotFoundException
Route::get('/login', function () {
    return response()->json(['message' => 'Unauthenticated.'], 401);
})->name('api.login');

// Public Referral Code Validation
Route::post('/referral/validate', [App\Http\Controllers\Api\V1\User\ReferralController::class, 'validateCode']);

// Savings (Piggyvest) Routes
Route::middleware('auth:api')->prefix('v1/savings')->group(function () {
    // Flex
    Route::prefix('flex')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\Savings\FlexController::class, 'index']);
        Route::post('deposit', [\App\Http\Controllers\Api\Savings\FlexController::class, 'deposit']);
        Route::post('withdraw', [\App\Http\Controllers\Api\Savings\FlexController::class, 'withdraw']);
        Route::get('history', [\App\Http\Controllers\Api\Savings\FlexController::class, 'history']);
    });

    // SafeLock
    Route::prefix('safelock')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\Savings\SafeLockController::class, 'index']);
        Route::get('plans', [\App\Http\Controllers\Api\Savings\SafeLockController::class, 'plans']);
        Route::post('create', [\App\Http\Controllers\Api\Savings\SafeLockController::class, 'create']);
        Route::post('withdraw', [\App\Http\Controllers\Api\Savings\SafeLockController::class, 'withdraw']);
        Route::post('break', [\App\Http\Controllers\Api\Savings\SafeLockController::class, 'break']);
        Route::get('history', [\App\Http\Controllers\Api\Savings\SafeLockController::class, 'history']);
    });

    // Target Savings
    Route::prefix('target')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\Savings\TargetController::class, 'index']);
        Route::post('create', [\App\Http\Controllers\Api\Savings\TargetController::class, 'create']);
        Route::post('quick-save', [\App\Http\Controllers\Api\Savings\TargetController::class, 'quickSave']);
        Route::post('break', [\App\Http\Controllers\Api\Savings\TargetController::class, 'break']);
        Route::get('history', [\App\Http\Controllers\Api\Savings\TargetController::class, 'history']);
    });

    // EduSave
    Route::prefix('edusave')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\Savings\EduSaveController::class, 'index']);
        Route::post('create', [\App\Http\Controllers\Api\Savings\EduSaveController::class, 'create']);
        Route::get('history', [\App\Http\Controllers\Api\Savings\EduSaveController::class, 'history']);
    });
});

// KYC (YouVerify)
Route::middleware('auth:api')->prefix('kyc')->group(function () {
    Route::get('user-tier', [\App\Http\Controllers\Api\User\KycController::class, 'userTier']);
    Route::get('tiers', [\App\Http\Controllers\Api\User\KycController::class, 'tiers']);
    
    // Tier 1
    Route::post('tier1/initiate', [\App\Http\Controllers\Api\User\KycController::class, 'tier1Initiate']);
    Route::post('tier1/verify', [\App\Http\Controllers\Api\User\KycController::class, 'tier1Verify']);

    // Tier 2
    Route::post('tier2/initiate', [\App\Http\Controllers\Api\User\KycController::class, 'tier2Initiate']);
    Route::post('tier2/verify', [\App\Http\Controllers\Api\User\KycController::class, 'tier2Verify']);

    // Tier 3
    Route::post('tier3/submit', [\App\Http\Controllers\Api\User\KycController::class, 'tier3Submit']);
});

// YouVerify Webhook
Route::post('webhook/youverify', [\App\Http\Controllers\Api\User\WebhookController::class, 'handleYouVerify'])->name('webhook.youverify');

// SafeHaven Webhook
Route::post('webhook/safehaven', [\App\Http\Controllers\Api\User\WebhookController::class, 'handleSafeHaven'])->name('webhook.safehaven');

// Graph Webhook
Route::post('webhook/graph', [\App\Http\Controllers\Api\GraphWebhookController::class, 'handleWebhook'])->name('webhook.graph');

// Busha Webhook
Route::post('webhook/busha', [\App\Http\Controllers\Api\BushaWebhookController::class, 'handle'])->name('webhook.busha');

// Quidax Ramp Webhook
Route::post('webhook/quidax-ramp', [\App\Http\Controllers\Api\QuidaxRampWebhookController::class, 'handle'])->name('webhook.quidax-ramp');

// Route::post('webhook/safehaven', [\App\Http\Controllers\Api\User\WebhookController::class, 'handleSafeHaven'])->name('webhook.safehaven');


// Crypto Loans
Route::middleware('auth:api')->prefix('v1/loans')->group(function () {
    // Lending
    Route::post('lend', [\App\Http\Controllers\Api\V1\User\LoanController::class, 'createLendingOffer']);
    Route::get('my-lending-offers', [\App\Http\Controllers\Api\V1\User\LoanController::class, 'getMyLendingOffers']);
    Route::post('cancel-offer/{offerId}', [\App\Http\Controllers\Api\V1\User\LoanController::class, 'cancelLendingOffer']);
    
    // Borrowing
    Route::post('borrow', [\App\Http\Controllers\Api\V1\User\LoanController::class, 'createBorrowRequest']);
    Route::get('my-borrow-requests', [\App\Http\Controllers\Api\V1\User\LoanController::class, 'getMyBorrowRequests']);
    Route::post('calculate-collateral', [\App\Http\Controllers\Api\V1\User\LoanController::class, 'calculateCollateral']);
    Route::get('collateral-options', [\App\Http\Controllers\Api\V1\User\LoanController::class, 'getCollateralOptions']);
    
    // Active Loans
    Route::get('my-loans/borrower', [\App\Http\Controllers\Api\V1\User\LoanController::class, 'getMyLoansAsBorrower']);
    Route::get('my-loans/lender', [\App\Http\Controllers\Api\V1\User\LoanController::class, 'getMyLoansAsLender']);
    Route::get('details/{loanId}', [\App\Http\Controllers\Api\V1\User\LoanController::class, 'getLoanDetails']);
    Route::get('borrow/cancel/{loan_request_id}', [\App\Http\Controllers\Api\V1\User\LoanController::class, 'cancelBorrowRequest']);
    // Balance Endpoints
    Route::get('bond-balance/{loanId}', [\App\Http\Controllers\Api\V1\User\LoanController::class, 'getBondBalance']);
    Route::get('total-balances', [\App\Http\Controllers\Api\V1\User\LoanController::class, 'getTotalAssetBalances']);
    
    // Repayment
    Route::post('repay/{loanId}', [\App\Http\Controllers\Api\V1\User\LoanController::class, 'repayLoan']);
});

// USDT EasyEarn
Route::middleware(['auth:api', 'verification.guard.api'])->prefix('v1/usdt-easyearn')->group(function () {
    Route::get('info', [\App\Http\Controllers\Api\V1\User\UsdtEasyearnController::class, 'info']);
    Route::get('my-investments', [\App\Http\Controllers\Api\V1\User\UsdtEasyearnController::class, 'myInvestments']);
    Route::get('my-savings', [\App\Http\Controllers\Api\V1\User\UsdtEasyearnController::class, 'myInvestments']);
    Route::post('invest', [\App\Http\Controllers\Api\V1\User\UsdtEasyearnController::class, 'invest']);
    Route::post('save', [\App\Http\Controllers\Api\V1\User\UsdtEasyearnController::class, 'invest']);
    Route::get('investment/{id}', [\App\Http\Controllers\Api\V1\User\UsdtEasyearnController::class, 'show']);
    Route::get('savings/{id}', [\App\Http\Controllers\Api\V1\User\UsdtEasyearnController::class, 'show']);
    Route::post('withdraw-interest/{id}', [\App\Http\Controllers\Api\V1\User\UsdtEasyearnController::class, 'withdrawInterest']);
    Route::post('withdraw-principal/{id}', [\App\Http\Controllers\Api\V1\User\UsdtEasyearnController::class, 'withdrawPrincipal']);
    Route::post('top-up/{id}', [\App\Http\Controllers\Api\V1\User\UsdtEasyearnController::class, 'topUp']);
});

// =========================================================================
// USD Wallet Operations (Graph API)
// =========================================================================
Route::middleware('auth:api')->prefix('usd')->group(function () {
    // POST /api/usd/receive   — credit USD into the user's Graph USD wallet
    Route::post('receive', [\App\Http\Controllers\Api\V1\User\UsdController::class, 'receive']);
    // POST /api/usd/send      — send USD from user's Graph USD wallet to a recipient
    Route::post('send',    [\App\Http\Controllers\Api\V1\User\UsdController::class, 'send']);
});

// =========================================================================
// Graph Price Data (Public — cached proxy to CoinGecko)
// =========================================================================
Route::prefix('graph')->group(function () {
    // GET /api/graph/prices?symbol=BTC&interval=1d
    Route::get('prices',  [\App\Http\Controllers\Api\V1\User\GraphDataController::class, 'prices']);
    // GET /api/graph/history?symbol=BTC&days=7
    Route::get('history', [\App\Http\Controllers\Api\V1\User\GraphDataController::class, 'history']);
});
