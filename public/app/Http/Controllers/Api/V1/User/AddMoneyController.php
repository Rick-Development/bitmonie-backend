<?php
namespace App\Http\Controllers\Api\V1\User;

use Exception;
use App\Models\Transaction;
use Illuminate\Http\Request;
use App\Models\TemporaryData;
use App\Constants\GlobalConst;
use App\Http\Helpers\Response;
use Illuminate\Support\Facades\DB;
use App\Traits\PaymentGateway\Gpay;
use App\Http\Controllers\Controller;
use App\Models\Admin\PaymentGateway;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\RedirectResponse;
use App\Constants\PaymentGatewayConst;
use App\Models\Admin\CryptoTransaction;
use App\Traits\ControlDynamicInputFields;
use Illuminate\Support\Facades\Validator;
use App\Models\Admin\PaymentGatewayCurrency;
use App\Http\Helpers\PaymentGateway as PaymentGatewayHelper;
use App\Models\UserWallet;

class AddMoneyController extends Controller
{
    use ControlDynamicInputFields;

    public function getPaymentGateways() {
        try{
            $payment_gateways = PaymentGateway::addMoney()->active()->get();
            $payment_gateways->makeHidden(['credentials','created_at','input_fields','last_edit_by','updated_at','supported_currencies','image','env','slug','title','alias','code']);
        }catch(Exception $e) {
            return Response::error([__('Failed to fetch data. Please try again')],[],500);
        }

        $user_wallet_data       = UserWallet::with(['currency'])->auth()->first();
        if(!$user_wallet_data) return Response::error(['Sorry! Wallet not found!']);

        $user_wallet            = [
            'currency'          => $user_wallet_data->currency->code ?? '',
            'balance'           => floatval($user_wallet_data->balance) ?? '',
            'name'              => $user_wallet_data->currency->name,
            'flag'              => $user_wallet_data->currency->flag,
            'rate'              => floatval($user_wallet_data->currency->rate)
        ];

        $currency_image_paths   = [
            'base_url'          => url("/"),
            'path_location'     => files_asset_path_basename("currency-flag"),
            'default_image'     => files_asset_path_basename("default"),
        ];

        return Response::success([__('Payment gateway fetch successfully!')],[
            'payment_gateways'      => $payment_gateways,
            'user_wallet'           => $user_wallet,
            'currency_image_path'   => $currency_image_paths
        ],200);
    }

    public function automaticSubmit(Request $request) {

        try{
            $instance = PaymentGatewayHelper::init($request->all())->type(PaymentGatewayConst::TYPEADDMONEY)->setProjectCurrency(PaymentGatewayConst::PROJECT_CURRENCY_SINGLE)->gateway()->api()->render();
        }catch(Exception $e) {
            return Response::error([$e->getMessage()],[],400);
        }

        if($instance instanceof RedirectResponse === false && isset($instance['gateway_type']) && $instance['gateway_type'] == PaymentGatewayConst::MANUAL) {
            return Response::error([__('Can\'t submit manual gateway in automatic link')],[],400);
        }

        return Response::success([__('Payment gateway response successful')],[
            'redirect_url'          => $instance['redirect_url'],
            'redirect_links'        => $instance['redirect_links'],
            'action_type'           => $instance['type']  ?? false, 
            'address_info'          => $instance['address_info'] ?? [],
        ],200);
        
    }

    public function success(Request $request, $gateway){
        try{
            $token = PaymentGatewayHelper::getToken($request->all(),$gateway);
            $temp_data = TemporaryData::where("type",PaymentGatewayConst::TYPEADDMONEY)->where("identifier",$token)->first();

            if(!$temp_data) {
                if(Transaction::where('callback_ref',$token)->exists()) {
                    return Response::success([__('Transaction request sended successfully!')],[],400);
                }else {
                    return Response::error([__('Transaction failed. Record didn\'t saved properly. Please try again')],[],400);
                }
            }

            $update_temp_data = json_decode(json_encode($temp_data->data),true);
            $update_temp_data['callback_data']  = $request->all();
            $temp_data->update([
                'data'  => $update_temp_data,
            ]);
            $temp_data = $temp_data->toArray();
            $instance = PaymentGatewayHelper::init($temp_data)->type(PaymentGatewayConst::TYPEADDMONEY)->setProjectCurrency(PaymentGatewayConst::PROJECT_CURRENCY_SINGLE)->responseReceive();

        }catch(Exception $e) {
            return Response::error([$e->getMessage()],[],500);
        }
        return Response::success([__('Successfully added money')],[],200);
    }

