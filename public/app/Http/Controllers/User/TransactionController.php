<?php

namespace App\Http\Controllers\User;

use Exception;
use App\Models\Transaction;
use Illuminate\Http\Request;
use App\Http\Helpers\Response;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Http\Controllers\Controller;
use App\Constants\PaymentGatewayConst;
use Illuminate\Support\Facades\Validator;
use App\Providers\Admin\BasicSettingsProvider;

class TransactionController extends Controller
{

    public function slugValue($slug){

        $values =  [
            'add-money'                 => PaymentGatewayConst::TYPEADDMONEY,
            'money-out'                 => PaymentGatewayConst::TYPEMONEYOUT,
            'virtual-card'              => PaymentGatewayConst::TYPEVIRTUALCARD,
            'salary-disbursement'       => PaymentGatewayConst::SALARYDISBURSEMENT,
        ];

        if(!array_key_exists($slug,$values)) return abort(404);
        return $values[$slug];
    }

    /**
     * This method for show the transaction index page
     * @method GET
     * @return Illuminate\Http\Request
     */

    public function index($slug = null){
        if($slug != null){
            $transactions = Transaction::auth()->where("type",$this->slugValue($slug))->orderByDesc("id")->paginate(12);
            $page_title = ucwords(remove_special_char($slug," ")) . " Log";
        }else {
            $transactions = Transaction::auth()->orderByDesc("id")->paginate(12);
            $page_title = "Transaction Log";
        }
        return view('user.sections.transactions.index', compact('transactions', 'page_title'));
    }

    /**
     * This method for show the transaction index page
     * @method POST
     * @param Illuminate\Http\Request $request
     * @return Illuminate\Http\Request
     */

    public function search(Request $request) {
        $validator = Validator::make($request->all(),[
            'text'  => 'required|string',
        ]);

        if($validator->fails()) {
            return Response::error($validator->errors(),null,400);
        }

        $validated = $validator->validate();

        try{
            $transactions = Transaction::auth()->search($validated['text'])->take(10)->get();
        }catch(Exception $e){
            $error = ['error' => ['Something went wrong!. Please try again.']];
            return Response::error($error,null,500);
        }

        return view('user.components.search.transaction-log',compact('transactions'));
    }
    /**
     * Method for download salary disbursement data
     * @param $sd_id
     */
    public function download($sd_id){
        $transactions   = Transaction::with(['user','user_wallet'])->where('salary_disbursement_id',$sd_id)->whereNot('attribute',PaymentGatewayConst::SEND)->get();
        $total_amount = $transactions->sum('request_amount') ?? 0;
        
        $pdf = Pdf::loadView('user.sections.pdf.salary-disbursement', compact(
            'transactions',
            'total_amount',
            
        ))->setOption(['dpi' => 150, 'defaultFont' => 'sans-serif']);

        $basic_settings = BasicSettingsProvider::get();
        $pdf_download_name =  $basic_settings->site_name.'-'.'salary-disbursement.pdf';
        return $pdf->download($pdf_download_name.".pdf");
    }
}
