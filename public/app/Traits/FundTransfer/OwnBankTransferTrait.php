<?php
namespace App\Traits\FundTransfer;

use Exception;
use App\Models\User;
use App\Models\UserWallet;
use App\Models\Beneficiary;
use App\Models\Transaction;
use Illuminate\Http\Request;
use App\Models\TemporaryData;
use App\Constants\GlobalConst;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\RedirectResponse;
use App\Constants\PaymentGatewayConst;
use App\Providers\Admin\BasicSettingsProvider;
use App\Notifications\User\FundTransfer\OwnBankSenderNotification;
use App\Notifications\User\FundTransfer\OwnBankReceiverNotification;

trait OwnBankTransferTrait{
    /**
     * Own Bank Transfer Select
     *
     * @param App\Models\Beneficiary $beneficiary
     * @param Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function ownBankTransferSelect(Request $request, Beneficiary $beneficiary){
        
        $data['beneficiary'] = $beneficiary->info;

        $temp_identifier = generate_unique_string('temporary_datas','identifier',60);

        try{
            TemporaryData::create([
                'type'          => GlobalConst::FUND_TRANSFER,
                'identifier'    => $temp_identifier,
                'data'          => $data,
            ]);
        }catch(Exception $e) {
            return back()->with(['error' => ['Something went wrong!. Please try again.']]);
        }

        return redirect()->route('user.fund-transfer.create', $temp_identifier);
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
        if(!$sender_wallet) return back()->with(['error' => ['Your wallet not found']]);

        if($charge_calculation['payable'] > $sender_wallet->balance) return back()->with(['error' => ['Your wallet balance is insufficient']]);

        $limit_response = transactionDailyAndMonthlyLimitCheck($charge_calculation, $fees_and_charge,$type = PaymentGatewayConst::TYPE_OWN_BANK_TRANSFER); // Daily And Monthly Limit Check
        if($limit_response instanceof RedirectResponse) return $limit_response;

        $update_data = (array) $temp_data->data;
        $update_data['charges'] = $charge_calculation;
        $update_data['remark'] = $validated['remarks'];

        try{
            $temp_data->update(['data' => $update_data]);
        }catch(Exception $e) {
            return back()->with(['error' => ['Something went wrong!. Please try again.']]);
        }

        return redirect()->route('user.fund-transfer.preview', $validated['temp_token']);
    }

    /**
     *  Own Bank Transfer Submit
     *
     * @param App\Models\TemporaryData $temp_data
     * @return \Illuminate\Http\Response
     */
    public function ownBankTransferPreviewSubmit(TemporaryData $temp_data){


        $sender_wallet = UserWallet::active()->where('user_id', Auth::id())->first();
        if(!$sender_wallet) return back()->with(['error' => ['Your wallet not found']]);
        
        $receiver        = User::active()->where('email',$temp_data->data->beneficiary->email)->first();
        if(!$receiver) return back()->with(['error' => ['Receiver not found']]);
        $receiver_wallet = UserWallet::active()->where('user_id', $receiver->id)->first();
        if(!$receiver_wallet) return back()->with(['error' => ['Receiver wallet not found']]);

        $charges = $temp_data->data->charges;
        if($charges->payable > $sender_wallet->balance) return back()->with(['error' => ['Your wallet balance is insufficient']]);

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
                'attribute'                     => GlobalConst::SEND,
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
            return redirect()->route('user.fund-transfer.index')->with(['error' => ['Something went wrong! Please try again']]);
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
        } catch (Exception $e) {}

        return redirect()->route('user.fund-transfer.transaction.success',$transaction->trx_id)->with(['success' => ['Fund transfer successfully done!']]);
    }
}