    public function cancel(Request $request,$gateway) {
        $token = PaymentGatewayHelper::getToken($request->all(),$gateway);
        $temp_data = TemporaryData::where("type",PaymentGatewayConst::TYPEADDMONEY)->where("identifier",$token)->first();
        try{
            if($temp_data != null) {
                $temp_data->delete();
            }
        }catch(Exception $e) {
            // Handel error
        }
        return Response::success([__('Payment process cancel successfully!')],[],200);
    }

    public function postSuccess(Request $request, $gateway)
    {
        try{
            $token = PaymentGatewayHelper::getToken($request->all(),$gateway);
            $temp_data = TemporaryData::where("type",PaymentGatewayConst::TYPEADDMONEY)->where("identifier",$token)->first();
            if($temp_data && $temp_data->data->creator_guard != 'api') {
                Auth::guard($temp_data->data->creator_guard)->loginUsingId($temp_data->data->creator_id);
            }
        }catch(Exception $e) {
            return Response::error([$e->getMessage()]);
        }

        return $this->success($request, $gateway);
    }

    public function postCancel(Request $request, $gateway)
    {
        try{
            $token = PaymentGatewayHelper::getToken($request->all(),$gateway);
            $temp_data = TemporaryData::where("type",PaymentGatewayConst::TYPEADDMONEY)->where("identifier",$token)->first();
            if($temp_data && $temp_data->data->creator_guard != 'api') {
                Auth::guard($temp_data->data->creator_guard)->loginUsingId($temp_data->data->creator_id);
            }
        }catch(Exception $e) {
            return Response::error([$e->getMessage()]);
        }

        return $this->cancel($request, $gateway);
    }

    public function manualInputFields(Request $request) {
        $validator = Validator::make($request->all(),[
            'alias'         => "required|string|exists:payment_gateway_currencies",
        ]);

        if($validator->fails()) {
            return Response::error($validator->errors()->all(),[],400);
        }

        $validated = $validator->validate();
        $gateway_currency = PaymentGatewayCurrency::where("alias",$validated['alias'])->first();

        $gateway = $gateway_currency->gateway;

        if(!$gateway->isManual()) return Response::error([__('Can\'t get fields. Requested gateway is automatic')],[],400);

        if(!$gateway->input_fields || !is_array($gateway->input_fields)) return Response::error([__("This payment gateway is under constructions. Please try with another payment gateway")],[],503);

        try{
            $input_fields = json_decode(json_encode($gateway->input_fields),true);
            $input_fields = array_reverse($input_fields);
        }catch(Exception $e) {
            return Response::error([__("Something went wrong! Please try again")],[],500);
        }
        
        return Response::success([__('Payment gateway input fields fetch successfully!')],[
            'gateway'           => [
                'desc'          => $gateway->desc
            ],
            'input_fields'      => $input_fields,
            'currency'          => $gateway_currency->only(['alias']),
        ],200);
    }

