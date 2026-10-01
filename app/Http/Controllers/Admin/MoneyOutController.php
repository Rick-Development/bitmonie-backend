<?php

namespace App\Http\Controllers\Admin;

use Exception;
use App\Models\Transaction;
use Illuminate\Http\Request;
use App\Http\Helpers\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Constants\PaymentGatewayConst;
use Illuminate\Support\Facades\Validator;

class MoneyOutController extends Controller
{
   /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $page_title = "All Logs";
        $logs = Transaction::moneyOut()->orderByDesc('id')->paginate(10);
        return view('admin.sections.money-out.index',compact(
            'page_title',
            'logs',
        ));
    }

    /**
     * Display All Pending Logs
     * @return view
     */
    public function pending() {
        $page_title = "Pending Logs";
        $logs = Transaction::moneyOut()->pending()->orderByDesc('id')->paginate(10);
        return view('admin.sections.money-out.index',compact(
            'page_title',
            'logs'
        ));
    }


    /**
     * Display All Complete Logs
     * @return view
     */
    public function complete() {
        $page_title = "Completed Logs";
        $logs = Transaction::moneyOut()->complete()->orderByDesc('id')->paginate(10);
        return view('admin.sections.money-out.index',compact(
            'page_title',
            'logs'
        ));
    }


    /**
     * Display All Canceled Logs
     * @return view
     */
    public function canceled() {
        $page_title = "Canceled Logs";
        $logs = Transaction::moneyOut()->reject()->orderByDesc('id')->paginate(10);
        return view('admin.sections.money-out.index',compact(
            'page_title',
            'logs'
        ));
    }

    public function details(Transaction $transaction) {
        $page_title = "Transaction Details";
        return view('admin.sections.money-out.details',compact("page_title","transaction"));
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
                'status'        => PaymentGatewayConst::STATUSSUCCESS,
                'reject_reason' => null,
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
        if($transaction->status != PaymentGatewayConst::STATUSPENDING) return back()->with(['error' => ['Action Denied!']]);

        DB::beginTransaction();
        try{

            DB::table($transaction->getTable())->where("id",$transaction->id)->update([
                'reject_reason'     => $validated['reason'],
                'status'            => PaymentGatewayConst::STATUSREJECTED,
                'available_balance' => ($transaction->total_payable + $transaction->available_balance),
            ]);

            DB::table($transaction->creator_wallet->getTable())->where("id",$transaction->creator_wallet->id)->increment('balance',$transaction->total_payable);

            DB::commit();
        }catch(Exception $e) {
            DB::rollBack();
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
        $logs = Transaction::moneyOut()->search($validated['text'])->limit(10)->get();
        return view('admin.components.search.money-out-search',compact(
            'logs',
        ));
    }
}
