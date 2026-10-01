<?php

namespace App\Http\Controllers\Admin;

use Exception;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Http\Helpers\Response;
use App\Models\Admin\BankList;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class BankListController extends Controller
{
     /**
     *  Bank List
     *
     * @method GET
     * @return \Illuminate\Http\Response
     */

     public function index(){
        $page_title = "Bank List";
        $banks = BankList::latest()->paginate(20);
        return view('admin.sections.fund-transfer.bank-list.index', compact(
            'page_title','banks'
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
            'name'      => 'required|string|max:200|unique:bank_lists,name',
        ]);

        if($validator->fails()) {
            return back()->withErrors($validator)->withInput()->with('modal','bank-list-add');
        }

        $validated = $validator->validate();
        $slugData = Str::slug($request->name);
        $makeUnique = BankList::where('alias',  $slugData)->first();
        if($makeUnique){
            return back()->with(['error' => [ $request->name.' '.'Bank Already Exists!']]);
        }
        $admin = Auth::user();

        $validated['admin_id'] = $admin->id;
        $validated['name']     = $request->name;
        $validated['alias']    = $slugData;
        try{
            BankList::create($validated);
            return back()->with(['success' => ['Bank Saved Successfully!']]);
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
        $bank = BankList::where('id',$target)->first();

        $validator = Validator::make($request->all(),[
            'name'      => 'required|string|max:200',
        ]);

        if($validator->fails()) {
            return back()->withErrors($validator)->withInput()->with('modal','edit-bank-list');
        }
        $validated = $validator->validate();

        $slugData = Str::slug($request->name);
        $makeUnique = BankList::where('id',"!=",$bank->id)->where('alias',  $slugData)->first();
        if($makeUnique){
            return back()->with(['error' => [ $request->name.' '.'Bank Already Exists!']]);
        }
        $validated['name']     = $request->name;
        $validated['alias']    = $slugData;

        try{
            $bank->update($validated);
            return back()->with(['success' => ['Bank Updated Successfully!']]);
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

        $bank = BankList::where('id',$bank_id)->first();
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

        $success = ['success' => ['Bank status updated successfully!']];
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
            'target'        => 'required|string|exists:bank_lists,id',
        ]);
        $validated = $validator->validate();
        $bank = BankList::where("id",$validated['target'])->first();

        try{
            $bank->delete();
        }catch(Exception $e) {
            return back()->with(['error' => ['Something went wrong, Please try again.']]);
        }
        return back()->with(['success' => ['Bank deleted successfully!']]);
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

        $banks = BankList::search($validated['text'])->select()->limit(10)->get();
        return view('admin.components.search.bank-list-search',compact(
            'banks',
        ));
    }
}