    public function manualSubmit(Request $request) {

        try{
            $instance = PaymentGatewayHelper::init($request->only(['currency','amount']))->setProjectCurrency(PaymentGatewayConst::PROJECT_CURRENCY_SINGLE)->gateway()->get();
        }catch(Exception $e) {
            return Response::error([$e->getMessage()],[],401);
        }

        // Check it's manual or automatic
        if(!isset($instance['gateway_type']) || $instance['gateway_type'] != PaymentGatewayConst::MANUAL) return Response::error([__('Can\'t submit automatic gateway in manual link')],[],400);

        $gateway = $instance['gateway'] ?? null;
        $gateway_currency = $instance['currency'] ?? null;
        if(!$gateway || !$gateway_currency || !$gateway->isManual()) return Response::error([__('Selected gateway is invalid')],[],400);

        $amount = $instance['amount'] ?? null;
        if(!$amount) return Response::error([__('Transaction Failed. Failed to save information. Please try again')],[],400);

        $wallet = $wallet = $instance['wallet'] ?? null;
        if(!$wallet) return Response::error([__('Your wallet is invalid!')],[],400);

        $this->file_store_location = "transaction";
        $dy_validation_rules = $this->generateValidationRules($gateway->input_fields);

        $validator = Validator::make($request->all(),$dy_validation_rules);
        if($validator->fails()) return Response::error($validator->errors()->all(),[],400);
        $validated = $validator->validate();
        $get_values = $this->placeValueWithFields($gateway->input_fields,$validated);

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
                'details'                       => json_encode(['input_values' => $get_values]),
                'status'                        => PaymentGatewayConst::STATUSPENDING,
                'created_at'                    => now(),
            ]);

            DB::commit();
        }catch(Exception $e) {
            DB::rollBack();
            return Response::error([__("Something went wrong! Please try again")],[],500);
        }
        return Response::success([__('Transaction Success. Please wait for admin confirmation')],[],200);
    }

    public function gatewayAdditionalFields(Request $request) {
        $validator = Validator::make($request->all(),[
            'currency'          => "required|string|exists:payment_gateway_currencies,alias",
        ]);
        if($validator->fails()) return Response::error($validator->errors()->all(),[],400);
        $validated = $validator->validate();

        $gateway_currency = PaymentGatewayCurrency::where("alias",$validated['currency'])->first();

        $gateway = $gateway_currency->gateway;

        $data['available'] = false;
        $data['additional_fields']  = [];
        if(Gpay::isGpay($gateway)) {
            $gpay_bank_list = Gpay::getBankList();
            if($gpay_bank_list == false) return Response::error(['Gpay bank list server response failed! Please try again'],[],500);
            $data['available']  = true;

            $gpay_bank_list_array = json_decode(json_encode($gpay_bank_list),true);

            $gpay_bank_list_array = array_map(function ($array){
                
                $data['name']       = $array['short_name_by_gpay'];
                $data['value']      = $array['gpay_bank_code'];

                return $data;
        
            }, $gpay_bank_list_array);

            $data['additional_fields'][] = [
                'type'      => "select",
                'label'     => "Select Bank",
                'title'     => "Select Bank",
                'name'      => "bank",
                'values'    => $gpay_bank_list_array,
            ];

        }

        return Response::success([__('Request response fetch successfully!')],$data,200);
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

        if(!isset($validated['txn_hash'])) return Response::error(['Transaction hash is required for verify']);

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
                                                
        if(!$crypto_transaction) return Response::error(['Transaction hash is not valid! Please input a valid hash'],[],404);

        if($crypto_transaction->amount >= $transaction->total_payable == false) {
            if(!$crypto_transaction) Response::error(['Insufficient amount added. Please contact with system administrator'],[],400);
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
            return Response::error(['Something went wrong! Please try again'],[],500);
        }

        return Response::success(['Payment Confirmation Success!'],[],200);
    }
    /**
     * Redirect Users for collecting payment via Button Pay (JS Checkout)
     */
    public function redirectBtnPay(Request $request, $gateway){
        try{
            return PaymentGatewayHelper::init([])->type(PaymentGatewayConst::TYPEADDMONEY)->setProjectCurrency(PaymentGatewayConst::PROJECT_CURRENCY_SINGLE)->handleBtnPay($gateway, $request->all());
        }catch(Exception $e) {
            return Response::error([$e->getMessage()], [], 500);
        }
    }
}
