<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GlobalController;
use App\Http\Controllers\User\KycController;
use App\Providers\Admin\BasicSettingsProvider;
use Pusher\PushNotifications\PushNotifications;
use App\Http\Controllers\User\ProfileController;
use App\Http\Controllers\User\AddMoneyController;
use App\Http\Controllers\User\MoneyOutController;
use App\Http\Controllers\User\SecurityController;
use App\Http\Controllers\User\DashboardController;
use App\Http\Controllers\User\StatementController;
use App\Http\Controllers\User\BeneficiaryController;
use App\Http\Controllers\User\TransactionController;
use App\Http\Controllers\User\FundTransferController;
use App\Http\Controllers\User\AuthorizationController;
use App\Http\Controllers\User\SetupPinController;
use App\Http\Controllers\User\StrowalletVirtualCardController;
use App\Http\Controllers\User\SupportTicketController;


Route::prefix("user")->name("user.")->group(function(){
    Route::controller(DashboardController::class)->group(function(){
        Route::get('dashboard','index')->name('dashboard');
        Route::post('logout','logout')->name('logout');
        Route::delete('delete/account','deleteAccount')->name('delete.account')->middleware('app.mode');
        Route::post("check/pin",'checkPin')->middleware('auth')->name('check.pin');
    });

    Route::controller(ProfileController::class)->prefix("profile")->name("profile.")->group(function(){
        Route::get('/','index')->name('index');
        Route::put('password/update','passwordUpdate')->name('password.update')->withoutMiddleware('app.mode');
        Route::put('update','update')->name('update')->withoutMiddleware('app.mode');
    });

    Route::controller(SupportTicketController::class)->prefix("prefix")->name("support.ticket.")->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('create', 'create')->name('create');
        Route::post('store', 'store')->name('store');
        Route::get('conversation/{encrypt_id}','conversation')->name('conversation');
        Route::post('message/send','messageSend')->name('messaage.send');
    });

    Route::controller(AddMoneyController::class)->prefix('add-money')->middleware(['kyc.verification.guard','pin.setup.guard'])->name('add.money.')->group(function() {
        Route::get('/','index')->name('index');
        Route::post('submit','submit')->name('submit');

        Route::get('preview/{token}','preview')->name('preview');
        Route::post('preview/submit','previewSubmit')->name('preview.submit');

        Route::get('success/response/{gateway}','success')->name('payment.success');
        Route::get("cancel/response/{gateway}",'cancel')->name('payment.cancel');
        Route::post("callback/response/{gateway}",'callback')->name('payment.callback')->withoutMiddleware(['web','auth','verification.guard','user.google.two.factor']);

        // POST Route For Unauthenticated Request
        Route::post('success/response/{gateway}', 'postSuccess')->name('payment.success')->withoutMiddleware(['auth','verification.guard','user.google.two.factor','kyc.verification.guard','pin.setup.guard']);
        Route::post('cancel/response/{gateway}', 'postCancel')->name('payment.cancel')->withoutMiddleware(['auth','verification.guard','user.google.two.factor']);

        // redirect with HTML form route
        Route::get('redirect/form/{gateway}', 'redirectUsingHTMLForm')->name('payment.redirect.form')->withoutMiddleware(['auth','verification.guard','user.google.two.factor']);

        //redirect with Btn Pay
        Route::get('redirect/btn/checkout/{gateway}', 'redirectBtnPay')->name('payment.btn.pay')->withoutMiddleware(['auth','verification.guard','user.google.two.factor']);

        Route::post('authorize/payment/submit', 'authorizePaymentSubmit')->name('authorize.payment.submit')->withoutMiddleware(['auth','verification.guard','user.google.two.factor']);

        Route::get('manual/{token}','showManualForm')->name('manual.form');
        Route::post('manual/submit/{token}','manualSubmit')->name('manual.submit');

        Route::get('manual/preview/{token}','manualPreview')->name('manual.preview');
        Route::post('manual/preview/submit','manualPreviewSubmit')->name('manual.preview.submit');

        Route::prefix('payment')->name('payment.')->group(function() {
            Route::get('crypto/address/{trx_id}','cryptoPaymentAddress')->name('crypto.address');
            Route::post('crypto/confirm/{trx_id}','cryptoPaymentConfirm')->name('crypto.confirm');
        });
    });

    Route::controller(BeneficiaryController::class)->middleware(['kyc.verification.guard','pin.setup.guard'])->prefix("beneficiary")->name("beneficiary.")->group(function(){
        Route::get('list','index')->name('index');
        Route::get('add/{type?}','create')->name('create');
        Route::post('submit/{type?}','submit')->name('submit');
        Route::get('preview/{temp_token}/{type?}','preview')->name('preview');
        Route::post('confirm/{type?}','confirm')->name('confirm');
        Route::delete('delete','delete')->name('delete');
        Route::post('search/{type?}','search')->name('search');
        Route::post('get/create-input','getTrxTypeInputs')->name('create.get.input');
        Route::post('get/create-input/sub','getTrxTypeSubInputs')->name('create.get.sub.input');
        Route::post('get/bank-branch','getBankBranch')->name('get.bank.branch');
    });

    Route::controller(FundTransferController::class)->prefix('fund-transfer')->middleware(['kyc.verification.guard','pin.setup.guard'])->name('fund-transfer.')->group(function(){
        Route::get('/', 'index')->name('index');
        Route::post('/beneficiary/select', 'beneficiarySelect')->name('beneficiary.select');
        Route::get('/create/{token}', 'create')->name('create');
        Route::post('/submit', 'submit')->name('submit');
        Route::get('/preview/{token}', 'preview')->name('preview');
        Route::post('preview/submit', 'previewSubmit')->name('preview.submit');
        Route::get('transaction/success/{trx_id}','transactionSuccess')->name('transaction.success');
        Route::get('pdf/download/{trx_id}','pdfDownload')->name('pdf.download');
    });

    // strowallet virtual card
    Route::controller(StrowalletVirtualCardController::class)->middleware(['kyc.verification.guard','pin.setup.guard'])->prefix('strowallet-virtual-card')->name('strowallet.virtual.card.')->group(function(){
        Route::get('/', 'index')->name('index');
        Route::get('create','createPage')->name('create')->middleware('kyc.verification.guard');
        Route::post('create/customer','createCustomer')->name('create.customer')->middleware('kyc.verification.guard');
        Route::get('edit/customer','editCustomer')->name('edit.customer')->middleware('kyc.verification.guard');
        Route::put('update/customer','updateCustomer')->name('update.customer')->middleware('kyc.verification.guard');

        Route::post('create','cardBuy')->name('store')->middleware('kyc.verification.guard');
        Route::post('fund','cardFundConfirm')->name('fund')->middleware('kyc.verification.guard');
        Route::get('details/{card_id}','cardDetails')->name('details');
        Route::get('transaction/{card_id}','cardTransaction')->name('transaction');
        Route::put('change/status','cardBlockUnBlock')->name('change.status');
        Route::post('make/default/remove/default','makeDefaultOrRemove')->name('make.default.or.remove');
    });

    // Transaction
    Route::controller(TransactionController::class)->prefix('transactions')->name('transactions.')->group(function(){
        Route::get('/{slug?}', 'index')->name('index')->whereIn('slug',['add-money','money-out','transfer-money','money-exchange']);
        Route::post('search', 'search')->name('search');
        Route::get('download/{sd_id}','download')->name('download');
    });

    Route::controller(StatementController::class)->prefix('statements')->name('statements.')->group(function(){
        Route::get('/', 'index')->name('index');
        Route::get('/filter', 'filterStatement')->name('filter');
    });

    //setup pin
    Route::controller(SetupPinController::class)->prefix('setup-pin')->name('setup.pin.')->group(function(){
        Route::get('/', 'index')->name('index');
        Route::post('store', 'store')->name('store');
        Route::post('update','update')->name('update');
    });

    Route::controller(SecurityController::class)->prefix("security")->name('security.')->group(function(){
        Route::get('google/2fa','google2FA')->name('google.2fa');
        Route::post('google/2fa/status/update','google2FAStatusUpdate')->name('google.2fa.status.update');
    });

    Route::controller(KycController::class)->prefix('kyc')->name('kyc.')->group(function() {
        Route::get('/','index')->name('index');
        Route::post('submit','store')->name('submit');
    });

    Route::controller(MoneyOutController::class)->middleware(['kyc.verification.guard','pin.setup.guard'])->prefix('money-out')->name('money-out.')->group(function() {
        Route::get('/','index')->name('index');
        Route::post('submit','submit')->name('submit');
        Route::get('instruction/{token}','instruction')->name('instruction');
        Route::post('instruction/submit/{token}','instructionSubmit')->name('instruction.submit');
        Route::get('preview/{token}','preview')->name('preview');
        Route::post('preview/submit','previewSubmit')->name('preview.submit');
    });
    // OTP Verification Mail/SMS Send
    Route::controller(AuthorizationController::class)->prefix('verification-code')->name('verification-code.')->group(function(){
        Route::get('send','verificationCodeSend')->name('send');
        Route::get('resend','verificationCodeResend')->name('resend');
        Route::post('check','verificationCodeCheck')->name('check');
    });

    Route::post("info/account",[GlobalController::class,'userInfoAccount'])->name('info.account');

});


