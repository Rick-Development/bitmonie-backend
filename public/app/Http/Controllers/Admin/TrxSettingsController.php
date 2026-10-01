<?php

namespace App\Http\Controllers\Admin;

use Exception;
use Illuminate\Http\Request;
use App\Http\Helpers\Response;
use App\Models\TransactionMethod;
use App\Http\Controllers\Controller;
use App\Models\Admin\TransactionSetting;
use Illuminate\Support\Facades\Validator;

class TrxSettingsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $page_title = "Fees & Charges";
        $transaction_charges = TransactionSetting::all();
        return view('admin.sections.trx-settings.index',compact(
            'page_title',
            'transaction_charges'
        ));
    }

    /**
     * Update transaction charges
     * @param Request closer
     * @return back view
     */
    public function trxChargeUpdate(Request $request) {
        $validator = Validator::make($request->all(),[
            'slug'                              => 'required|string',
            $request->slug.'_fixed_charge'      => 'required|numeric',
            $request->slug.'_percent_charge'    => 'required|numeric',
            $request->slug.'_min_limit'         => 'required|numeric',
            $request->slug.'_max_limit'         => 'required|numeric',
            $request->slug.'_daily_limit'       => 'required|numeric',
            $request->slug.'_monthly_limit'     => 'required|numeric',
        ]);
        $validated = $validator->validate();

        $transaction_setting = TransactionSetting::where('slug',$request->slug)->first();

        if(!$transaction_setting) return back()->with(['error' => ['Transaction charge not found!']]);
        $validated = replace_array_key($validated,$request->slug."_");

        try{
            $transaction_setting->update($validated);
        }catch(Exception $e) {
            return back()->with(['error' => ["Something went wrong! Please try again."]]);
        }

        return back()->with(['success' => ['Charge Updated Successfully!']]);

    }


    public function methods() {
        $page_title =  "Transaction Methods";
        $methods = TransactionMethod::orderByDesc("id")->get();
        return view('admin.sections.trx-settings.methods',compact('page_title','methods'));
    }

    public function statusUpdate(Request $request) {
        $validator = Validator::make($request->all(),[
            'data_target'       => "required|string|exists:transaction_methods,slug",
            'status'            => "required|numeric",
        ]);

        if($validator->fails()) {
            return Response::error($validator->errors(),null,400);
        }

        $validated = $validator->validate();

        try{
            TransactionMethod::where("slug",$validated['data_target'])->update([
                'status'       => ($validated['status'] == true) ? false : true,
            ]);
        }catch(Exception $e) {
            $error = ['error' => ['Something went wrong!. Please try again.']];
            return Response::error($error,null,500);
        }

        $success = ['success' => ['Method status updated successfully!']];
        return Response::success($success,null,200);
    }
}
