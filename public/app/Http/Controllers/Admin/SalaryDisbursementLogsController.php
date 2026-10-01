<?php

namespace App\Http\Controllers\Admin;

use App\Models\Transaction;
use Illuminate\Http\Request;
use App\Http\Helpers\Response;
use App\Http\Controllers\Controller;
use App\Constants\PaymentGatewayConst;
use Illuminate\Support\Facades\Validator;

class SalaryDisbursementLogsController extends Controller
{
    /**
     * Method for salary disbursement logs 
     * @return view
     */
    public function index(){
        $page_title     = "Salary Disbursement Logs";
        $transactions   = Transaction::with(['user'])->where('type',PaymentGatewayConst::SALARYDISBURSEMENT)
                            ->where('attribute',PaymentGatewayConst::SEND)
                            ->orderBy('id','desc')
                            ->get();

        return view('admin.sections.salary-disbursement-logs.index',compact(
            'page_title',
            'transactions'
        ));
    }
    /**
     * Method for view salary disbursement details page.
     * @param $salary_disbursement_id
     * @return view
     */
    public function details($salary_disbursement_id){
        $page_title             = "Salary Disbursement Logs Details";
        $receiver_transaction   = Transaction::with(['user'])
                            ->where('salary_disbursement_id',$salary_disbursement_id)
                            ->whereNot('attribute',PaymentGatewayConst::SEND)
                            ->get();
        $sender_transaction     = Transaction::with(['user','user_wallet'])
                            ->where('salary_disbursement_id',$salary_disbursement_id)
                            ->whereNot('attribute',PaymentGatewayConst::RECEIVED)
                            ->first();
        $total_amount           = $receiver_transaction->sum('request_amount') ?? 0;
        
        return view('admin.sections.salary-disbursement-logs.details',compact(
            'page_title',
            'total_amount',
            'sender_transaction',
            'receiver_transaction',
        ));
    }
    public function search(Request $request) {
        $validator = Validator::make($request->all(),[
            'text'  => 'required|string',
        ]);

        if($validator->fails()) {
            $error = ['error' => $validator->errors()];
            return Response::error($error,null,400);
        }

        $validated = $validator->validate();
        $transactions = Transaction::with(['user'])->where('type',PaymentGatewayConst::SALARYDISBURSEMENT)
        ->where('attribute',PaymentGatewayConst::SEND)->search($validated['text'])->limit(10)->get();
        
        return view('admin.components.data-table.salary-disbursement-table',compact(
            'transactions',
        ));
    }

}
