<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Constants\GlobalConst;
use Exception;
use App\Models\Transaction;
use App\Http\Helpers\Response;
use App\Http\Controllers\Controller;
use App\Constants\PaymentGatewayConst;

class TransactionController extends Controller
{
    public function log() {
        $transactions = Transaction::auth()->orderByDesc("id")->get()->map(function($data){
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
                    'download_url'              => route('user.fund-transfer.pdf.download', $data->trx_id),
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
                    'download_url'              => route('user.fund-transfer.pdf.download', $data->trx_id),
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

        return Response::success([__('Transactions fetch successfully!')],[
            'transactions'  => $transactions,
        ],200);
    }
}
