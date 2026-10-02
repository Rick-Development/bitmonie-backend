<?php

namespace App\Http\Controllers\User;

use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Transaction;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Constants\PaymentGatewayConst;
use App\Models\UserWallet;
use App\Providers\Admin\BasicSettingsProvider;
use App\Services\TransactionHistoryService;
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

    public function filterStatement(Request $request, TransactionHistoryService $historyService){

        $page_title = "Bank Statement";
        $transactions = $historyService->forUser($request->user(), $request->query());
        $summary = $historyService->summary($transactions);

        if(isset($request->submit_type) && $request->submit_type == 'EXPORT') {
            return $this->download($transactions, $summary, $request->query());
        }

        return view('user.sections.statement', compact('page_title', 'transactions', 'summary'));
    }

    /**
     * Method for download statement in pdf format
     * @param string
     *
     */
    public function download($transactions, array $summary = [], array $filters = []){

        $pdf = Pdf::loadView('user.sections.pdf.statement-ledger', [
            'user' => Auth::user(),
            'transactions' => $transactions,
            'summary' => $summary,
            'filters' => $filters,
        ])->setOption(['dpi' => 150, 'defaultFont' => 'sans-serif']);

        $basic_settings = BasicSettingsProvider::get();
        $pdf_download_name =  $basic_settings->site_name.'-'.'statement.pdf';
        return $pdf->download($pdf_download_name.".pdf");

    }
}
