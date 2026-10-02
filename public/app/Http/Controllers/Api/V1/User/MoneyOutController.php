<?php

namespace App\Http\Controllers\Api\V1\User;

use Exception;
use Carbon\Carbon;
use App\Models\UserWallet;
use App\Models\Transaction;
use Jenssegers\Agent\Agent;
use Illuminate\Http\Request;
use App\Models\TemporaryData;
use App\Constants\GlobalConst;
use App\Http\Helpers\Response;
use Illuminate\Support\Facades\DB;
use App\Constants\NotificationConst;
use App\Http\Controllers\Controller;
use App\Models\Admin\PaymentGateway;
use App\Constants\PaymentGatewayConst;
use App\Models\Admin\AdminNotification;
use App\Providers\Admin\CurrencyProvider;
use App\Traits\ControlDynamicInputFields;
use Illuminate\Support\Facades\Validator;
use App\Models\Admin\PaymentGatewayCurrency;
use App\Providers\Admin\BasicSettingsProvider;
use App\Notifications\User\MoneyOutNotification;

class MoneyOutController extends Controller
{
    use ControlDynamicInputFields;
    /**
     * Method for get money out info
     */
    public function info(){

        // User Wallet
        $user_wallet_data   = UserWallet::with(['currency'])->auth()->first();
        if(!$user_wallet_data) return Response::error(['Sorry! Wallet not found!']);
        $user_wallet        = [
            'currency'          => $user_wallet_data->currency->code ?? '',
            'balance'           => floatval($user_wallet_data->balance) ?? '',
            'name'              => $user_wallet_data->currency->name,
            'flag'              => $user_wallet_data->currency->flag,
            'rate'              => floatval($user_wallet_data->currency->rate)
        ];

        $currency_image_paths = [
            'base_url'          => url("/"),
            'path_location'     => files_asset_path_basename("currency-flag"),
            'default_image'     => files_asset_path_basename("default"),
        ];

        $payment_gateway        = PaymentGatewayCurrency::whereHas('gateway', function ($gateway) {
            $gateway->where('slug', PaymentGatewayConst::money_out_slug());
            $gateway->where('status', 1);
        })->get()->map(function($data){
            return [
                'name'          => $data->gateway->name,
                'alias'         => $data->gateway->alias,
                'min_limit'     => floatval($data->min_limit),
                'max_limit'     => floatval($data->max_limit),
                'fixed_charge'  => floatval($data->fixed_charge),
                'percent_charge'=> floatval($data->percent_charge),
                'rate'          => floatval($data->rate),
                'gateway_currency'          => $data->currency_code,
            ];
        });
        
        return Response::success(['Information fetch successfully.'],[
            'user_wallet'       => $user_wallet,
            'image_path'        => $currency_image_paths,
            'payment_gateways'  => $payment_gateway,
        ],200);
    }
    /**
     * Method for store money out information
     * @param Illuminate\Http\Request $request
     */
    public function submit(Request $request) {
        $validator = Validator::make($request->all(),[
            'payment_gateway'   => "required|exists:payment_gateways,alias",
            'amount'            => "required|numeric|gt:0",
        ]);
        if($validator->fails()){
            return Response::error($validator->errors()->all(),[]);
        }
        $validated  = $validator->validated();

        $default_currency = CurrencyProvider::default();

        $sender_wallet = UserWallet::auth()->whereHas('currency',function($query) use ($default_currency) {
            $query->where('code',$default_currency->code)->active();
        })->first();

        $gateway = PaymentGateway::moneyOut()->gateway($validated['payment_gateway'])->first();
        if(!$gateway->isManual()) return Response::error([__('Gateway isn\'t available for this transaction.')],[],400);

        $gateway_currency = $gateway->currencies->first();

        $charges = $this->moneyOutCharges($validated['amount'],$gateway_currency,$sender_wallet); // money-out charge

        $exchange_request_amount    = $charges->request_amount;
        $gateway_min_limit          = $gateway_currency->min_limit / $charges->exchange_rate;
        $gateway_max_limit          = $gateway_currency->max_limit / $charges->exchange_rate;

        if($exchange_request_amount < $gateway_min_limit || $exchange_request_amount > $gateway_max_limit) return Response::error(['Please follow the transaction limit. (Min '.$gateway_min_limit . ' ' . $sender_wallet->currency->code .' - Max '.$gateway_max_limit. ' ' . $sender_wallet->currency->code . ')'],[],400);

        // Store Temp Data
        try{
            $token = generate_unique_string("temporary_datas","identifier",16);
            $temp_data = TemporaryData::create([
                'type'          => PaymentGatewayConst::money_out_slug(),
                'identifier'    => $token,
                'data'          => [
                    'gateway_currency_id'   => $gateway_currency->id,
                    'wallet_id'             => $sender_wallet->id,
                    'charges'               => $charges,
                ],
            ]);
        }catch(Exception $e) {
            return Response::error([__('Something went wrong! Please try again')],[],400);
        }
        $data                   = [
            'type'              => $temp_data->type,
            'identifier'        => $temp_data->identifier,
            'wallet_id'         => $temp_data->data->wallet_id,
            'charges'           => $temp_data->data->charges
        ];
        return Response::success([__('Money out information saved successfully')],[
            'temp_data'      => $data
        ],200);

    }
    public function moneyOutCharges($amount,$currency,$wallet) {

        $data['exchange_rate']          = floatval($currency->rate);
        $data['request_amount']         = floatval($amount);
        $data['fixed_charge']           = $currency->fixed_charge / $data['exchange_rate'];
        $data['percent_charge']         = ((($amount * $currency->rate) / 100) * $currency->percent_charge) / $currency->rate;
        $data['gateway_currency_code']  = $currency->currency_code;
        $data['gateway_currency_id']    = $currency->id;
        $data['sender_currency_code']   = $wallet->currency->code;
        $data['sender_wallet_id']       = $wallet->id;
        $data['will_get']               = ($amount * $data['exchange_rate']);
        $data['receive_currency']       = $currency->currency_code;
        $data['sender_currency']        = $wallet->currency->code;
        $data['total_charge']           = $data['fixed_charge'] + $data['percent_charge']; // in sender currency
        $data['total_payable']          = $data['request_amount'] + $data['total_charge']; // in sender currency

        return (object) $data;
    }
    /**
     * Method for get the payment gateway instructions
     * @param Illuminate\Http\Request $request
     */
    public function instruction(Request $request){
        $validator  = Validator::make($request->all(),[
            'token' => 'required'
        ]);
        if($validator->fails()){
            return Response::error($validator->errors()->all(),[]);
        }
        $validated = $validator->validate();
        $tempData = TemporaryData::where('identifier',$validated['token'])->first();
        if(!$tempData) return Response::error([__('Data not found!')],[],400);

        $gateway_currency_id = $tempData->data->gateway_currency_id ?? "";
        if(!$gateway_currency_id) return Response::error([__('Invalid Request!')],[],400);

        $gateway_currency = PaymentGatewayCurrency::find($gateway_currency_id);
        if(!$gateway_currency) return Response::error([__('Payment gateway currency is invalid!')],[],400);
        $gateway = $gateway_currency->gateway;
        $input_fields = $gateway->input_fields;
        if($input_fields == null || !is_array($input_fields)) return Response::error([__('This gateway is temporary pause or under maintenance!')],[],400); 

        return Response::success(['Payment gateway field fetch successfully.'],[
            'desc'          => strip_tags($gateway->desc),
            'input_fields'  => $input_fields
        ],200);
    }
    /**
     * Method for submit instruction data
     * @param Illuminate\Http\Request $request
     */
    public function instructionSubmit(Request $request){
        $validators      = Validator::make($request->all(),[
            'token'     => 'required',
        ]);
        
        $tempData = TemporaryData::where('identifier',$request->token)->first();
        $gateway_currency_id = $tempData->data->gateway_currency_id ?? "";
        if(!$gateway_currency_id) return Response::error([__('Invalid Request!')],[],400);

        $gateway_currency = PaymentGatewayCurrency::find($gateway_currency_id);
        if(!$gateway_currency) return Response::error([__('Payment gateway currency is invalid!')],[],400);
        $gateway = $gateway_currency->gateway;

        $wallet_id = $tempData->data->wallet_id ?? null;
        $wallet = UserWallet::auth()->active()->find($wallet_id);
        if(!$wallet) return Response::error([__('Your wallet is invalid!')],[],400);

        $amount = $tempData->data->charges;

        if($wallet->balance < $amount->total_payable) return Response::error([__('Your wallet balance is insufficient!')],[],400);

        $this->file_store_location = "transaction";
        $dy_validation_rules = $this->generateValidationRules($gateway->input_fields);
        $validator = Validator::make($request->all(),$dy_validation_rules);
        if($validator->fails()){
            return Response::error($validator->errors()->all(),[]);
        }
        $validated      = $validator->validate();
        $get_values = $this->placeValueWithFields($gateway->input_fields,$validated);

        $update_temp = (array) $tempData->data;
        $update_temp['get_values'] = $get_values;

        try {
            $tempData->update(['data' => $update_temp]);
        }catch(Exception $e) {
            return Response::error([__('Something went wrong! Please try again')],[],400);
        }

        return Response::success(['Information submitted successfully.'],[
            'temp_data' => $tempData,
        ],200);
    }
    /**
     * Method for confirm money out request
     * @param Illuminate\Http\Request $request
     */
    public function confirm(Request $request){

        $validated = $request->validate([
            'token'    => "required|exists:temporary_datas,identifier",
        ]);

        if($request->has('pin_code')) {
            if(auth()->user()->pin_code !== $request->pin_code) {
                 return Response::error([__('Invalid Transaction PIN')], [], 400); 
            }
        }

        $temp_data = TemporaryData::where('identifier',$request->token)->first();

        $gateway_currency_id = $temp_data->data->gateway_currency_id ?? "";
        if(!$gateway_currency_id) return Response::error([__('Invalid Request!')],[],400);
        $gateway_currency = PaymentGatewayCurrency::find($gateway_currency_id);
        if(!$gateway_currency) return Response::error([__('Payment gateway currency is invalid!')],[],400);

        $wallet_id = $temp_data->data->wallet_id ?? null;
        $wallet = UserWallet::auth()->active()->find($wallet_id);
        if(!$wallet) return Response::error([__('Your wallet is invalid!')],[],400);
        $amount = $temp_data->data->charges;

        $wallet_balance = 0;
        $wallet_balance = $wallet->balance;
        if($wallet->balance < $amount->total_payable) return redirect()->route('user.money-out.index')->with(['error' => ['Your wallet balance is insufficient!']]);

        $trx_id = generateTrxString('transactions','trx_id','MO-',14);
        // Make Transaction
        DB::beginTransaction();
        try{
            $transaction =  Transaction::create([
                'type'                          => PaymentGatewayConst::TYPEMONEYOUT,
                'trx_id'                        => $trx_id,
                'user_type'                     => GlobalConst::USER,
                'user_id'                       => $wallet->user->id,
                'wallet_id'                     => $wallet->id,
                'payment_gateway_currency_id'   => $gateway_currency->id,
                'request_amount'                => $amount->request_amount,
                'request_currency'              => $wallet->currency->code,
                'exchange_rate'                 => $amount->exchange_rate,
                'percent_charge'                => $amount->percent_charge,
                'fixed_charge'                  => $amount->fixed_charge,
                'total_charge'                  => $amount->total_charge,
                'total_payable'                 => $amount->total_payable,
                'available_balance'             => $wallet_balance - $amount->total_payable,
                'receive_amount'                => $amount->will_get,
                'receiver_type'                 => GlobalConst::USER,
                'receiver_id'                   => $wallet->user->id,
                'payment_currency'              => $gateway_currency->currency_code,
                'details'                       => ['input_values' => $temp_data->data->get_values,'charges' => $amount],
                'status'                        => PaymentGatewayConst::STATUSPENDING,
                'attribute'                     => GlobalConst::SEND,
                'created_at'                    => now(),
            ]);

            $this->createTransactionDeviceRecord($transaction->id);

            DB::table($wallet->getTable())->where("id",$wallet->id)->update([
                'balance'       => ($wallet->balance - $amount->total_payable),
            ]);

            user_notification_data_save(auth()->user()->id,$type = PaymentGatewayConst::TYPEMONEYOUT,$title = "Money Out",$transaction->id,$amount->request_amount,$gateway = null,$currency = get_default_currency_code(),$message = "Money Out Successful.");
            $this->notification($amount);
            $basic_settings = BasicSettingsProvider::get();
            if($basic_settings->email_notification){
                try{
                    $wallet->user->notify(new MoneyOutNotification($wallet->user, $transaction));
                }catch(Exception $e){}
            }
            DB::commit();
        }catch(Exception $e) {
            DB::rollBack();
            return Response::error([__('Something went wrong! Please try again')],[],400);
        }

        $temp_data->delete();
        return Response::success(['Transaction Success. Please wait for admin confirmation.'],[],200);
    }
    // create transaction device record
    public function createTransactionDeviceRecord($transaction_id) {
        $client_ip = request()->ip() ?? false;
        $location = geoip()->getLocation($client_ip);
        $agent = new Agent();

        $mac = "";

        DB::beginTransaction();
        try{
            DB::table("transaction_devices")->insert([
                'transaction_id'=> $transaction_id,
                'ip'            => $client_ip,
                'mac'           => $mac,
                'city'          => $location['city'] ?? "",
                'country'       => $location['country'] ?? "",
                'longitude'     => $location['lon'] ?? "",
                'latitude'      => $location['lat'] ?? "",
                'timezone'      => $location['timezone'] ?? "",
                'browser'       => $agent->browser() ?? "",
                'os'            => $agent->platform() ?? "",
            ]);
            DB::commit();
        }catch(Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
    }
    // admin notification
    public function notification($charges){
        
        $notification_content_admin = [
            'title'         => "Money Out Request",
            'message'       => "Money out request for ".get_amount($charges->total_payable).' '. $charges->sender_currency_code,
            'time'          => Carbon::now()->diffForHumans(),
            'image'         => auth()->user()->userImage,
        ];
        AdminNotification::create([
            'type'      => NotificationConst::SIDE_NAV,
            'admin_id'  => 1,
            'message'   => $notification_content_admin,
        ]);
    }

}
