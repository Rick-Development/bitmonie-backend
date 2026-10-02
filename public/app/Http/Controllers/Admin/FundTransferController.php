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
use App\Notifications\Admin\RejectNotification;
use App\Notifications\Admin\ApprovedNotification;

class FundTransferController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $page_title = "All Logs";
        $logs = Transaction::fundTransfer()->orderByDesc("id")->paginate(10);
        return view('admin.sections.fund-transfer-log.index', compact(
            'page_title',
            'logs'
        ));
    }

        /**
     * Display All Pending Logs
     * @return view
     */
    public function pending() {
        $page_title = "Pending Logs";
        $logs = Transaction::fundTransfer()->pending()->orderByDesc('id')->paginate(10);
        return view('admin.sections.fund-transfer-log.index',compact(
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
        $logs = Transaction::fundTransfer()->complete()->orderByDesc('id')->paginate(10);
        return view('admin.sections.fund-transfer-log.index',compact(
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
        $logs = Transaction::fundTransfer()->reject()->orderByDesc('id')->paginate(10);
        return view('admin.sections.fund-transfer-log.index',compact(
            'page_title',
            'logs'
        ));
    }

    /**
     * Own bank Transfer Details
     *
     * @method GET
     * @return \Illuminate\Http\Response
     */

    public function ownDetails($trx_id) {
        $transaction = Transaction::where('trx_id',$trx_id)->first();
        $page_title = "Details";
        return view('admin.sections.fund-transfer-log.details',compact("page_title","transaction"));
    }

    /**
     * Other bank Transfer Details
     *
     * @method GET
     * @return \Illuminate\Http\Response
    */

     public function otherDetails($trx_id) {
        $transaction = Transaction::where('trx_id',$trx_id)->first();
        $page_title = "Details";
        return view('admin.sections.fund-transfer-log.other-details',compact("page_title","transaction"));
    }

     /**
     * Other bank Transfer Details
     *
     * @method POST
     * @return \Illuminate\Http\Response
     */
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

        try {
            $transaction->creator->notify(new ApprovedNotification((object) $validated));
        } catch (\Exception $th) {

        }

        return back()->with(['success' => ['Transaction successfully approved!']]);
    }

    /**
     * Other bank Transfer Details
     *
     * @method POST
     * @return \Illuminate\Http\Response
     */
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

        try {
            $transaction->creator->notify(new RejectNotification((object) $validated));
        } catch (\Exception $th) {

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
        $logs = Transaction::fundTransfer()->search($validated['text'])->limit(10)->get();
        return view('admin.components.search.fund-transfer-search',compact(
            'logs',
        ));
    }
}
