<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Models\Transaction;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Constants\PaymentGatewayConst;
use App\Http\Helpers\Response;

class StatementController extends Controller
{
    /**
     * Method for get the statement data
     */
    public function index(Request $request){
        $transactions = [];

        $trx_id    = $request->trx_id;
        $from_date = $request->from_date;
        $to_date   = $request->to_date;
        $type      = $request->type;
        $status    = $request->status;

        $query     = Transaction::auth();
        if(isset($trx_id) && !empty($trx_id)){
            $query->where("trx_id","like","%".$trx_id."%");
        }

        if(isset($from_date) && isset($to_date) && !empty($from_date) && !empty($to_date)){
            $query->whereDate("created_at", '>=', $from_date);
            $query->whereDate("created_at", '<=', $to_date);
        }
        if(isset($type) && !empty($type) && $type != '*'){
            if($type == 'FUND-TRANSFER'){
                $query->whereIn("type",[PaymentGatewayConst::TYPE_OTHER_BANK_TRANSFER, PaymentGatewayConst::TYPE_OWN_BANK_TRANSFER]);
            }else{
                $query->where("type",$type);
            }
        }
        if(isset($status) && !empty($status) && $status != '*'){
            $query->where("status",$status);
        }

        $transactions = $query->get()->map(function($data){
            return [
                'trx_id'            => $data->trx_id,
                'transaction_type'  => $data->type,
                'request_currency'  => $data->request_currency,
                'request_amount'    => floatval($data->request_amount),
                'total_charge'      => floatval($data->total_charge),
                'payable_amount'    => floatval($data->total_charge),
                'type'              => $data->userTrxType,
                'status'            => $data->stringStatus->value,
                'created_at'        => $data->created_at
            ];
        });

        return Response::success([__("Statement data fetch successfully.")],[
            'transactions'  => $transactions
        ],200);
    }
}
