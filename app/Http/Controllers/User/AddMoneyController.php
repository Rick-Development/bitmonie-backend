<?php

namespace App\Http\Controllers\User;

use Exception;
use App\Models\UserWallet;
use App\Models\Transaction;
use Illuminate\Http\Request;
use App\Models\TemporaryData;
use App\Constants\GlobalConst;
use App\Models\Admin\Currency;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\Admin\PaymentGateway;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\RedirectResponse;
use App\Constants\PaymentGatewayConst;
use App\Models\Admin\CryptoTransaction;
use App\Traits\ControlDynamicInputFields;
use Illuminate\Support\Facades\Validator;
use App\Models\Admin\PaymentGatewayCurrency;
use App\Http\Helpers\PaymentGateway as PaymentGatewayHelper;

class AddMoneyController extends Controller
{
    use ControlDynamicInputFields;

    public function index() {
        $page_title       = "Add Money";
        $user_wallets     = UserWallet::auth()->get();
        $user_currencies  = Currency::whereIn('id',$user_wallets->pluck('currency_id')->toArray())->get();
        $payment_gateways = PaymentGateway::addMoney()->active()->with('currencies')->has("currencies")->get();
        $transactions     = Transaction::auth()->addMoney()->orderByDesc("id")->latest()->take(3)->get();
        return view('user.sections.add-money.index',compact('page_title','payment_gateways', 'transactions'));
    }

    public function submit(Request $request, PaymentGatewayCurrency $gateway_currency) {

        $validated = Validator::make($request->all(),[
            'amount'            => 'required|numeric|gt:0',
            'gateway_currency'  => 'required|string|exists:'.$gateway_currency->getTable().',alias',
        ])->validate();

        $request->merge(['currency' => $validated['gateway_currency']]);

        try{
            $instance = PaymentGatewayHelper::init($request->all())->type(PaymentGatewayConst::TYPEADDMONEY)->setProjectCurrency(PaymentGatewayConst::PROJECT_CURRENCY_SINGLE)->gateway()->get();

            if($instance instanceof RedirectResponse === false && isset($instance['gateway_type']) && $instance['gateway_type'] == PaymentGatewayConst::MANUAL) {
                $manual_handler = $instance['distribute'];
                return $this->$manual_handler($instance);
            }

            $token = generate_unique_string("temporary_datas","identifier",16);
            TemporaryData::create([
                'type'          => PaymentGatewayConst::add_money_slug(),
                'identifier'    => $token,
                'data'          => ['instance' => $instance, 'request' => $request->all()]
            ]);

        }catch(Exception $e) {
            return back()->with(['error' => [$e->getMessage()]]);
        }

        return redirect()->route('user.add.money.preview', $token);
    }

    public function preview($token) {
        $page_title = "Add Money Preview";
        $temp_data = TemporaryData::where('identifier',$token)->first();
        
        if(!$temp_data) return back()->with(['error' => ['Invalid Request']]);
        return view('user.sections.add-money.preview',compact('page_title','token','temp_data'));
    }

    public function previewSubmit(Request $request){

        $request->validate([
            'temp_token' => "required|exists:temporary_datas,identifier",
            'code'       => 'nullable',
        ]);

        $temp_data = TemporaryData::where('identifier',$request->temp_token)->first();
       
        try{
            $instance = PaymentGatewayHelper::init((array) $temp_data->data->request)->type(PaymentGatewayConst::TYPEADDMONEY)->setProjectCurrency(PaymentGatewayConst::PROJECT_CURRENCY_SINGLE)->gateway()->render();
        }catch(Exception $e) {
            return back()->with(['error' => [$e->getMessage()]]);
        }

        return $instance;
    }

