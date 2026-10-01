<?php

namespace App\Http\Controllers\Admin;

use Exception;
use App\Models\Transaction;
use Illuminate\Http\Request;
use App\Http\Helpers\Response;
use App\Http\Controllers\Controller;
use App\Constants\PaymentGatewayConst;
use Illuminate\Support\Facades\Validator;

class AddMoneyController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $page_title = "All Logs";
        $logs = Transaction::addMoney()->orderByDesc("id")->paginate(10);
        return view('admin.sections.add-money.index', compact(
            'page_title',
            'logs'
        ));
    }


    /**
     * Pending Add Money Logs View.
     * @return view $pending-add-money-logs
     */
    public function pending()
    {
        $page_title = "Pending Logs";
        $logs = Transaction::addMoney()->pending()->orderByDesc("id")->paginate(10);
        return view('admin.sections.add-money.index', compact(
            'page_title',
            'logs'
        ));
    }


    /**
     * Complete Add Money Logs View.
     * @return view $complete-add-money-logs
     */
    public function complete()
    {
        $page_title = "Completed Logs";
        $logs = Transaction::addMoney()->complete()->orderByDesc("id")->paginate(10);
        return view('admin.sections.add-money.index', compact(
            'page_title',
            'logs'
        ));
    }

    /**
     * Canceled Add Money Logs View.
     * @return view $canceled-add-money-logs
     */
    public function canceled()
    {
        $page_title = "Canceled Logs";
        $logs = Transaction::addMoney()->reject()->orderByDesc("id")->paginate(10);
        return view('admin.sections.add-money.index', compact(
            'page_title',
            'logs'
        ));
    }

    public function details(Transaction $transaction) {
        $page_title = "Transaction Details";
        return view('admin.sections.add-money.details',compact("page_title","transaction"));
    }

    public function approve(Request $request) {
        $validated = Validator::make($request->all(),[
            'target'    => 'required|string|exists:transactions,trx_id',
        ])->validate();

        $transaction = Transaction::where("trx_id",$validated['target'])->first();
        if(!$transaction) return back()->with(['error' => ['Transaction not found!']]);
        if($transaction->status == PaymentGatewayConst::STATUSSUCCESS) return back()->with(['warning' => ['This transaction is already approved']]);
        if($transaction->status != PaymentGatewayConst::STATUSPENDING) return back()->with(['warning' => ['Action Denied!']]);

        try{

            $transaction->update([
                'status'            => PaymentGatewayConst::STATUSSUCCESS,
                'available_balance' => ($transaction->creator_wallet->balance + $transaction->receive_amount),
            ]);

            // Update Creator Wallet Balance
            $transaction->creator_wallet->update([
                'balance'       => ($transaction->creator_wallet->balance + $transaction->receive_amount),
            ]);

        }catch(Exception $e) {
            return back()->with(['error' => ['Something went wrong. Please try again']]);
        }

        return back()->with(['success' => ['Transaction successfully approved!']]);
    }

    public function reject(Request $request) {
        $validator = Validator::make($request->all(),[
            'target'        => "required|string|exists:transactions,trx_id",
            'reason'        => "required|string|max:1000",
        ]);

        if($validator->fails()) {
            return back()->withErrors($validator)->withInput()->with('modal',"reject-modal");
        }
        $validated = $validator->validate();

        $transaction = Transaction::where("trx_id",$validated['target'])->first();
        if(!$transaction) return back()->with(['error' => ['Transaction not found!']]);
        if($transaction->status == PaymentGatewayConst::STATUSREJECTED) return back()->with(['warning' => ['This transaction is already rejected']]);
        if($transaction->status != PaymentGatewayConst::STATUSPENDING) return back()->with(['warning' => ['Action Denied!']]);

        try{

            $transaction->update([
                'reject_reason'     => $validated['reason'],
                'status'            => PaymentGatewayConst::STATUSREJECTED,
            ]);

        }catch(Exception $e) {
            return back()->with(['error' => ['Something went wrong! Please try again']]);
        }

        return back()->with(['success' => ['Transaction rejected successfully!']]);
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
        $logs = Transaction::addMoney()->search($validated['text'])->limit(10)->get();
        return view('admin.components.search.add-money-search',compact(
            'logs',
        ));
    }
}
