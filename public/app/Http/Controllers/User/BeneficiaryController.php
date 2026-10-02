<?php

namespace App\Http\Controllers\User;

use Exception;
use App\Models\User;
use App\Models\Beneficiary;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\TemporaryData;
use App\Constants\GlobalConst;
use App\Http\Helpers\Response;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use App\Models\UserNotification;
use App\Models\TransactionMethod;
use App\Constants\NotificationConst;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\RedirectResponse;
use App\Models\Admin\BankBranch;
use App\Models\Admin\BankList;
use App\Models\Admin\MobileBank;
use Illuminate\Support\Facades\Validator;
use App\Providers\Admin\BasicSettingsProvider;
use App\Notifications\User\BeneficiaryNotification;

class BeneficiaryController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $page_title = "My Beneficiary";
        $beneficiaries = Beneficiary::auth()->orderByDesc("id")->paginate(10);
        $type = GlobalConst::BENEFICIARY;
        return view('user.sections.beneficiary.index',compact("page_title","beneficiaries",'type'));
    }


    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create($type = null)
    {
        $page_title = "Beneficiary Details";
        $methods    = TransactionMethod::active()->get();
        return view('user.sections.beneficiary.create',compact('page_title','methods','type'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @method POST
     * @return \Illuminate\Http\Response
     */
    public function submit(Request $request, $type = null)
    {
        $validator = Validator::make($request->all(),[
            'method'        => "required|string|exists:transaction_methods,slug",
        ]);

        if($validator->fails()) {
            return Response::error($validator->errors(),null,400);
        }

        $validated = $validator->validate();

        return $this->storeDistributor($validated['method'],$request, $type);
    }

    /**
     * Store Distributor
     *
     * @return \Illuminate\Http\Response
     */
    public function storeDistributor($slug,$request, $type) {
        $methods = [
            Str::slug(GlobalConst::TRX_OWN_BANK_TRANSFER)   => "ownBankTransfer",
            Str::slug(GlobalConst::TRX_OTHER_BANK_TRANSFER)   => "otherBankTransfer",
        ];

        if(!array_key_exists($slug,$methods)) return abort(404);
        $method = $methods[$slug];
        return $this->$method($request, $type);
    }

    /**
     * Own Bank Transfer
     *
     * @return \Illuminate\Http\Response
     */
    public function ownBankTransfer(Request $request, $type) {

        $validator = Validator::make($request->all(),[
            'method'              => "required|string",
            'account_number'      => "required|string|exists:users,account_no",
            'account_holder_name' => "required|string|max:120",
            'email'               => "nullable|email|max:120",
            'beneficiary_subtype' => ['required','string',Rule::in([GlobalConst::TRX_ACCOUNT_NUMBER])]
        ]);

        if($validator->fails()) {
            return Response::error($validator->errors(),null,400);
        }

        $validated = $validator->validate();

        $receiver = User::where('account_no',$validated['account_number'])->first();
        if(!$receiver) return Response::error(['error' => ['Account doesn\'t exists!']],null,404);

        $method = TransactionMethod::active()->where("slug",$validated['method'])->first();
        if(!$method) return Response::error(['error' => ['Transaction type isn\'t available or under maintenance']]);
        if(Beneficiary::where("user_id",auth()->user()->id)->where("transaction_method_id",$method->id)->whereJsonContains('info->account_number',$receiver->account_no)->exists()) return Response::error(['error' => ['Beneficiary already exist']]);

        $slug = Str::uuid();
        $validated['method'] = $method;
        try{
            Beneficiary::create([
                'transaction_method_id'             => $method->id,
                'user_id'                           => auth()->user()->id,
                'slug'                              => $slug,
                'info'                              => $validated,
            ]);
        }catch(Exception $e) {
            $error = ['error' => ['Something went wrong!. Please try again.']];
            return Response::error($error,null,500);
        }


        $response_data = [
            'url'    => setRoute('user.beneficiary.index'),
        ];

        $success = ['success' => ['Success']];
        return Response::success($success,$response_data,200);
    }


    /**
     * Other Bank Transfer
     *
     * @return \Illuminate\Http\Response
     */
    public function otherBankTransfer(Request $request, $type) {

        $validator = Validator::make($request->all(),[
            'method'              => "required|string",
            'beneficiary_subtype' => ['required','string',Rule::in([GlobalConst::TRX_ACCOUNT_NUMBER])],
            'account_number'      => "string|max:30|required_if:beneficiary_subtype,==,".GlobalConst::TRX_ACCOUNT_NUMBER,
            'account_holder_name' => "string|max:120|required_if:beneficiary_subtype,==,".GlobalConst::TRX_ACCOUNT_NUMBER,
            'bank'                => "required|string|exists:bank_lists,id",
            'branch'              => "required|string|exists:bank_branches,id",
            'email'               => "nullable",
            'phone'               => "nullable",
        ]);

        if($validator->fails()) {
            return Response::error($validator->errors(),null,400);
        }

        $validated = $validator->validate();

        $method = TransactionMethod::active()->where("slug",$validated['method'])->first();
        if(!$method) return Response::error(['error' => ['Transaction type isn\'t available or under maintenance']]);

        if(isset($validated['account_number'])){
            $account_col = 'account_number';
        }else{
            $account_col = 'card_number';
        }
        if(Beneficiary::where("user_id",auth()->user()->id)->where("transaction_method_id",$method->id)->whereJsonContains('info->'.$account_col,$validated[$account_col])->whereJsonContains('info->bank_id',$validated['bank'])->exists()) return Response::error(['error' => ['Beneficiary already exist']]);

        $validated['bank_id'] = $validated['bank'];
        $validated['branch_id'] = $validated['branch'];
        $validated['bank']   = BankList::find($validated['bank']);
        $validated['branch'] = BankBranch::find($validated['branch']);
        $validated['method'] = $method;
        $slug = Str::uuid();

        try{
            Beneficiary::create([
                'transaction_method_id'             => $method->id,
                'user_id'                           => auth()->user()->id,
                'slug'                              => $slug,
                'info'                              => $validated,
            ]);
        }catch(Exception $e) {
            $error = ['error' => ['Something went wrong!. Please try again.']];
            return Response::error($error,null,500);
        }

        $response_data = [
            'url'    => setRoute('user.beneficiary.index'),
        ];

        $success = ['success' => ['Success']];
        return Response::success($success,$response_data,200);
    }


    /**
     * Beneficiary Preview
     *
     * @method GET
     * @param  $temp_identifier
     * @return \Illuminate\Http\Response
     */
    public function preview($temp_identifier, $type = null){
        $page_title = "Beneficiary Preview";
        $temp_data = TemporaryData::where('identifier', $temp_identifier)->first();
        if(!$temp_data) return back()->with(['error' => ['Invalid Request']]);
        return view('user.sections.beneficiary.preview',compact('page_title','temp_data','type'));
    }

     /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function confirm(Request $request, $type = null){
        $validator = Validator::make($request->all(),[
            'temp_token' => "required|string|exists:temporary_datas,identifier",
            'code'       => 'nullable',
        ]);

        $validated = $validator->validate();

        // Verification OTP Check
        $verification_otp_response = verificationCodeCodeCheck($request);
        if($verification_otp_response instanceof RedirectResponse) return $verification_otp_response;

        $temp_data = TemporaryData::where('identifier', $validated['temp_token'])->first();

        $user = Auth::user();
        $data = $temp_data->data;

        $insert_data = [
            'transaction_method_id'         => $data->method->id,
            'user_id'                       => $user->id,
            'info'                          => $data,
        ];

        try{
            Beneficiary::create($insert_data);
        }catch(Exception $e) {
            $error = ['error' => ['Something went wrong!. Please try again.']];
            return Response::error($error,null,500);
        }

        try {
            $basic_settings = BasicSettingsProvider::get();
            if($basic_settings->email_notification){
                $user->notify(new BeneficiaryNotification($data,$user));
            }
            $this->notification((array) $data);
        } catch (\Exception $e) {

        }

        if($type){
            return redirect()->route('user.fund-transfer.index')->with(['success' =>['Beneficiary Created Successful']]);
        }else{
            return redirect()->route('user.beneficiary.index')->with(['success' =>['Beneficiary Created Successful']]);
        }
    }

    public function getTrxTypeInputs(Request $request) {
        $validator = Validator::make($request->all(),[
            'data'          => "required|string|exists:transaction_methods,slug"
        ]);
        if($validator->fails()) {
            return Response::error($validator->errors());
        }
        $validated = $validator->validate();
       
        switch($validated['data']){
            case Str::slug(GlobalConst::TRX_OWN_BANK_TRANSFER):
                return view('user.components.beneficiary.trx-type-fields.trx-sub-type');
                break;
            case Str::slug(GlobalConst::TRX_OTHER_BANK_TRANSFER):
                return view('user.components.beneficiary.trx-type-fields.other-bank.trx-sub-type');
                break;
            case Str::slug(GlobalConst::TRX_MOBILE_WALLET_TRANSFER):
                $mobile_banks = MobileBank::active()->orderBy('id','desc')->get();
                return view('user.components.beneficiary.trx-type-fields.mobile-wallet.trx-sub-type',compact('mobile_banks'));
                break;
            default:
                return Response::error(['Oops! Data not found or section is under maintenance']);
        }

        return Response::error(['error' => ['Something went wrong! Please try again']]);
    }

    public function getTrxTypeSubInputs(Request $request) {
        $validator = Validator::make($request->all(),[
            'sub_type' => "required|string",
            'method'   => "required|string"
        ]);
        if($validator->fails()) {
            return Response::error($validator->errors());
        }
        $validated = $validator->validate();

        if($validated['method'] == Str::slug(GlobalConst::TRX_OWN_BANK_TRANSFER)){
            switch($validated['sub_type']){
                case Str::slug(GlobalConst::TRX_ACCOUNT_NUMBER):
                    return view('user.components.beneficiary.trx-type-fields.account');
                    break;
                case Str::slug(GlobalConst::TRX_CREDIT_CARD):
                    return view('user.components.beneficiary.trx-type-fields.card-holder');
                    break;
                default:
                    return Response::error(['Oops! Data not found or section is under maintenance']);
            }
        }else{
            $bank_list = BankList::active()->orderBy('id','desc')->get();
            switch($validated['sub_type']){
                case Str::slug(GlobalConst::TRX_ACCOUNT_NUMBER):
                    return view('user.components.beneficiary.trx-type-fields.other-bank.account', compact('bank_list'));
                    break;
                case Str::slug(GlobalConst::TRX_CREDIT_CARD):
                    return view('user.components.beneficiary.trx-type-fields.other-bank.card-holder', compact('bank_list'));
                    break;
                default:
                    return Response::error(['Oops! Data not found or section is under maintenance']);
            }
        }

        return Response::error(['error' => ['Something went wrong! Please try again']]);
    }


    public function getBankBranch(Request $request){
        $validator = Validator::make($request->all(),[
            'value' => "required|string",
        ]);
        if($validator->fails()) {
            return Response::error($validator->errors());
        }
        $validated = $validator->validate();
        $branches = BankBranch::where('bank_list_id', $validated['value'])->get();

        return view('user.components.beneficiary.trx-type-fields.other-bank.bank-branch', compact('branches'));
    }

    public function delete(Request $request){
        $validator = Validator::make($request->all(),[
            'target'        => 'required|string|exists:beneficiaries,id',
        ]);

        $validated = $validator->validate();
        $beneficiary = Beneficiary::find($validated['target'])->first();

        try{
            $beneficiary->delete();
        }catch(Exception $e) {
            return back()->with(['error' => ['Something went wrong! Please try again.']]);
        }

        return back()->with(['success' => ['Beneficiary Deleted Successfully!']]);
    }

    public function search(Request $request, $type) {
        $validator = Validator::make($request->all(),[
            'text'  => 'required|string',
        ]);

        if($validator->fails()) {
            return Response::error($validator->errors(),null,400);
        }
        $validated = $validator->validate();
        $beneficiaries = Beneficiary::auth()->search($validated['text'])->select()->limit(10)->get();

        return view('user.components.search.recipient-search',compact(
            'beneficiaries','type',
        ));
    }

    public function notification($data){

        switch($data['beneficiary_subtype']){
            case Str::slug(GlobalConst::TRX_ACCOUNT_NUMBER):
                $full_name = $data['account_holder_name'];
                break;
            default:
                $full_name = $data['card_holder_name'];
        }

        $notification_content = [
            'title'         => "Beneficiary",
            'message'       => "New Beneficiary Added For (".$data['method']->name.") Beneficiary full name is ".$full_name,
            'time'          => Carbon::now()->diffForHumans(),
            'image'         => auth()->user()->userImage,
        ];

        UserNotification::create([
            'type'      => NotificationConst::BENEFICIARY,
            'user_id'  =>  Auth::guard(get_auth_guard())->user()->id,
            'message'   => $notification_content,
        ]);
    }
}