    public function success(Request $request, $gateway){
        try{
            $token = PaymentGatewayHelper::getToken($request->all(),$gateway);
            $temp_data = TemporaryData::where("type",PaymentGatewayConst::TYPEADDMONEY)->where("identifier",$token)->first();

            if(Transaction::where('callback_ref', $token)->exists()) {
                if(!$temp_data) return redirect()->route('user.add.money.index')->with(['success' => ['Transaction failed. Record didn\'t saved properly. Please try again.']]);
            }else {
                if(!$temp_data) return redirect()->route('user.add.money.index')->with(['error' => ['Transaction failed. Record didn\'t saved properly. Please try again.']]);
            }
            $update_temp_data = json_decode(json_encode($temp_data->data),true);
            $update_temp_data['callback_data']  = $request->all();
            $temp_data->update([
                'data'  => $update_temp_data,
            ]);
            $temp_data = $temp_data->toArray();
            $instance = PaymentGatewayHelper::init($temp_data)->type(PaymentGatewayConst::TYPEADDMONEY)->setProjectCurrency(PaymentGatewayConst::PROJECT_CURRENCY_SINGLE)->responseReceive();
            if($instance instanceof RedirectResponse) return $instance;
        }catch(Exception $e) {
            return back()->with(['error' => [$e->getMessage()]]);
        }
        return redirect()->route("user.add.money.index")->with(['success' => ['Successfully added money.']]);
    }

    public function cancel(Request $request, $gateway) {

        $token = PaymentGatewayHelper::getToken($request->all(),$gateway);

        if($temp_data = TemporaryData::where("type",PaymentGatewayConst::TYPEADDMONEY)->where("identifier",$token)->first()) {
            $temp_data->delete();
        }

        return redirect()->route('user.add.money.index');
    }

    public function postSuccess(Request $request, $gateway)
    {
        try{
            $token = PaymentGatewayHelper::getToken($request->all(),$gateway);
            $temp_data = TemporaryData::where("type",PaymentGatewayConst::TYPEADDMONEY)->where("identifier",$token)->first();
            Auth::guard($temp_data->data->creator_guard)->loginUsingId($temp_data->data->creator_id);
        }catch(Exception $e) {
            return redirect()->route('frontend.index');
        }

        return $this->success($request, $gateway);
    }

    public function postCancel(Request $request, $gateway)
    {
        try{
            $token = PaymentGatewayHelper::getToken($request->all(),$gateway);
            $temp_data = TemporaryData::where("type",PaymentGatewayConst::TYPEADDMONEY)->where("identifier",$token)->first();
            Auth::guard($temp_data->data->creator_guard)->loginUsingId($temp_data->data->creator_id);
        }catch(Exception $e) {
            return redirect()->route('frontend.index');
        }

        return $this->cancel($request, $gateway);
    }

    public function callback(Request $request,$gateway) {

        $callback_token = $request->get('token');
        $callback_data = $request->all();

        try{
            PaymentGatewayHelper::init([])->type(PaymentGatewayConst::TYPEADDMONEY)->setProjectCurrency(PaymentGatewayConst::PROJECT_CURRENCY_SINGLE)->handleCallback($callback_token,$callback_data,$gateway);
        }catch(Exception $e) {
            logger($e);
        }
    }

    public function handleManualPayment($payment_info) {

        // Insert temp data
        $data = [
            'type'          => PaymentGatewayConst::TYPEADDMONEY,
            'identifier'    => generate_unique_string("temporary_datas","identifier",16),
            'data'          => [
                'gateway_currency_id'    => $payment_info['currency']->id,
                'amount'                 => $payment_info['amount'],
                'wallet_id'              => $payment_info['wallet']->id,
            ],
        ];

        try{
            TemporaryData::create($data);
        }catch(Exception $e) {
            return redirect()->route('user.add.money.index')->with(['error' => ['Failed to save data. Please try again']]);
        }
        return redirect()->route('user.add.money.manual.form',$data['identifier']);
    }

    public function showManualForm($token) {
        $tempData = TemporaryData::search($token)->first();
        if(!$tempData || $tempData->data == null || !isset($tempData->data->gateway_currency_id)) return redirect()->route('user.add.money.index')->with(['error' => ['Invalid request']]);
        $gateway_currency = PaymentGatewayCurrency::find($tempData->data->gateway_currency_id);
        if(!$gateway_currency || !$gateway_currency->gateway->isManual()) return redirect()->route('user.add.money.index')->with(['error' => ['Selected gateway is invalid']]);
        $gateway = $gateway_currency->gateway;
        if(!$gateway->input_fields || !is_array($gateway->input_fields)) return redirect()->route('user.add.money.index')->with(['error' => ['This payment gateway is under constructions. Please try with another payment gateway']]);
        $amount = $tempData->data->amount;

        $page_title = "Payment Instructions";
        return view('user.sections.add-money.manual.instruction',compact("gateway","page_title","token","amount"));
    }

