<?php

namespace App\Http\Controllers\Api\V1\User;

use Exception;
use App\Models\User;
use App\Models\UserWallet;
use App\Models\Beneficiary;
use App\Models\Transaction;
use Illuminate\Support\Str;
use Jenssegers\Agent\Agent;
use Illuminate\Http\Request;
use App\Models\TemporaryData;
use App\Constants\GlobalConst;
use App\Http\Helpers\Response;
use App\Models\Admin\Currency;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\RedirectResponse;
use App\Constants\PaymentGatewayConst;
use App\Models\Admin\TransactionSetting;
use App\Providers\Admin\CurrencyProvider;
use Illuminate\Support\Facades\Validator;
use App\Providers\Admin\BasicSettingsProvider;
use App\Notifications\User\FundTransfer\OwnBankSenderNotification;
use App\Notifications\User\FundTransfer\OtherBankSenderNotification;
use App\Notifications\User\FundTransfer\OwnBankReceiverNotification;

class FundTransferController extends Controller
{
    /**
     * Method for select beneficiary and save information
     * @param Illuminate\Http\Request $request
     */
    public function beneficiarySelect(Request $request){
        $validator          = Validator::make($request->all(),[
            'beneficiary_id'=> 'required'
        ]);
        if($validator->fails()) return Response::error($validator->errors()->all(),[]);
        $validated          = $validator->validate();
        $beneficiary        = Beneficiary::find($validated['beneficiary_id']);
        if(!$beneficiary){
            return back()->with(['error' => ['Beneficiary Not Found!']]);
        }
        $confirm_methods = [
            Str::slug(GlobalConst::TRX_OWN_BANK_TRANSFER)   => "ownBankTransferSelect",
            Str::slug(GlobalConst::TRX_OTHER_BANK_TRANSFER)   => "otherBankTransferSelect",
        ];
        if(!array_key_exists($beneficiary->info->method->slug,$confirm_methods)) return Response::error([__("Method not found")],[],400);
        $method = $confirm_methods[$beneficiary->method->slug];

        if(!method_exists($this,$method)) {
            return Response::error([__("This section is under construction.")],[],400);
        }
        
        return Response::success([__("Information saved successfully.")],[
            'temp_data'     => $this->$method($request,$beneficiary)
        ],200);
    }
    /**
     * Own Bank Transfer Select
     * @param App\Models\Beneficiary $beneficiary
     * @param Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function ownBankTransferSelect(Request $request, Beneficiary $beneficiary){
        $data['beneficiary'] = $beneficiary->info;

        $temp_identifier = generate_unique_string('temporary_datas','identifier',60);

        try{
            $temp_data = TemporaryData::create([
                'type'          => GlobalConst::FUND_TRANSFER,
                'identifier'    => $temp_identifier,
                'data'          => $data,
            ]);
        }catch(Exception $e) {
            return Response::error([__("Something went wrong!. Please try again.")],[],400);
        }
        return $temp_data;
    }
    /**
     * Method for get the charge information
     * @return response
     */
    public function chargeInfo(Request $request){
        $validator          = Validator::make($request->all(),[
            'identifier'    => 'required',
        ]);
        if($validator->fails()) return Response::error($validator->errors()->all(),[]);
        $validated          = $validator->validate();
        $temp_data          = TemporaryData::where('identifier', $validated['identifier'])->first();
        if(!$temp_data) return Response::error([__("Invalid Request")],[],400);
        $fees_and_charge    = TransactionSetting::whereSlug($temp_data->data->beneficiary->method->slug)->active()->first();
        if(!$fees_and_charge) return Response::error([__("Fess And Charge Not Defined, Please Contact With Support!")],[],400);
        $base_currency      = Currency::default();
        $data               = [
            'currency'      => $base_currency->currency_code,
            'rate'          => floatval($base_currency->rate),
            'slug'          => $fees_and_charge->slug,
            'title'         => $fees_and_charge->title,
            'fixed_charge'  => floatval($fees_and_charge->fixed_charge),
            'percent_charge'=> floatval($fees_and_charge->percent_charge),
            'min_limit'     => floatval($fees_and_charge->min_limit),
            'max_limit'     => floatval($fees_and_charge->max_limit),
            'monthly_limit' => floatval($fees_and_charge->monthly_limit),
            'daily_limit'   => floatval($fees_and_charge->daily_limit),
        ];

        return Response::success([__("Fees and charges data fetch successfully.")],[
            'fees_and_charges'  => $data
        ],200);
    }
    /**
     * Method for submit fund transfer information
     * @param Illuminate\Http\Request $request
     */
    public function submit(Request $request){
        $validator = Validator::make($request->all(), [
            'identifier' => 'required|exists:temporary_datas,identifier',
            'amount'     => 'required|numeric|gt:0',
            'remarks'    => 'nullable|string',
        ]);
        if($validator->fails()) return Response::error($validator->errors()->all(),[]);
        $validated       = $validator->validate();
        $temp_data = TemporaryData::where('identifier', $validated['identifier'])->first();
        if(!$temp_data) return Response::error([__("Invalid Request")],[],400);

        $user_wallet = UserWallet::active()->where('user_id', Auth::id())->first();
        if(!$user_wallet) return Response::error([__("Your wallet not found.")],[],400);

        if($validated['amount'] > $user_wallet->balance) return Response::error([__("Your wallet balance is insufficient")],[],400);

        $fees_and_charge = TransactionSetting::whereSlug($temp_data->data->beneficiary->method->slug)->active()->first();

        $confirm_methods = [
            Str::slug(GlobalConst::TRX_OWN_BANK_TRANSFER)   => "ownBankTransferSubmit",
            Str::slug(GlobalConst::TRX_OTHER_BANK_TRANSFER)   => "otherBankTransferSubmit",
        ];

        if(!array_key_exists($temp_data->data->beneficiary->method->slug,$confirm_methods)) abort(404);
        $method = $confirm_methods[$temp_data->data->beneficiary->method->slug];
        
        if(!method_exists($this,$method)) {
            return Response::error([__("This section is under construction.")],[],400);
        }
        $submit_data    = $this->$method($validated,$fees_and_charge,$temp_data);
        if(isset($submit_data['status'])){
            if($submit_data['status'] == false){
                return Response::error([$submit_data['message']],[],400);
            }
            
        }
        return Response::success([__("Data submitted  successfully.")],[
            'temp_data'     => $this->$method($validated,$fees_and_charge,$temp_data)
        ],200);
        
    }
     /**
     *  Own Bank Transfer Submit
     *
     * @param  $token
     * @return \Illuminate\Http\Response
     */
    public function ownBankTransferSubmit($validated,$fees_and_charge,$temp_data){

        $user_wallet = UserWallet::where('user_id', Auth::id())->first();
        $charge_calculation = $this->transferCharges($validated['amount'], $fees_and_charge, $user_wallet);

        $sender_wallet = UserWallet::active()->where('user_id', Auth::id())->first();
        if(!$sender_wallet) return [
            'status'    => false,
            'message'   => 'Your wallet not found.'
        ];
        if($charge_calculation['payable'] > $sender_wallet->balance) return [
            'status'    => false,
            'message'   => 'Insufficient Balance'
        ];

        $limit_response = $this->transactionLimitCheck($charge_calculation, $fees_and_charge); // Daily And Monthly Limit Check
        
        if($limit_response instanceof RedirectResponse) return $limit_response;

        $update_data = (array) $temp_data->data;
        $update_data['charges'] = $charge_calculation;
        $update_data['remark'] = $validated['remarks'];

        try{
            $temp_data->update(['data' => $update_data]);
        }catch(Exception $e) {
            return Response::error([__("This section is under construction.")],[],400);
        }

        return $temp_data;
    }
    /**
     *  Charges Calculation
     *
     * @param $sender_amount, $charges, $user_wallet
     * @return \Illuminate\Http\Response
     */
    public function transferCharges($sender_amount,$charges,$user_wallet) {
        $data['request_amount']        = floatval($sender_amount);
        $data['sender_currency']       = get_default_currency_code();
        $data['receiver_amount']       = floatval($sender_amount);
        $data['receiver_currency']     = get_default_currency_code();
        $data['receiver_currency_id']  = $user_wallet->currency->id;
        $data['percent_charge']        = ($sender_amount / 100) * $charges->percent_charge ?? 0;
        $data['fixed_charge']          = floatval($charges->fixed_charge) ?? 0;
        $data['total_charge']          = $data['percent_charge'] + $data['fixed_charge'];
        $data['sender_wallet_balance'] = floatval($user_wallet->balance);
        $data['payable']               = $sender_amount + $data['total_charge'];
        return $data;
    }
    // limit check
    function transactionLimitCheck($charge_calculation, $fees_and_charge){

        // Check Daily Transaction Limit
        $default_currency = CurrencyProvider::default();
        $user_daily_total_transactions = Transaction::where('user_id',auth()->user()->id)->OwnBankTransfer()->today()->get()->sum('request_amount');
    
        if(($user_daily_total_transactions + $charge_calculation['request_amount']) > $fees_and_charge->daily_limit) {
            return [
                'status'    => 'false',
                'message'   => __('Your daily transaction limit is over. You can transaction maximum ' . get_amount($fees_and_charge->daily_limit,$default_currency->code) . '. You already completed ' . get_amount($user_daily_total_transactions,$default_currency->code) . ' equal money.')
            ];  
        }
    
        // Check Monthly Transaction Limit
        $user_monthly_total_transactions = Transaction::where('user_id',auth()->user()->id)->OwnBankTransfer()->whereBetween('created_at',[now()->firstOfMonth(),now()->lastOfMonth()])->get()->sum('request_amount');
    
        if(($user_monthly_total_transactions + $charge_calculation['request_amount']) > $fees_and_charge->monthly_limit) {
            return [
                'status'    => 'false',
                'message'   => __('Your monthly transaction limit is over. You can transaction maximum ' . get_amount($fees_and_charge->monthly_limit,$default_currency->code) . '. You already completed ' . get_amount($user_monthly_total_transactions,$default_currency->code) . ' equal money.')
            ]; 
        }
    }
    /**
     *  Fund Transfer Submit
     * @method POST
     * @return \Illuminate\Http\Response
     */
    public function confirm(Request $request){
        $validated = $request->validate([
            'identifier' => "required|exists:temporary_datas,identifier",
        ]);
        $confirm_methods = [
            Str::slug(GlobalConst::TRX_OWN_BANK_TRANSFER)   => "ownBankTransferConfirm",
            Str::slug(GlobalConst::TRX_OTHER_BANK_TRANSFER)   => "otherBankTransferConfirm",
        ];

        $temp_data = TemporaryData::where('identifier', $validated['identifier'])->first();
        if(!$temp_data) return Response::error([__("Invalid Request")],[],400);

        if(!array_key_exists($temp_data->data->beneficiary->method->slug,$confirm_methods)) abort(404);

        $method = $confirm_methods[$temp_data->data->beneficiary->method->slug];

        if(!method_exists($this,$method)) {
            return Response::error([__("This section is under construction.")],[],400);
        }
        
        return $this->$method($temp_data);
    }
    /**
     *  Own Bank Transfer Submit
     *
     * @param App\Models\TemporaryData $temp_data
     * @return \Illuminate\Http\Response
     */


