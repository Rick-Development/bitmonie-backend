<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Models\UserWallet;
use App\Models\Transaction;
use App\Constants\GlobalConst;
use App\Http\Helpers\Response;
use App\Models\UserNotification;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Constants\PaymentGatewayConst;

class DashboardController extends Controller
{
    public function dashboard() {
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
        
        $profile_image_paths = [
            'base_url'          => url("/"),
            'path_location'     => files_asset_path_basename("user-profile"),
            'default_image'     => files_asset_path_basename("profile-default"),
        ];

        $transaction_count = Transaction::auth()->count();
        $complete_transaction_count = Transaction::auth()->complete()->count();
        $pending_transaction_count = Transaction::auth()->pending()->count();

        $total_add_money = Transaction::auth()->where('type', PaymentGatewayConst::TYPEADDMONEY)->sum('request_amount');
        $total_money_out = Transaction::auth()->where('type', PaymentGatewayConst::TYPEMONEYOUT)->sum('request_amount');
        $fund_transfer = Transaction::auth()->whereIn('type', [PaymentGatewayConst::TYPE_OWN_BANK_TRANSFER, PaymentGatewayConst::TYPE_OTHER_BANK_TRANSFER, PaymentGatewayConst::TYPE_MOBILE_WALLET_TRANSFER])->sum('request_amount');
       
        $fund_received = Transaction::where('receiver_id', Auth::id())->whereIn('type', [PaymentGatewayConst::TYPE_OWN_BANK_TRANSFER, PaymentGatewayConst::TYPE_OTHER_BANK_TRANSFER, PaymentGatewayConst::TYPE_MOBILE_WALLET_TRANSFER])->sum('request_amount');

        $transactions = Transaction::auth()->orderByDesc("id")->latest()->get()->map(function($data){
            if($data->type == PaymentGatewayConst::TYPEADDMONEY){
                if(@$data->gateway_currency->gateway->isManual()){
                    $type   = "(Manual)";
                }else{
                    $type   = "";
                }
                return [
                    'type'              => $data->type,
                    'trx_id'            => $data->trx_id,
                    'gateway'           => $data->gateway_currency->name . $type,
                    'gateway_currency'  => $data->gateway_currency->currency_code,
                    'request_amount'    => floatval($data->request_amount),    
                    'request_currency'  => $data->request_currency,    
                    'exchange_rate'     => floatval($data->exchange_rate),    
                    'total_charge'      => floatval($data->total_charge),    
                    'total_payable'     => floatval($data->total_payable),    
                    'receive_amount'    => floatval($data->receive_amount),  
                    'status'            => $data->stringStatus->value,
                    'attribute'         => $data->attribute,
                    'created_at'        => $data->created_at,
                        
                ];
            }else if($data->type == PaymentGatewayConst::TYPEMONEYOUT){
                if(@$data->gateway_currency->gateway->isManual()){
                    $type   = "(Manual)";
                }else{
                    $type   = "";
                }
                return [
                    'type'              => $data->type,
                    'trx_id'            => $data->trx_id,
                    'gateway'           => $data->gateway_currency->name . $type,
                    'gateway_currency'  => $data->gateway_currency->currency_code,
                    'request_amount'    => floatval($data->request_amount),    
                    'request_currency'  => $data->request_currency,    
                    'exchange_rate'     => floatval($data->exchange_rate),    
                    'total_charge'      => floatval($data->total_charge),    
                    'total_payable'     => floatval($data->total_payable),    
                    'receive_amount'    => floatval($data->receive_amount),  
                    'status'            => $data->stringStatus->value,
                    'attribute'         => $data->attribute,
                    'created_at'        => $data->created_at,
                        
                ];
            }else if($data->type == PaymentGatewayConst::TYPE_OWN_BANK_TRANSFER){
                return [
                    'type'                      => $data->type,
                    'trx_id'                    => $data->trx_id,
                    'request_amount'            => floatval($data->request_amount),    
                    'request_currency'          => $data->request_currency,    
                    'exchange_rate'             => floatval($data->exchange_rate),    
                    'total_charge'              => floatval($data->total_charge),    
                    'total_payable'             => floatval($data->total_payable),    
                    'receive_amount'            => floatval($data->receive_amount), 
                    'account_number'            => $data->details->beneficiary->account_number,
                    'account_holder_name'       => $data->details->beneficiary->account_holder_name,
                    'email'                     => $data->details->beneficiary->email,
                    'status'                    => $data->stringStatus->value,
                    'attribute'                 => $data->attribute,
                    'created_at'                => $data->created_at,
                        
                ];
            }else if($data->type == PaymentGatewayConst::TYPE_OTHER_BANK_TRANSFER){
                return [
                    'type'                      => $data->type,
                    'trx_id'                    => $data->trx_id,
                    'request_amount'            => floatval($data->request_amount),    
                    'request_currency'          => $data->request_currency,    
                    'exchange_rate'             => floatval($data->exchange_rate),    
                    'total_charge'              => floatval($data->total_charge),    
                    'total_payable'             => floatval($data->total_payable),    
                    'receive_amount'            => floatval($data->receive_amount), 
                    'account_number'            => $data->details->beneficiary->account_number,
                    'account_holder_name'       => $data->details->beneficiary->account_holder_name,
                    'bank_name'                 => $data->details->beneficiary->bank->name,
                    'branch_name'               => $data->details->beneficiary->branch->name,
                    'status'                    => $data->stringStatus->value,
                    'attribute'                 => $data->attribute,
                    'created_at'                => $data->created_at,
                        
                ];
            }else if($data->type == PaymentGatewayConst::TYPEVIRTUALCARD){
                return [
                    'type'                      => $data->type,
                    'trx_id'                    => $data->trx_id,
                    'request_amount'            => floatval($data->request_amount),    
                    'request_currency'          => $data->request_currency,  
                    'total_charge'              => floatval($data->total_charge),
                    'status'                    => $data->stringStatus->value,
                    'remark'                    => $data->remark,
                    'attribute'                 => $data->attribute,
                    'created_at'                => $data->created_at,

                ];
            }else if($data->type == PaymentGatewayConst::SALARYDISBURSEMENT){
                if($data->attribute == GlobalConst::SEND){
                    $download_url   = route('user.transactions.download',['sd_id' => $data->salary_disbursement_id]);
                    $from           = $data->admin->fullname;
                }else{
                    $download_url   = "";
                    $from           = $data->details->fullname;
                }
                return [
                    'type'                      => $data->type,
                    'trx_id'                    => $data->trx_id,
                    'download_url'              => $download_url,
                    'from'                      => $from,
                    'request_amount'            => floatval($data->request_amount),    
                    'request_currency'          => $data->request_currency,  
                    'total_charge'              => floatval($data->total_charge),
                    'status'                    => $data->stringStatus->value,
                    'remark'                    => $data->remark,
                    'attribute'                 => $data->attribute,
                    'created_at'                => $data->created_at,

                ];
            }else if($data->type == PaymentGatewayConst::TYPEADDSUBTRACTBALANCE){
                return [
                    'title'                     => "Balance Update",
                    'type'                      => $data->type,
                    'trx_id'                    => $data->trx_id,
                    'from'                      => $data->admin->fullname,
                    'request_amount'            => floatval($data->request_amount),    
                    'request_currency'          => $data->request_currency,  
                    'status'                    => $data->stringStatus->value,
                    'transaction_type'          => $data->details,
                    'attribute'                 => $data->attribute,
                    'created_at'                => $data->created_at,
                ];
            }
            
        });


        return Response::success([__('User dashboard data fetch successfully!')],[
            'user_wallet'               => $user_wallet,
            'pin_status'                => auth()->user()->pin_status,
            'total_transactions'        => $transaction_count,
            'complete_transactions'     => $complete_transaction_count,
            'pending_transactions'      => $pending_transaction_count,
            'total_add_money'           => floatval($total_add_money),
            'total_money_out'           => floatval($total_money_out),
            'total_fund_transfer'       => floatval($fund_transfer),
            'total_fund_received'       => floatval($fund_received),
            'profile_image_paths'       => $profile_image_paths,
            'currency_image_paths'      => $currency_image_paths,
            'transactions'              => $transactions
        ]);
    }

    public function notifications() {
        $user           = auth()->user()->id;
        $notification   = UserNotification::auth()->orderBy("id","desc")->get()->map(function($data){
            return [
                'id'            => $data->id,
                'type'          => $data->type,
                'message'       => [
                    'title'     => $data->message->title,
                    'gateway'   => $data->message->gateway,
                    'currency'  => $data->message->currency,
                    'amount'    => floatval($data->message->amount),
                    'message'   => $data->message->message
                ],
                'created_at'    => $data->created_at
            ]; 
        });
        return Response::success([__('Notification Data Fetch Successfuly.')],[
            'notification'      => $notification,
        ],200);
    }

    public function updateDeviceToken(\Illuminate\Http\Request $request) {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'token' => 'required|string',
        ]);

        if($validator->fails()) {
            return Response::error($validator->errors()->all());
        }

        $user = auth()->user();
        try {
            $user->update([
                'fcm_token' => $request->token,
            ]);
            return Response::success([__('Device token updated successfully.')]);
        } catch (\Exception $e) {
            return Response::error([__('Something went wrong! Please try again.')]);
        }
    }
}