    public function manualSubmit(Request $request,$token) {
        $request->merge(['identifier' => $token]);
        $tempDataValidate = Validator::make($request->all(),[
            'identifier'        => "required|string|exists:temporary_datas",
        ])->validate();

        $tempData = TemporaryData::search($tempDataValidate['identifier'])->first();
        if(!$tempData || $tempData->data == null || !isset($tempData->data->gateway_currency_id)) return redirect()->route('user.add.money.index')->with(['error' => ['Invalid request']]);
        $gateway_currency = PaymentGatewayCurrency::find($tempData->data->gateway_currency_id);

        if(!$gateway_currency || !$gateway_currency->gateway->isManual()) return redirect()->route('user.add.money.index')->with(['error' => ['Selected gateway is invalid']]);
        $gateway = $gateway_currency->gateway;
        $amount = $tempData->data->amount ?? null;
        if(!$amount) return redirect()->route('user.add.money.index')->with(['error' => ['Transaction Failed. Failed to save information. Please try again']]);
        $wallet = UserWallet::find($tempData->data->wallet_id ?? null);
        if(!$wallet) return redirect()->route('user.add.money.index')->with(['error' => ['Your wallet is invalid!']]);

        $this->file_store_location = "transaction";
        $dy_validation_rules = $this->generateValidationRules($gateway->input_fields);

        $validated = Validator::make($request->all(),$dy_validation_rules)->validate();
        $get_values = $this->placeValueWithFields($gateway->input_fields,$validated);

        $update_temp = (array) $tempData->data;
        $update_temp['get_values'] = $get_values;

        try {
            $tempData->update(['data' => $update_temp]);
        }catch(Exception $e) {
            return redirect()->route('user.add.money.manual.form',$token)->with(['error' => ['Something went wrong! Please try again']]);
        }

        return redirect()->route('user.add.money.manual.preview', $token);
    }

    public function manualPreview($token){
        $temp_data = TemporaryData::where('identifier',$token)->first();
        if(!$temp_data) return back()->with(['error' => ['Invalid Request']]);
        $page_title = "Add Money Preview";
        return view('user.sections.add-money.manual.preview',compact('page_title','temp_data','token'));
    }

    public function manualPreviewSubmit(Request $request){

        $validated = $request->validate([
            'temp_token' => "required|exists:temporary_datas,identifier",
            'code'     => 'nullable',
        ]);

        // Verification OTP Check
        $verification_otp_response = verificationCodeCodeCheck($request);
        if($verification_otp_response instanceof RedirectResponse) return $verification_otp_response;

        $temp_data = TemporaryData::where('identifier',$request->temp_token)->first();

        $gateway_currency = PaymentGatewayCurrency::find($temp_data->data->gateway_currency_id);
        if(!$gateway_currency || !$gateway_currency->gateway->isManual()) return redirect()->route('user.add.money.index')->with(['error' => ['Selected gateway is invalid']]);
        $gateway = $gateway_currency->gateway;
        $amount = $temp_data->data->amount ?? null;
        if(!$amount) return redirect()->route('user.add.money.index')->with(['error' => ['Transaction Failed. Failed to save information. Please try again']]);
        $wallet = UserWallet::find($temp_data->data->wallet_id ?? null);
        if(!$wallet) return redirect()->route('user.add.money.index')->with(['error' => ['Your wallet is invalid!']]);

         // Make Transaction
         DB::beginTransaction();
         try{
            $id = DB::table("transactions")->insertGetId([
                'type'                          => PaymentGatewayConst::TYPEADDMONEY,
                'trx_id'                        => generateTrxString('transactions','trx_id','AM-',14),
                'user_type'                     => GlobalConst::USER,
                'user_id'                       => $wallet->user->id,
                'wallet_id'                     => $wallet->id,
                'payment_gateway_currency_id'   => $gateway_currency->id,
                'request_amount'                => $amount->requested_amount,
                'request_currency'              => $wallet->currency->code,
                'exchange_rate'                 => $gateway_currency->rate,
                'percent_charge'                => $amount->percent_charge,
                'fixed_charge'                  => $amount->fixed_charge,
                'total_charge'                  => $amount->total_charge,
                'total_payable'                 => $amount->total_amount,
                'receive_amount'                => $amount->will_get,
                'receiver_type'                 => GlobalConst::USER,
                'receiver_id'                   => $wallet->user->id,
                'available_balance'             => $wallet->balance,
                'payment_currency'              => $gateway_currency->currency_code,
                'details'                       => json_encode(['input_values' => $temp_data->data->get_values]),
                'status'                        => PaymentGatewayConst::STATUSPENDING,
                'attribute'                     => GlobalConst::RECEIVED,
                'created_at'                    => now(),
            ]);

            user_notification_data_save($wallet->user->id,$type = PaymentGatewayConst::TYPEADDMONEY,$title = "Add Money",$id,$amount->requested_amount,$gateway_currency->name,$wallet->currency->code,$message="Successfully added.");
            DB::table("temporary_datas")->where("identifier",$request->temp_token)->delete();
            DB::commit();
         }catch(Exception $e) {
             DB::rollBack();
             return redirect()->route('user.add.money.manual.form',$request->temp_token)->with(['error' => ['Something went wrong! Please try again']]);
         }
         return redirect()->route('user.add.money.index')->with(['success' => ['Transaction Success. Please wait for admin confirmation.']]);
    }