// Route For Pusher Beams Auth
Route::get('user/pusher/beams-auth', function (Request $request) {
    if(Auth::check() == false) {
        return response(['Inconsistent request'], 401);
    }
    $userID = Auth::user()->id;

    $basic_settings = BasicSettingsProvider::get();
    if(!$basic_settings) {
        return response('Basic setting not found!', 404);
    }

    $notification_config = $basic_settings->push_notification_config;

    if(!$notification_config) {
        return response('Notification configuration not found!', 404);
    }

    $instance_id    = $notification_config->instance_id ?? null;
    $primary_key    = $notification_config->primary_key ?? null;
    if($instance_id == null || $primary_key == null) {
        return response('Sorry! You have to configure first to send push notification.', 404);
    }
    $beamsClient = new PushNotifications(
        array(
            "instanceId" => $notification_config->instance_id,
            "secretKey" => $notification_config->primary_key,
        )
    );

    $get_full_host_path = remove_special_char(get_full_url_host(), "-");

    $publisherUserId = $get_full_host_path . "-user-".$userID;
    try{
        $beamsToken = $beamsClient->generateToken($publisherUserId);
    }catch(Exception $e) {
        return response(['Server Error. Failed to generate beams token.'], 500);
    }

    return response()->json($beamsToken);
})->name('user.pusher.beams.auth');
