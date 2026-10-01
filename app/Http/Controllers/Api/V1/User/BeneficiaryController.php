<?php

namespace App\Http\Controllers\Api\V1\User;

use Exception;
use App\Models\User;
use App\Models\Beneficiary;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Constants\GlobalConst;
use App\Http\Helpers\Response;
use App\Models\Admin\BankList;
use Illuminate\Validation\Rule;
use App\Models\Admin\BankBranch;
use App\Models\TransactionMethod;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class BeneficiaryController extends Controller
{
    /**
     * Method for get the beneficiary information
     * @return response
     */
    public function index(){
        $beneficiary      = Beneficiary::with(['method'])->auth()->orderBy('id','desc')->get()->map(function($item){
            $method_name = $item->method->name;
            $info = $item->info;
            

            $beneficiary_data = [
                'id'                    => $item->id,
                'beneficiary_type'      => $method_name,
                'slug'                  => $item->slug,
                'account_number'        => $info->account_number,
                'account_holder_name'   => $info->account_holder_name,
                'email'                 => $info->email,
                'beneficiary_subtype'   => $info->beneficiary_subtype
            ];

            if ($method_name != GlobalConst::TRX_OWN_BANK_TRANSFER) {
                $beneficiary_data['bank_name'] = $info->bank->name;
                $beneficiary_data['branch_name'] = $info->branch->name;
                $beneficiary_data['image']  = '';

            }else{
                $user_info          = User::where('account_no',$info->account_number)->first();

                $beneficiary_data['image']  = $user_info->image;
            }

            return $beneficiary_data;
            
        });
        $image_paths = [
            'base_url'          => url("/"),
            'path_location'     => files_asset_path_basename("user-profile"),
            'default_image'     => files_asset_path_basename("profile-default"),
        ];

        return Response::success(['Beneficiary data fetch successfully.'],[
            'beneficiary'       => $beneficiary,
            'image_path'        => $image_paths
        ]);
    }
    /**
     * Method for get the methods information
     * @return response
     */
    public function method(){
        $methods    = TransactionMethod::active()->get()->map(function($data){
            return [
                'slug'      => $data->slug,
                'name'      => $data->name
            ];
        });
        return Response::success(['Methods data fetch successfully.'],[
            'methods'       => $methods
        ]);
    }
    /**
     * Method for get bank list information
     * @return response
     */
    public function bankList(){
        $bank_list      = BankList::with(['branches'])->active()->orderBy('id','desc')->get()->map(function($item){
            return [
                'bank_id'           => $item->id,
                'bank_name'         => $item->name,
                'branches'          => $item->branches->map(function($branch) {
                    return [
                        'id'   => $branch->id,
                        'name' => $branch->name,
                    ];
                }),
            ];
        });

        return Response::success(['Bank data fetch successfully.'],[
            'bank_list'       => $bank_list
        ]);
    }
    /**
     * Method for get the branch info using bank
     * @param Illuminate\Http\Request $request
     */
    public function findBranch(Request $request){
        $validator          = Validator::make($request->all(),[
            'bank_id'       => 'required|integer',
        ]);
        if($validator->fails()) return Response::error([$validator->errors()->all(),[]]);
        $validated          = $validator->validate();
        $branch_list        = BankBranch::where('bank_list_id',$validated['bank_id'])->select('id','name')->get()->makeHidden(['editData']);
        if(count($branch_list) == 0) {
            return Response::error([__('Branch not found!')],[],400);
        }
        return Response::success([__('Branch list data fetch successfully.')],[
            'branch_list'   => $branch_list
        ],200);
    }
    /**
     * Method for get the account information.
     * @param Illuminate\Http\Request $request
     */
    public function accountDetails(Request $request){
        $validator          = Validator::make($request->all(),[
            'account_number'=> 'required'
        ]);

        if($validator->fails()) return Response::error($validator->error()->all(),[]);
        $validated          = $validator->validate();
        $user               = auth()->user();
        if($user->account_no == $validated['account_number']){
            return Response::error([__("Sorry! You can not use your account number to create a beneficiary account.")],[],400);
        }
        $account_details    = User::where('account_no',$validated['account_number'])->where('status',true)->first();
        
        if(!$account_details) return Response::error([__('Sorry! There is no user in this account number.')],[],400);

        $account_data           = [
            'name'              => $account_details->fullname ?? '',
            'account_number'    => $account_details->account_no ?? '',
            'email'             => $account_details->email ?? ''
        ];

        return Response::success([__('Account details data fetch successfully.')],[
            'account_details'   => $account_data
        ],200);
    }
    /**
     * Method for store beneficiary data
     * @param Illuminate\Http\Request $request
     */
    public function store(Request $request){
        
        $validator                  = Validator::make($request->all(),[
            'method'                => 'required',
            'beneficiary_subtype'   => ['required','string',Rule::in([GlobalConst::TRX_ACCOUNT_NUMBER])],
            'account_number'        => 'required',
            'account_holder_name'   => 'required',
            'email'                 => 'nullable',
            'bank'                  => 'required_if:method,other-bank-transfer',
            'branch'                => 'required_if:method,other-bank-transfer',
            'phone'                 => 'required_if:method,other-bank-transfer',
            
        ]);
        if($validator->fails()) return Response::error($validator->errors()->all(),[]);
        $validated                  = $validator->validate();
        $method = TransactionMethod::active()->where("slug",$validated['method'])->first();
        if(!$method) return Response::error(['Transaction type isn\'t available or under maintenance'],[],400);

        if($validated['method'] == 'own-bank-transfer'){
            $receiver = User::where('account_no',$validated['account_number'])->first();
            if(!$receiver) return Response::error(['Account doesn\'t exists!'],[],404);
            if(Beneficiary::auth()->where("transaction_method_id",$method->id)->whereJsonContains('info->account_number',$receiver->account_no)->exists()) return Response::error(['Beneficiary already exist'],[],400);
        }else{
            if(isset($validated['account_number'])){
                $account_col = 'account_number';
            }else{
                $account_col = 'card_number';
            }
            if(Beneficiary::where("user_id",auth()->user()->id)->where("transaction_method_id",$method->id)->whereJsonContains('info->'.$account_col,$validated[$account_col])->whereJsonContains('info->bank_id',$validated['bank'])->exists()) return Response::error(['Beneficiary already exist'],[],400);
        }
        
        $slug = Str::uuid();
        
        if($validated['method'] == 'own-bank-transfer'){
            unset($validated['bank'], $validated['branch'], $validated['phone']);
        }else{
            $validated['bank_id'] = $validated['bank'];
            $validated['branch_id'] = $validated['branch'];
            $validated['bank']   = BankList::find($validated['bank']);
            $validated['branch'] = BankBranch::find($validated['branch']);
        }
        $validated['method'] = $method;
        try{
            $beneficiary = Beneficiary::create([
                'transaction_method_id'             => $method->id,
                'user_id'                           => auth()->user()->id,
                'slug'                              => $slug,
                'info'                              => $validated,
            ]);
        }catch(Exception $e) {
            return Response::error(['Something went wrong!. Please try again.'],[],400);
        }

        return Response::success([__('Beneficiary data stored successfully.')],[
            'beneficiary'       => $beneficiary
        ],200);
    }
    /**
     * Method for delete beneficiary information
     * @param Illuminate\Http\Request $request
     */
    public function delete(Request $request){
        $validator      = Validator::make($request->all(),[
            'slug'      => 'required'
        ]);
        if($validator->fails()) return Response::error($validator->errors()->all(),[]);
        $validated      = $validator->validate();
        $beneficiary    = Beneficiary::auth()->where('slug',$validated['slug'])->first();
        if(!$beneficiary) return Response::error([__("Beneficiary data not found.")],[],400);
        try{
            $beneficiary->delete();
        }catch(Exception $e){
            return Response::error(['Something went wrong!. Please try again.'],[],400);
        }
        return Response::success([__('Beneficiary data deleted successfully.')],[],200);
    }
}