    /**
     *  Own Bank Transfer Submit
     *
     * @param App\Models\TemporaryData $temp_data
     * @return \Illuminate\Http\Response
     */
    public function ownBankTransferConfirm(TemporaryData $temp_data){
        // PIN Check
        $request = request();
        if($request->has('pin_code')) {
             if(Auth::user()->pin_code !== $request->pin_code) {
                  return Response::error([__('Invalid Transaction PIN')], [], 400);
             }
        }

        $sender_wallet = UserWallet::active()->where('user_id', Auth::id())->first();
        if(!$sender_wallet) return Response::error([__("Your wallet not found")],[],400);
        
        $receiver        = User::active()->where('email',$temp_data->data->beneficiary->email)->first();
        if(!$receiver) return Response::error([__("Receiver not found")],[],400);
        $receiver_wallet = UserWallet::active()->where('user_id', $receiver->id)->first();
        if(!$receiver_wallet) return Response::error([__("Receiver wallet not found")],[],400);

        $charges = $temp_data->data->charges;
        
        if($charges->payable > $sender_wallet->balance) return Response::error([__("Your wallet balance is insufficient")],[],400);

        $trx_id = generateTrxString('transactions','trx_id','FT-',14);
        // Make Transaction
        DB::beginTransaction();
        try{
            $transaction =  Transaction::create([
                'type'                          => PaymentGatewayConst::TYPE_OWN_BANK_TRANSFER,
                'trx_id'                        => $trx_id,
                'user_type'                     => GlobalConst::USER,
                'user_id'                       => $sender_wallet->user->id,
                'wallet_id'                     => $sender_wallet->id,
                'request_amount'                => $charges->request_amount,
                'request_currency'              => $charges->sender_currency,
                'exchange_rate'                 => $sender_wallet->currency->rate,
                'percent_charge'                => $charges->percent_charge,
                'fixed_charge'                  => $charges->fixed_charge,
                'total_charge'                  => $charges->total_charge,
                'total_payable'                 => $charges->payable,
                'available_balance'             => $sender_wallet->balance - $charges->payable,
                'receive_amount'                => $charges->request_amount,
                'receiver_type'                 => GlobalConst::USER,
                'receiver_id'                   => $sender_wallet->user->id,
                'payment_currency'              => $sender_wallet->currency->code,
                'remark'                        => $temp_data->data->remark ?? '',
                'receiver_id'                   => $receiver->id,
                'details'                       => ['beneficiary' => $temp_data->data->beneficiary,'charges' => $charges],
                'status'                        => PaymentGatewayConst::STATUSSUCCESS,
                'created_at'                    => now(),
            ]);

            DB::table('user_wallets')->where("id",$sender_wallet->id)->update([
                'balance'       => ($sender_wallet->balance - $charges->payable),
            ]);

            DB::table('user_wallets')->where("id",$receiver_wallet->id)->update([
                'balance'       => ($receiver_wallet->balance + $charges->request_amount),
            ]);

            $this->createTransactionDeviceRecord($transaction->id);

            $temp_data->delete();
            DB::commit();
        }catch(Exception $e) {
            DB::rollBack();
            return Response::error([__('Something went wrong! Please try again')],[],400);
        }

        try {
            user_notification_data_save($sender_wallet->user->id,$type = PaymentGatewayConst::TYPE_OWN_BANK_TRANSFER,$title = "Fund Transfer",$transaction->id,$charges->request_amount,$gateway = null,$currency = $charges->sender_currency,$message = "Fund Transfer Successful.");

            $basic_settings = BasicSettingsProvider::get();
            if($basic_settings->email_notification){
                try{
                    $sender_wallet->user->notify(new OwnBankSenderNotification($sender_wallet->user, $transaction));
                    $receiver_wallet->user->notify(new OwnBankReceiverNotification($receiver, $transaction));
                }catch(Exception $e){}
            }
        } catch (Exception $e) {
            return Response::error([__('Something went wrong! Please try again')],[],400);
        }

        return Response::success([__("Fund transfer successfully done!")],[],200);
    }
    /**
     *  Transaction Device Record
     *
     * @param $sender_amount, $charges, $user_wallet
     * @return \Illuminate\Http\Response
     */
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
    /**
     * Other Bank Transfer Select
     *
     * @param App\Models\Beneficiary $beneficiary
     * @param Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function otherBankTransferSelect(Request $request, Beneficiary $beneficiary){
        $data['beneficiary'] = $beneficiary->info;
        $temp_identifier = generate_unique_string('temporary_datas','identifier',60);
        try{
            $temp_data = TemporaryData::create([
                'type'          => GlobalConst::FUND_TRANSFER,
                'identifier'    => $temp_identifier,
                'data'          => $data,
            ]);
        }catch(Exception $e) {
            return Response::error([__("Something went wrong!. Please try again.")],[],400);
        }
        return $temp_data;
    }


    /**
     *  Other Bank Transfer Submit
     *
     * @param  $token
     * @return \Illuminate\Http\Response
     */
    public function otherBankTransferSubmit($validated,$fees_and_charge,$temp_data){

        $user_wallet = UserWallet::where('user_id', Auth::id())->first();
        $charge_calculation = $this->transferCharges($validated['amount'], $fees_and_charge, $user_wallet);
        if($charge_calculation['payable'] > $user_wallet->balance) return [
            'status'    => false,
            'message'   => 'Insufficient Balance'
        ];  
        $limit_response = $this->transactionLimitCheck($charge_calculation, $fees_and_charge); // Daily And Monthly Limit Check
        if($limit_response instanceof RedirectResponse) return $limit_response;

        $update_data = (array) $temp_data->data;
        $update_data['charges'] = $charge_calculation;
        $update_data['remark'] = $validated['remarks'];

        try{
            $temp_data->update(['data' => $update_data]);
        }catch(Exception $e) {
            return Response::error([__("This section is under construction.")],[],400);
        }

        return $temp_data;
    }

