<?php

namespace App\Http\Controllers\User;

use Exception;
use App\Models\UserWallet;
use App\Models\Transaction;
use Jenssegers\Agent\Agent;
use Illuminate\Http\Request;
use App\Models\TemporaryData;
use App\Constants\GlobalConst;
use Illuminate\Support\Carbon;
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
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $page_title         = "Money Out";
        $payment_gateways   = PaymentGateway::moneyOut()->manual()->active()->get();
        $user_wallets       = UserWallet::auth()->get();
        $transactions       = Transaction::auth()->moneyOut()->orderByDesc("id")->get();
        return view('user.sections.money-out.index',compact('page_title','payment_gateways','user_wallets','transactions'));
    }

    public function submit(Request $request) {

        $validated = $request->validate([
            'payment_gateway'   => "required|exists:payment_gateways,alias",
            'amount'            => "required|numeric|gt:0",
        ]);

        $default_currency = CurrencyProvider::default();

        $sender_wallet = UserWallet::auth()->whereHas('currency',function($query) use ($default_currency) {
            $query->where('code',$default_currency->code)->active();
        })->first();

        $gateway = PaymentGateway::moneyOut()->gateway($validated['payment_gateway'])->first();
        if(!$gateway->isManual()) return back()->with(['error' => ['Gateway isn\'t available for this transaction']]);
        $gateway_currency = $gateway->currencies->first();

        $charges = $this->moneyOutCharges($validated['amount'],$gateway_currency,$sender_wallet); // money-out charge

        $exchange_request_amount    = $charges->request_amount;
        $gateway_min_limit          = $gateway_currency->min_limit / $charges->exchange_rate;
        $gateway_max_limit          = $gateway_currency->max_limit / $charges->exchange_rate;

        if($exchange_request_amount < $gateway_min_limit || $exchange_request_amount > $gateway_max_limit) return back()->with(['error' => ['Please follow the transaction limit. (Min '.$gateway_min_limit . ' ' . $sender_wallet->currency->code .' - Max '.$gateway_max_limit. ' ' . $sender_wallet->currency->code . ')']]);

        // Store Temp Data
        try{
            $token = generate_unique_string("temporary_datas","identifier",16);
            TemporaryData::create([
                'type'          => PaymentGatewayConst::money_out_slug(),
                'identifier'    => $token,
                'data'          => [
                    'gateway_currency_id'   => $gateway_currency->id,
                    'wallet_id'             => $sender_wallet->id,
                    'charges'               => $charges,
                ],
            ]);
        }catch(Exception $e) {
            return back()->with(['error' => ['Something went wrong! Please try again']]);
        }

        return redirect()->route('user.money-out.instruction',$token);

    }

    public function moneyOutCharges($amount,$currency,$wallet) {

        $data['exchange_rate']          = $currency->rate;
        $data['request_amount']         = $amount;
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

    public function instruction($token) {
        $tempData = TemporaryData::where('identifier',$token)->first();
        $gateway_currency_id = $tempData->data->gateway_currency_id ?? "";
        if(!$gateway_currency_id) return redirect()->route('user.money-out.index')->with(['error' => ['Invalid Request!']]);

        $gateway_currency = PaymentGatewayCurrency::find($gateway_currency_id);
        if(!$gateway_currency) return redirect()->route('user.money-out.index')->with(['error' => ['Payment gateway currency is invalid!']]);
        $gateway = $gateway_currency->gateway;
        $input_fields = $gateway->input_fields;
        if($input_fields == null || !is_array($input_fields)) return redirect()->route('user.money-out.index')->with(['error' => ['This gateway is temporary pause or under maintenance!']]);
        $amount = $tempData->data->charges;
        $page_title = "Money Out";
        return view('user.sections.money-out.instructions',compact('page_title','gateway','token','amount'));
    }

    public function instructionSubmit(Request $request,$token) {
        $tempData = TemporaryData::where('identifier',$token)->first();
        $gateway_currency_id = $tempData->data->gateway_currency_id ?? "";
        if(!$gateway_currency_id) return redirect()->route('user.money-out.index')->with(['error' => ['Invalid Request!']]);

        $gateway_currency = PaymentGatewayCurrency::find($gateway_currency_id);
        if(!$gateway_currency) return redirect()->route('user.money-out.index')->with(['error' => ['Payment gateway currency is invalid!']]);
        $gateway = $gateway_currency->gateway;

        $wallet_id = $tempData->data->wallet_id ?? null;
        $wallet = UserWallet::auth()->active()->find($wallet_id);
        if(!$wallet) return redirect()->route('user.money-out.index')->with(['error' => ['Your wallet is invalid!']]);

        $amount = $tempData->data->charges;

        if($wallet->balance < $amount->total_payable) return redirect()->route('user.money-out.index')->with(['error' => ['Your wallet balance is insufficient!']]);

        $this->file_store_location = "transaction";
        $dy_validation_rules = $this->generateValidationRules($gateway->input_fields);
        $validated = Validator::make($request->all(),$dy_validation_rules)->validate();
        $get_values = $this->placeValueWithFields($gateway->input_fields,$validated);

        $update_temp = (array) $tempData->data;
        $update_temp['get_values'] = $get_values;

        try {
            $tempData->update(['data' => $update_temp]);
        }catch(Exception $e) {
            return redirect()->route('user.money-out.instruction',$token)->with(['error' => ['Something went wrong! Please try again']]);
        }

        return redirect()->route('user.money-out.preview', $token);
    }

    public function preview($token){
        $temp_data = TemporaryData::where('identifier',$token)->first();
        if(!$temp_data) return back()->with(['error' => ['Invalid Request']]);
        $page_title = "Money Out Details";
        return view('user.sections.money-out.preview',compact('page_title','temp_data','token'));
    }

    public function previewSubmit(Request $request){

        $validated = $request->validate([
            'temp_token' => "required|exists:temporary_datas,identifier",
            'code'     => 'nullable',
        ]);


        $temp_data = TemporaryData::where('identifier',$request->temp_token)->first();

        $gateway_currency_id = $temp_data->data->gateway_currency_id ?? "";
        if(!$gateway_currency_id) return redirect()->route('user.money-out.index')->with(['error' => ['Invalid Request!']]);

        $gateway_currency = PaymentGatewayCurrency::find($gateway_currency_id);
        if(!$gateway_currency) return redirect()->route('user.money-out.index')->with(['error' => ['Payment gateway currency is invalid!']]);

        $wallet_id = $temp_data->data->wallet_id ?? null;
        $wallet = UserWallet::auth()->active()->find($wallet_id);
        if(!$wallet) return redirect()->route('user.money-out.index')->with(['error' => ['Your wallet is invalid!']]);

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
            return redirect()->route('user.money-out.index')->with(['error' => ['Something went wrong! Please try again']]);
        }

        $temp_data->delete();

        return redirect()->route('user.money-out.index')->with(['success' => ['Transaction Success. Please wait for admin confirmation.']]);
    }

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