    public function cryptoPaymentAddress(Request $request, $trx_id) {

        $page_title = "Crypto Payment Address";
        $transaction = Transaction::where('trx_id', $trx_id)->firstOrFail();

        if($transaction->gateway_currency->gateway->isCrypto() && $transaction->details?->payment_info?->receiver_address ?? false) {
            return view('user.sections.add-money.payment.crypto.address', compact(
                'transaction',
                'page_title',
            ));
        }

        return abort(404);
    }

    public function cryptoPaymentConfirm(Request $request, $trx_id)
    {
        $transaction = Transaction::where('trx_id',$trx_id)->where('status', PaymentGatewayConst::STATUSWAITING)->firstOrFail();

        $dy_input_fields = $transaction->details->payment_info->requirements ?? [];
        $validation_rules = $this->generateValidationRules($dy_input_fields);

        $validated = [];
        if(count($validation_rules) > 0) {
            $validated = Validator::make($request->all(), $validation_rules)->validate();
        }

        if(!isset($validated['txn_hash'])) return back()->with(['error' => ['Transaction hash is required for verify']]);

        $receiver_address = $transaction->details->payment_info->receiver_address ?? "";

        // check hash is valid or not
        $crypto_transaction = CryptoTransaction::where('txn_hash', $validated['txn_hash'])
                                                ->where('receiver_address', $receiver_address)
                                                ->where('asset',$transaction->gateway_currency->currency_code)
                                                ->where(function($query) {
                                                    return $query->where('transaction_type',"Native")
                                                                ->orWhere('transaction_type', "native");
                                                })
                                                ->where('status',PaymentGatewayConst::NOT_USED)
                                                ->first();

        if(!$crypto_transaction) return back()->with(['error' => ['Transaction hash is not valid! Please input a valid hash']]);

        if($crypto_transaction->amount >= $transaction->total_payable == false) {
            if(!$crypto_transaction) return back()->with(['error' => ['Insufficient amount added. Please contact with system administrator']]);
        }

        DB::beginTransaction();
        try{

            // Update user wallet balance
            DB::table($transaction->creator_wallet->getTable())
                ->where('id',$transaction->creator_wallet->id)
                ->increment('balance',$transaction->receive_amount);

            // update crypto transaction as used
            DB::table($crypto_transaction->getTable())->where('id', $crypto_transaction->id)->update([
                'status'        => PaymentGatewayConst::USED,
            ]);

            // update transaction status
            $transaction_details = json_decode(json_encode($transaction->details), true);
            $transaction_details['payment_info']['txn_hash'] = $validated['txn_hash'];

            DB::table($transaction->getTable())->where('id', $transaction->id)->update([
                'details'       => json_encode($transaction_details),
                'status'        => PaymentGatewayConst::STATUSSUCCESS,
            ]);

            DB::commit();

        }catch(Exception $e) {
            DB::rollback();
            return back()->with(['error' => ['Something went wrong! Please try again']]);
        }

        return back()->with(['success' => ['Payment Confirmation Success!']]);
    }