    /**
     *  Own Bank Transfer Submit
     *
     * @param App\Models\TemporaryData $temp_data
     * @return \Illuminate\Http\Response
     */
    public function otherBankTransferConfirm(TemporaryData $temp_data){
        // PIN Check
        $request = request();
        if($request->has('pin_code')) {
             if(Auth::user()->pin_code !== $request->pin_code) {
                  return Response::error([__('Invalid Transaction PIN')], [], 400);
             }
        }

        $sender_wallet = UserWallet::active()->where('user_id', Auth::id())->first();
        if(!$sender_wallet) return Response::error([__("Your wallet not found")],[],400);

        $charges = $temp_data->data->charges;
        if($charges->payable > $sender_wallet->balance) return Response::error([__("Your wallet balance is insufficient")],[],400);

        $trx_id = generateTrxString('transactions','trx_id','FT-',14);
        // Make Transaction
        DB::beginTransaction();
        try{
            $transaction =  Transaction::create([
                'type'                          => PaymentGatewayConst::TYPE_OTHER_BANK_TRANSFER,
                'trx_id'                        => $trx_id,
                'user_type'                     => GlobalConst::USER,
                'user_id'                       => $sender_wallet->user->id,
                'wallet_id'                     => $sender_wallet->id,
                'request_amount'                => $charges->request_amount,
                'request_currency'              => $charges->sender_currency,
                'exchange_rate'                 => $sender_wallet->currency->rate,
                'percent_charge'                => $charges->percent_charge,
                'fixed_charge'                  => $charges->fixed_charge,
                'total_charge'                  => $charges->total_charge,
                'total_payable'                 => $charges->payable,
                'available_balance'             => $sender_wallet->balance - $charges->payable,
                'receive_amount'                => $charges->request_amount,
                'payment_currency'              => $sender_wallet->currency->code,
                'remark'                        => $temp_data->data->remark ?? NULL,
                'details'                       => ['beneficiary' => $temp_data->data->beneficiary,'charges' => $charges],
                'status'                        => PaymentGatewayConst::STATUSPENDING,
                'created_at'                    => now(),
            ]);

            DB::table('user_wallets')->where("id",$sender_wallet->id)->update([
                'balance'       => ($sender_wallet->balance - $charges->payable),
            ]);
            $this->createTransactionDeviceRecord($transaction->id);

            $temp_data->delete();
            DB::commit();
        }catch(Exception $e) {
            DB::rollBack();
            return Response::error([__('Something went wrong! Please try again')],[],400);
        }

        try {
            user_notification_data_save($sender_wallet->user->id,$type = PaymentGatewayConst::TYPE_OTHER_BANK_TRANSFER,$title = "Fund Transfer",$transaction->id,$charges->request_amount,$gateway = null,$currency = $charges->sender_currency,$message = "Fund Transfer Successful.");

            $basic_settings = BasicSettingsProvider::get();
            if($basic_settings->email_notification){
                try{
                    $sender_wallet->user->notify(new OtherBankSenderNotification($sender_wallet->user, $transaction));
                }catch(Exception $e){}
                
            }
        } catch (Exception $e) {
            return Response::error([__('Something went wrong! Please try again')],[],400);
        }

        return Response::success([__("Fund transfer successfully done!")],[],200);
    }
}
