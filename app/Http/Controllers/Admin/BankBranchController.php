<?php

namespace App\Http\Controllers\Admin;

use Exception;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Http\Helpers\Response;
use App\Http\Controllers\Controller;
use App\Models\Admin\BankBranch;
use App\Models\Admin\BankList;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class BankBranchController extends Controller
{
    /**
     *  Bank List
     *
     * @method GET
     * @return \Illuminate\Http\Response
     */

    public function index($bank_id = null){
         $page_title = "Bank Branch";
         if($bank_id){
            $branches = BankBranch::with('bank')->where('bank_list_id', $bank_id)->latest()->paginate(20);
            $banks    = BankList::where('id', $bank_id)->active()->get();
        }else{
            $branches = BankBranch::with('bank')->latest()->paginate(20);
            $banks    = BankList::latest()->active()->get();
        }
        return view('admin.sections.fund-transfer.bank-branch.index', compact(
            'page_title','branches','banks'
        ));
    }

    /**
     * Store Bank List
     *
     * @method POST
     * @param Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request){

        $validator = Validator::make($request->all(),[
            'name'      => 'required|string|max:200',
            'bank'      => 'required',
        ]);

        if($validator->fails()) {
            return back()->withErrors($validator)->withInput()->with('modal','bank-branch-add');
        }

        $validated = $validator->validate();

        $slugData = Str::slug($request->name);

        $makeUnique = BankBranch::where('bank_list_id', $validated['bank'])->where('alias', $slugData)->first();
        if($makeUnique){
            return back()->with(['error' => [ $request->name.' '.'Branch Already Exist For The Selected Bank!']])->with('modal','bank-branch-add');
        }

        $admin = Auth::user();

        $validated['admin_id'] = $admin->id;
        $validated['bank_list_id'] = $validated['bank'];
        $validated['alias']    = $slugData;
        try{
            BankBranch::create($validated);
            return back()->with(['success' => ['Bank Branch Saved Successfully!']]);
        }catch(Exception $e) {
            return back()->withErrors($validator)->withInput()->with(['error' => ['Something went wrong, Please try again.']]);
        }
    }

    /**
     * Update Bank List
     *
     * @method PUT
     * @param Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request){
        $target = $request->target;
        $branch = BankBranch::where('id',$target)->first();

        $validator = Validator::make($request->all(),[
            'name'      => 'required|string|max:200',
            'bank'      => 'required',
        ]);

        if($validator->fails()) {
            return back()->withErrors($validator)->withInput()->with('modal','edit-bank');
        }
        $validated = $validator->validate();

        $slugData = Str::slug($request->name);

        $makeUnique = BankBranch::where('bank_list_id', $validated['bank'])->where('alias', $slugData)->whereNot('alias',  $branch->alias)->first();
        if($makeUnique){
            return back()->with(['error' => [ $request->name.' '.'Branch Already Exist For The Selected Bank!']])->with('modal','bank-branch-edit');
        }

        $validated['name']     = $request->name;
        $validated['alias']    = $slugData;

        try{
            $branch->update($validated);
            return back()->with(['success' => ['Bank Branch Updated Successfully!']]);
        }catch(Exception $e) {
            return back()->withErrors($validator)->withInput()->with(['error' => ['Something went wrong, Please try again.']]);
        }
    }

    /**
     * Update Status
     *
     * @method PUT
     * @param Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function statusUpdate(Request $request) {
        $validator = Validator::make($request->all(),[
            'status'                    => 'required|boolean',
            'data_target'               => 'required|string',
        ]);
        if ($validator->stopOnFirstFailure()->fails()) {
            $error = ['error' => $validator->errors()];
            return Response::error($error,null,400);
        }
        $validated = $validator->safe()->all();
        $bank_id = $validated['data_target'];

        $bank = BankBranch::where('id',$bank_id)->first();
        if(!$bank) {
            $error = ['error' => ['Bank record not found in our system.']];
            return Response::error($error,null,404);
        }

        try{
            $bank->update([
                'status' => ($validated['status'] == true) ? false : true,
            ]);
        }catch(Exception $e) {
            $error = ['error' => ['Something went wrong. Please try again.']];
            return Response::error($error,null,500);
        }

        $success = ['success' => ['Branch status updated successfully!']];
        return Response::success($success,null,200);
    }

    /**
     * Bank List Delete
     *
     * @method DELETE
     * @param Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function delete(Request $request) {
        $validator = Validator::make($request->all(),[
            'target'        => 'required|string|exists:bank_branches,id',
        ]);
        $validated = $validator->validate();
        $bank = BankBranch::where("id",$validated['target'])->first();

        try{
            $bank->delete();
        }catch(Exception $e) {
            return back()->with(['error' => ['Something went wrong, Please try again.']]);
        }
        return back()->with(['success' => ['Bank branch deleted successfully!']]);
    }

    /**
     * Bank List Search
     *
     * @method DELETE
     * @param Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function search(Request $request) {
        $validator = Validator::make($request->all(),[
            'text'  => 'required|string',
        ]);

        if($validator->fails()) {
            $error = ['error' => $validator->errors()];
            return Response::error($error,null,400);
        }

        $validated = $validator->validate();

        $branches = BankBranch::search($validated['text'])->select()->limit(10)->get();

        return view('admin.components.search.bank-branch-search',compact(
            'branches',
        ));
    }
}
