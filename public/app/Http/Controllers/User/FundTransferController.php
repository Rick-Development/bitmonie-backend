<?php

namespace App\Http\Controllers\User;

use Exception;
use App\Models\Beneficiary;
use App\Models\Transaction;
use Illuminate\Support\Str;
use Jenssegers\Agent\Agent;
use Illuminate\Http\Request;
use App\Models\TemporaryData;
use App\Constants\GlobalConst;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\Admin\TransactionSetting;
use Illuminate\Support\Facades\Validator;
use App\Traits\FundTransfer\OwnBankTransferTrait;
use App\Traits\FundTransfer\OtherBankTransferTrait;

class FundTransferController extends Controller
{
    use OwnBankTransferTrait, OtherBankTransferTrait;

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $page_title = "Select Beneficiary";
        $beneficiaries = Beneficiary::auth()->orderByDesc("id")->paginate(10);
        $type = GlobalConst::FUND_TRANSFER;
        return view('user.sections.fund-transfer.index',compact("page_title","beneficiaries",'type'));
    }

    /**
     * Beneficiary Select
     *
     * @method POST
     * @param Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function beneficiarySelect(Request $request){
        $validator = Validator::make($request->all(), [
            'target' => ['required']
        ]);

        $validated = $validator->validate();
       
        $beneficiary = Beneficiary::find($validated['target']);
        if(!$beneficiary){
            return back()->with(['error' => ['Beneficiary Not Fund!']]);
        }
        $confirm_methods = [
            Str::slug(GlobalConst::TRX_OWN_BANK_TRANSFER)   => "ownBankTransferSelect",
            Str::slug(GlobalConst::TRX_OTHER_BANK_TRANSFER)   => "otherBankTransferSelect",
        ];
        if(!array_key_exists($beneficiary->info->method->slug,$confirm_methods)) abort(404);
        $method = $confirm_methods[$beneficiary->method->slug];
        
        if(!method_exists($this,$method)) {
            return redirect()->route('user.fund-transfer.index')->with(['warning' => ["This section is under construction."]]);
        }
        return $this->$method($request,$beneficiary);
    }
    
    /**
     * Fund Transfer Create
     * @method GET
     * @param  $token
     * @return \Illuminate\Http\Response
     */
    public function create($token){
        $page_title = 'Transfer Money';
        $temp_data = TemporaryData::where('identifier', $token)->first();
        if(!$temp_data) return redirect()->route('user.fund-transfer.index')->with(['error'=> ['Invalid Request!']]);
        $fees_and_charge = TransactionSetting::whereSlug($temp_data->data->beneficiary->method->slug)->active()->first();

        if(!$fees_and_charge) return redirect()->route('user.fund-transfer.index')->with(['error'=> ['Fess And Charge Not Defined, Please Contact With Support!']]);
        if($fees_and_charge->title == GlobalConst::TRX_OWN_BANK_TRANSFER){
            $user_daily_total_transactions = Transaction::where('user_id',auth()->user()->id)->OwnBankTransfer()->today()->get()->sum('request_amount');
            $remaining_daily_amount         = $fees_and_charge->daily_limit - $user_daily_total_transactions;
            $user_monthly_total_transactions = Transaction::where('user_id',auth()->user()->id)->OwnBankTransfer()->whereBetween('created_at',[now()->firstOfMonth(),now()->lastOfMonth()])->get()->sum('request_amount');
            $remaining_monthly_amount         = $fees_and_charge->monthly_limit - $user_monthly_total_transactions;
        }else{
            $user_daily_total_transactions = Transaction::where('user_id',auth()->user()->id)->OtherBankTransfer()->today()->get()->sum('request_amount');
            $remaining_daily_amount         = $fees_and_charge->daily_limit - $user_daily_total_transactions;
            $user_monthly_total_transactions = Transaction::where('user_id',auth()->user()->id)->OtherBankTransfer()->whereBetween('created_at',[now()->firstOfMonth(),now()->lastOfMonth()])->get()->sum('request_amount');
            $remaining_monthly_amount         = $fees_and_charge->monthly_limit - $user_monthly_total_transactions;
        }
        
        return view('user.sections.fund-transfer.create', compact('token','page_title','fees_and_charge','temp_data','remaining_daily_amount','remaining_monthly_amount'));
    }
    /**
     *  Fund Transfer Submit
     *
     * @method POST
     * @param Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function submit(Request $request){
        $validator = Validator::make($request->all(), [
            'temp_token' => 'required|exists:temporary_datas,identifier',
            'amount'     => 'numeric|required|gt:0',
            'remarks'    => 'nullable|string',
        ]);

        $validated = $validator->validate();

        $temp_data = TemporaryData::where('identifier', $validated['temp_token'])->first();
        if(!$temp_data) return back()->with(['error'=> ['Invalid Request']]);

        $fees_and_charge = TransactionSetting::whereSlug($temp_data->data->beneficiary->method->slug)->active()->first();

        $confirm_methods = [
            Str::slug(GlobalConst::TRX_OWN_BANK_TRANSFER)   => "ownBankTransferSubmit",
            Str::slug(GlobalConst::TRX_OTHER_BANK_TRANSFER)   => "otherBankTransferSubmit",
        ];

        if(!array_key_exists($temp_data->data->beneficiary->method->slug,$confirm_methods)) abort(404);
        $method = $confirm_methods[$temp_data->data->beneficiary->method->slug];

        if(!method_exists($this,$method)) {
            return redirect()->route('user.fund-transfer.index')->with(['warning' => ["This section is under construction"]]);
        }
        return $this->$method($validated,$fees_and_charge,$temp_data);

    }
    /**
     *  Fund Transfer Preview
     * @method Get
     * @param  $token
     * @return \Illuminate\Http\Response
     */
    public function preview($token){
        $temp_data = TemporaryData::where('identifier', $token)->first();
        if(!$temp_data) return redirect()->route('user.fund-transfer.index')->with(['error'=> ['Invalid Request!']]);
        $page_title = "Fund Transfer Preview";
        return view('user.sections.fund-transfer.preview', compact('page_title', 'temp_data'));
    }
    /**
     *  Fund Transfer Submit
     * @method POST
     * @return \Illuminate\Http\Response
     */
    public function previewSubmit(Request $request){
        $validated = $request->validate([
            'temp_token' => "required|exists:temporary_datas,identifier",
            'code'       => 'nullable',
        ]);
        $confirm_methods = [
            Str::slug(GlobalConst::TRX_OWN_BANK_TRANSFER)   => "ownBankTransferPreviewSubmit",
            Str::slug(GlobalConst::TRX_OTHER_BANK_TRANSFER)   => "otherBankTransferPreviewSubmit",
        ];

        $temp_data = TemporaryData::where('identifier', $validated['temp_token'])->first();
        if(!$temp_data) return back()->with(['error'=> ['Invalid Request']]);

        if(!array_key_exists($temp_data->data->beneficiary->method->slug,$confirm_methods)) abort(404);

        $method = $confirm_methods[$temp_data->data->beneficiary->method->slug];

        if(!method_exists($this,$method)) {
            return back()->with(['warning' => ["This section is under construction"]]);
        }

        return $this->$method($temp_data);
    }
    /**
     *  Own Bank Transfer Submit
     * @method GET
     * @param $trx_id
     * @return \Illuminate\Http\Response
     */
    public function transactionSuccess($trx_id){
        $page_title = 'Transaction Successful';
        return view('user.sections.fund-transfer.transaction-success', compact('page_title','trx_id'));
    }
    /**
     *  Charges Calculation
     *
     * @param $sender_amount, $charges, $user_wallet
     * @return \Illuminate\Http\Response
     */
    public function transferCharges($sender_amount,$charges,$user_wallet) {
        $data['request_amount']        = $sender_amount;
        $data['sender_currency']       = get_default_currency_code();
        $data['receiver_amount']       = $sender_amount;
        $data['receiver_currency']     = get_default_currency_code();
        $data['receiver_currency_id']  = $user_wallet->currency->id;
        $data['percent_charge']        = ($sender_amount / 100) * $charges->percent_charge ?? 0;
        $data['fixed_charge']          = $charges->fixed_charge ?? 0;
        $data['total_charge']          = $data['percent_charge'] + $data['fixed_charge'];
        $data['sender_wallet_balance'] = $user_wallet->balance;
        $data['payable']               = $sender_amount + $data['total_charge'];
        return $data;
    }

    /**
     *  Transaction Device Record
     *
     * @param $sender_amount, $charges, $user_wallet
     * @return \Illuminate\Http\Response
     */
    public function createTransactionDeviceRecord($transaction_id) {
        $client_ip = request()->ip() ?? false;
        $location = geoip()->getLocation($client_ip);
        $agent = new Agent();

        $mac = "";

        DB::beginTransaction();
        try{
            DB::table("transaction_devices")->insert([
                'transaction_id'=> $transaction_id,
                'ip'            => $client_ip,
                'mac'           => $mac,
                'city'          => $location['city'] ?? "",
                'country'       => $location['country'] ?? "",
                'longitude'     => $location['lon'] ?? "",
                'latitude'      => $location['lat'] ?? "",
                'timezone'      => $location['timezone'] ?? "",
                'browser'       => $agent->browser() ?? "",
                'os'            => $agent->platform() ?? "",
            ]);
            DB::commit();
        }catch(Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
    }

    /**
     *  PDF Download
     *
     * @param $trx_id
     * @return \Illuminate\Http\Response
    */
    public function pdfDownload($trx_id){
        $transaction = Transaction::where('trx_id',$trx_id)->first();

        if(!$transaction) return back(['error'=> ['Invalid Request!']]);

        $pdf = Pdf::loadView('user.sections.pdf.fund-transfer', compact('transaction'))->setOption(['dpi' => 150, 'defaultFont' => 'sans-serif']);
        $pdf_download_name =  $transaction->trx_id ?? now()->format("d-m-Y H:i");
        return $pdf->download($pdf_download_name.".pdf");
    }
}
