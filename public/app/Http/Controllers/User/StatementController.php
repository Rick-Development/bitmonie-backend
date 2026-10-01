<?php

namespace App\Http\Controllers\User;

use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Transaction;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Constants\PaymentGatewayConst;
use App\Models\UserWallet;
use App\Providers\Admin\BasicSettingsProvider;
use Illuminate\Support\Facades\Auth;

class StatementController extends Controller
{
    /**
     * Statement Page View And Get Statement
     *
     * @method GET
     * @return Illuminate\Http\Response Response
     */
    public function index(){
        $page_title = "Bank Statement";

        return view('user.sections.statement', compact('page_title'));
    }

     /**
     * Statement Page View And Get Statement
     *
     * @method GET
     * @param Illuminate\Http\Request $request;
     * @return Illuminate\Http\Response Response
     */

    public function filterStatement(Request $request){

        $page_title = "Bank Statement";

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

        $transactions = $query->orderBy('id','desc')->get();
        if(isset($request->submit_type) && $request->submit_type == 'EXPORT') {
            return $this->download($transactions);
        }

        return view('user.sections.statement', compact('page_title', 'transactions'));
    }

    /**
     * Method for download statement in pdf format
     * @param string
     *
     */
    public function download($transactions){

        
        $total_transaction  = $transactions->count();
        $total_add_money    = $transactions->where('type', PaymentGatewayConst::TYPEADDMONEY)->sum('request_amount');
        $total_money_out    = $transactions->where('type', PaymentGatewayConst::TYPEMONEYOUT)->sum('request_amount');
        $fund_transfer      = $transactions->where('user_id', Auth::id())->whereIn('type', [PaymentGatewayConst::TYPE_OWN_BANK_TRANSFER, PaymentGatewayConst::TYPE_OTHER_BANK_TRANSFER, PaymentGatewayConst::TYPE_MOBILE_WALLET_TRANSFER])->sum('request_amount');
        $fund_received      = $transactions->where('receiver_id', Auth::id())->whereIn('type', [PaymentGatewayConst::TYPE_OWN_BANK_TRANSFER, PaymentGatewayConst::TYPE_OTHER_BANK_TRANSFER, PaymentGatewayConst::TYPE_MOBILE_WALLET_TRANSFER])->sum('request_amount');
        $user_wallet        = UserWallet::auth()->first();
        $date               = [
            'from_date'     => $_GET['from_date'],
            'to_date'       => $_GET['to_date'],
            'type'          => $_GET['type'],
            'status'        => $_GET['status']
        ];

        $pdf = Pdf::loadView('user.sections.pdf.statement', compact(
            'transactions',
            'total_transaction',
            'total_add_money',
            'total_money_out',
            'fund_transfer',
            'fund_received',
            'user_wallet',
            'date'
        ))->setOption(['dpi' => 150, 'defaultFont' => 'sans-serif']);

        $basic_settings = BasicSettingsProvider::get();
        $pdf_download_name =  $basic_settings->site_name.'-'.'statement.pdf';
        return $pdf->download($pdf_download_name.".pdf");

    }
}
