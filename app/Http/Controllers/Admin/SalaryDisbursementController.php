<?php

namespace App\Http\Controllers\Admin;

use Exception;
use App\Models\User;
use App\Models\UserWallet;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\TemporaryData;
use App\Constants\GlobalConst;
use App\Http\Helpers\Response;
use Illuminate\Support\Facades\DB;
use App\Models\Admin\BasicSettings;
use App\Http\Controllers\Controller;
use App\Constants\PaymentGatewayConst;
use Illuminate\Support\Facades\Validator;
use App\Models\Admin\SalaryDisbursementUser;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use App\Notifications\Admin\SalaryDisbursementNotification;
use App\Notifications\Admin\SalaryDisbursementSenderNotification;

class SalaryDisbursementController extends Controller
{
    /**
     * Method for view salary disbursement page
     * @return view
     */
    public function index(){
        $page_title         = "Salary Disbursement";
        $companies          = User::with(['wallet'])->where('account_type',GlobalConst::BUSINESS_ACCOUNT)->where('status',1)->orderBy('id','desc')->get();
        
        return view('admin.sections.salary-disbursement.index',compact(
            'page_title',
            'companies',
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
        $companies = User::search($validated['text'])->limit(10)->get();
        return view('admin.components.data-table.salary-disbursement',compact(
            'companies',
        ));
    }
    /**
     * Method for company details page
     * @param $username
     * @return view
     */
    public function details($username){
        $page_title         = "Company Details";
        $company            = User::with(['wallet'])->where('username',$username)->first();
        if(!$company) return back()->with(['error' => ['Sorry! Company not found.']]);
        $users              = User::where('account_type',GlobalConst::PERSONAL_ACCOUNT)->where('status',1)->orderBy('id','desc')->get();
        $company_users      = SalaryDisbursementUser::where('company_username',$username)->get();

        return view('admin.sections.salary-disbursement.details',compact(
            'page_title',
            'company',
            'users',
            'company_users'
        ));
    }
    /**
     * Method for check valid user
     */
    public function checkUser(Request $request){
        $validator  = Validator::make($request->all(),[
            'email' => 'required',
        ]);
        if($validator->fails()) return Response::error($validator->errors()->all());

        $validated  = $validator->validate();
        $exist['data']       = User::where('email',$validated['email'])->first();
        if(!$exist['data'] ){
            return response()->json(['own'=>__("User not found!")]);
        }
        return response($exist);
    }
    /**
     * Method for store salary disbursement user
     * @param Illuminate\Http\Request $request
     */
    public function store(Request $request,$username){
        $validator          = Validator::make($request->all(),[
            'user_email'    => 'required|email',
            'amount'        => 'required|numeric'
        ]);
        if($validator->fails()) return back()->withErrors($validator)->withInput()->with('modal','user-add');
        $validated          = $validator->validate();

        $company            = User::where('username',$username)->first();
        if(!$company) return back()->with(['error' => ['Company not found!']]);
        $user               = User::where('email',$validated['user_email'])->first();
        if(!$user) return back()->with(['error' => ['User not found!']]);
        if(SalaryDisbursementUser::where('user_email',$validated['user_email'])->exists()){
            throw ValidationException::withMessages([
                'name'  => 'Employee already exists.',
            ]);
        }
        $validated['company_email']     = $company->email;
        $validated['company_name']      = $company->fullname;
        $validated['company_username']  = $company->username;
        $validated['user_name']         = $user->fullname;
        $validated['user_email']        = $user->email;
        $validated['user_username']     = $user->username;
        
        try{
            SalaryDisbursementUser::create($validated);
        }catch(Exception $e){
            return back()->with(['error' => ['Something went wrong! Please try again.']]);
        }
        return back()->with(['success' => ['Employee added successfully.']]);        
    }
    /**
     * Method for update Remittance bank 
     * @param string
     * @param \Illuminate\Http\Request $request 
     */
    public function update(Request $request){
        $validator = Validator::make($request->all(),[
            'target'        => 'required|numeric|exists:salary_disbursement_users,id',
            'edit_amount'   => 'required|numeric',
        ]);

        if($validator->fails()) {
            return back()->withErrors($validator)->withInput()->with("modal","edit-employee");
        }

        $validated = $validator->validate();
        $validated = replace_array_key($validated,"edit_");
        $validated = Arr::except($validated,['target']);
        $employee = SalaryDisbursementUser::find($request->target);      
        
        try{
            $employee->update($validated);
        }catch(Exception $e) {
            return back()->with(['error' => ['Something went wrong! Please try again']]);
        }

        return back()->with(['success' => ['Amount updated successfully!']]);

    }
    /**
     * Method for delete employee information
     * @param Illuminate\Http\Request $request
     */
    public function delete(Request $request){
        $request->validate([
            'target'    => 'required|numeric|',
        ]);
        $employee = SalaryDisbursementUser::find($request->target);
    
        try {
            $employee->delete();
        } catch (Exception $e) {
            return back()->with(['error' => ['Something went wrong! Please try again.']]);
        }
        return back()->with(['success' => ['Employee deleted successfully!']]);
    }
    /**
     * Method for view employee list page
     * @param $username
     * @return view
     */
    public function employeeList($username){
        $page_title         = "Salary Disbursement Send";
        $employee_lists     = SalaryDisbursementUser::where('company_username',$username)->orderby("id","desc")->get();
        if(count($employee_lists) == 0) return back()->with(['error' => ['Please add employee first!']]);

        return view('admin.sections.salary-disbursement.employee-list',compact(
            'page_title',
            'employee_lists',
            'username'
        ));
        
    }
    /**
     * Method for send salary
     * @param $username
     */
    public function send(Request $request,$username){
        $company        = User::where('username',$username)->first();
        if(!$company) return back()->with(['error' => ['Sorry! Company not found.']]);

        $validator      = Validator::make($request->all(),[
            'username'  => 'required|array',
            'username.*'=> 'required|string',
            'amount'    => 'required|array',
            'amount.*'  => 'required|numeric'
        ]);
        if($validator->fails()) return back()->withErrors($validator)->withInput($request->all());
        $validated      = $validator->validate();

        $employees_data = [];
        foreach ($validated['username'] as $index => $employee_username) {
            $employees_data[]   = [
                'username'      => $employee_username,
                'amount'        => $validated['amount'][$index],
            ];
        }
        $form_data  = [
            'company_username'  => $username,
            'receiver_info'     => $employees_data
        ];

        $validated['type']      = PaymentGatewayConst::SALARYDISBURSEMENT;
        $validated['identifier']= Str::uuid(); 
        $validated['data']      = json_encode($form_data);
        try{
            $temporary_data     = TemporaryData::create($validated);
        }catch(Exception $e){
            return back()->with(['error' => ['Something went wrong! Please try again.']]);
        }
        return redirect()->route('admin.salary.disbursement.preview',$temporary_data->identifier);
    }
    /**
     * Method for view the preview page
     * @return view
     */
    public function preview($identifier){
        $page_title     = "Salary Disbursement Preview";
        $temporary_data = TemporaryData::where('identifier',$identifier)->first();
        if(!$temporary_data) return back()->with(['error' => ['Sorry! Data not found.']]);
        $data = json_decode($temporary_data->data, true);
        $total_amount = array_sum(array_column($data['receiver_info'], 'amount'));
        
        $company    = User::where('username',$data['company_username'])->first();
        $available_balance = UserWallet::where('user_id',$company->id)->select('balance')->first()->balance;;
        
        return view('admin.sections.salary-disbursement.preview',compact(
            'page_title',
            'identifier',
            'total_amount',
            'available_balance',
            'data'
        ));
    }
    /**
     * Method for confirm salary disbursement
     * @param $identifier
     */
    public function confirm($identifier){
        $temporary_data     = TemporaryData::where('identifier',$identifier)->first();
        if(!$temporary_data) return back()->with(['error' => ['Sorry! Data not found.']]);

        $data = json_decode($temporary_data->data, true);
        $total_amount = array_sum(array_column($data['receiver_info'], 'amount'));
        $company        = User::where('username',$data['company_username'])->first();
        $company_wallet = UserWallet::where('user_id',$company->id)->first();

        if($company_wallet->balance < $total_amount){
            return back()->with(['error' => ['Sorry! Insufficient balance.']]);
        }
        $salary_disbursement_id = generateTrxString('transactions','trx_id','SD-',14);
        
        
        try{
            $this->insertSenderData($company,$company_wallet,$total_amount,$salary_disbursement_id,$data);

            foreach($data['receiver_info'] as $item){
                $user           = User::where('username',$item['username'])->first();
                $user_wallet    = UserWallet::where('user_id',$user->id)->first();
                $amount         = $item['amount'];
                $this->receiverData($user,$user_wallet,$amount,$salary_disbursement_id,$company);
            }
        }catch(Exception $e){
            return back()->with(['error' => ['Something went wrong! Please try again.']]);
        }
        return redirect()->route('admin.salary.disbursement.index')->with(['success' =>['Salary disbursement successfully.']]);      

    }
    //transaction saved
    function receiverData($user,$wallet,$amount,$salary_disbursement_id,$company){
        $basic_settings     = BasicSettings::first();
        $trx_id = generateTrxString('transactions','trx_id','SD-',14);
        $available_balance = $wallet->balance + $amount;
        $details    = [
            'fullname'  => $company->fullname,
        ];
        DB::beginTransaction();
        try{
            $id                         = DB::table('transactions')->insertGetId([
                'type'                  => PaymentGatewayConst::SALARYDISBURSEMENT,
                'trx_id'                => $trx_id,
                'salary_disbursement_id'=> $salary_disbursement_id,
                'user_type'             => GlobalConst::ADMIN,
                'user_id'               => $user->id,
                'wallet_id'             => $wallet->id,
                'admin_id'              => auth()->user()->id,
                'request_amount'        => $amount,
                'request_currency'      => get_default_currency_code(),
                'total_charge'          => 0,
                'total_payable'         => $amount,
                'receive_amount'        => $amount,
                'available_balance'     => $available_balance,
                'details'               => json_encode($details),
                'status'                => PaymentGatewayConst::STATUSSUCCESS,
                'attribute'             => PaymentGatewayConst::RECEIVED,
                'created_at'            => now()
            ]);
            $this->updateReceiverWalletBalance($wallet,$amount);

            user_notification_data_save($user->id,$type = PaymentGatewayConst::SALARYDISBURSEMENT,$title = "Salary Disbursement",$id,$amount,$gateway=null,get_default_currency_code(),$message="Successfully added.");

            if($basic_settings->email_notification == true){
                try{
                    Notification::route('mail',$user->email)->notify(new SalaryDisbursementNotification($user->fullname,$amount,auth()->user()->fullname,$trx_id));
                }catch(Exception $e){}                
            }
            DB::commit();
        }catch(Exception $e) {
            DB::rollBack();
            return back()->with(['error' => ['Something went wrong! Please try again']]);
        }
        return $id;
    }
    // update wallet balance
    function updateReceiverWalletBalance($wallet,$amount){
        $available_balance = $wallet->balance + $amount;
        $wallet->update([
            'balance' => $available_balance,
        ]);
    }
    //insert sender data
    function insertSenderData($user,$wallet,$amount,$salary_disbursement_id,$data){
        $basic_settings     = BasicSettings::first();
        $trx_id = generateTrxString('transactions','trx_id','SD-',14);
        $available_balance = $wallet->balance - $amount;
        DB::beginTransaction();
        try{
            $id                         = DB::table('transactions')->insertGetId([
                'type'                  => PaymentGatewayConst::SALARYDISBURSEMENT,
                'trx_id'                => $trx_id,
                'salary_disbursement_id'=> $salary_disbursement_id,
                'user_type'             => GlobalConst::ADMIN,
                'user_id'               => $user->id,
                'wallet_id'             => $wallet->id,
                'admin_id'              => auth()->user()->id,
                'request_amount'        => $amount,
                'request_currency'      => get_default_currency_code(),
                'total_charge'          => 0,
                'total_payable'         => $amount,
                'receive_amount'        => $amount,
                'available_balance'     => $available_balance,
                'details'               => json_encode($data),
                'status'                => PaymentGatewayConst::STATUSSUCCESS,
                'attribute'             => PaymentGatewayConst::SEND,
                'created_at'            => now()
            ]);
            $this->updateSenderWalletBalance($wallet,$amount);

            user_notification_data_save($user->id,$type = PaymentGatewayConst::SALARYDISBURSEMENT,$title = "Salary Disbursement",$id,$amount,$gateway=null,get_default_currency_code(),$message="Successfully send.");

            if($basic_settings->email_notification == true){
                try{
                    Notification::route('mail',$user->email)->notify(new SalaryDisbursementSenderNotification($user->fullname,$amount,$trx_id));
                }catch(Exception $e){}                
            }
            DB::commit();
        }catch(Exception $e) {
            DB::rollBack();
            return back()->with(['error' => ['Something went wrong! Please try again']]);
        }
        return $id;
    }
    // update wallet balance
    function updateSenderWalletBalance($wallet,$amount){
        $available_balance = $wallet->balance - $amount;
        $wallet->update([
            'balance' => $available_balance,
        ]);
    }

}