    public function redirectUsingHTMLForm(Request $request, $gateway)
    {
        $temp_data = TemporaryData::where('identifier', $request->token)->first();
        if(!$temp_data || $temp_data->data->action_type != PaymentGatewayConst::REDIRECT_USING_HTML_FORM) return back()->with(['error' => ['Request token is invalid!']]);
        $redirect_form_data = $temp_data->data->redirect_form_data;
        $action_url         = $temp_data->data->action_url;
        $form_method        = $temp_data->data->form_method;

        return view('payment-gateway.redirect-form', compact('redirect_form_data', 'action_url', 'form_method'));
    }

    /**
     * Redirect Users for collecting payment via Button Pay (JS Checkout)
     */
    public function redirectBtnPay(Request $request, $gateway)
    {
        try{
            
            return PaymentGatewayHelper::init([])->setProjectCurrency(PaymentGatewayConst::PROJECT_CURRENCY_SINGLE)->handleBtnPay($gateway, $request->all());
        }catch(Exception $e) {
            return redirect()->route('user.add.money.index')->with(['error' => [$e->getMessage()]]);
        }
    }

    public function authorizePaymentSubmit(Request $request){
        $temp_data = TemporaryData::where("type",PaymentGatewayConst::TYPEADDMONEY)->where("identifier",$request->token)->first();
        if(!$temp_data) return redirect()->route('user.add.money.index')->with(['success' => ['Transaction failed. Record didn\'t saved properly. Please try again.']]);

        $data        = $temp_data->data;
        $credentials = $data->credentials;
        $endpoint    = $credentials->mode === 'SANDBOX' ? $credentials->sandbox_url : $credentials->live_url;
        $amount      = $data->amount;

        $requestBody = [
            'createTransactionRequest' => [
                'merchantAuthentication' => [
                    'name'           => $credentials->login_id,
                    'transactionKey' => $credentials->transaction_key
                ],
                'refId' => generate_random_number(20),
                'transactionRequest' => [
                    'transactionType' => 'authCaptureTransaction',
                    'amount' => $amount->total_amount,
                    'currencyCode' => $amount->sender_cur_code,
                    'payment' => [
                        'opaqueData' => [
                            'dataDescriptor' => $request->dataDescriptor,
                            'dataValue' => $request->dataValue
                        ]
                    ],
                    'customerIP' => request()->ip(),
                ]
            ]
        ];

        $response = Http::withHeaders(['Content-Type' => 'application/json'])->post($endpoint, $requestBody);

        if($response->successful()){
            $responseBody = ltrim($response->getBody()->getContents(), "\xEF\xBB\xBF");
            $data = json_decode($responseBody, true);
            if($data['messages']['resultCode'] == 'Ok'){
                $update_temp_data = json_decode(json_encode($temp_data->data),true);
                $update_temp_data['capture']  = $data['transactionResponse'] ?? [];
                $temp_data->update([
                    'data'  => $update_temp_data,
                ]);
                $redirection = $this->getRedirection();
                $form_redirect_route = $redirection['return_url'];
                return redirect()->route($form_redirect_route, [PaymentGatewayConst::AUTHORIZE_DOT_NET, 'token' => $request->token]);
            }else{
                $temp_data->delete();
                return redirect()->route('user.add.money.index')->with(['error' => [$data['messages']['message'][0]['text']]]);
            }
        }else{
            $responseBody = ltrim($response->getBody()->getContents(), "\xEF\xBB\xBF");
            $data = json_decode($responseBody, true);
            $temp_data->delete();
            return redirect()->route('user.add.money.index')->with(['error' => [$data['messages']['message'][0]['text']]]);
        }

    }

    public function getRedirection() {
        $redirection = PaymentGatewayConst::registerRedirection();
        $guard = get_auth_guard();
        if(!array_key_exists($guard,$redirection)) {
            throw new Exception("Gateway Redirection URLs/Route Not Registered. Please Register in PaymentGatewayConst::class");
        }
        $gateway_redirect_route = $redirection[$guard];
        return $gateway_redirect_route;
    }
}
