<?php

namespace App\Http\Controllers\Admin;

use Exception;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Http\Controllers\Controller;
use App\Models\Admin\VirtualCardApi;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\VirtualCardTrxExport;
use App\Constants\PaymentGatewayConst;
use Illuminate\Support\Facades\Validator;

class VirtualCardController extends Controller
{
    /**
     * Method for view virtual card page
     * @return view
     */
    public function index(){
        $page_title = "Virtual Card Api";
        $api        = VirtualCardApi::first();

        return view('admin.sections.virtual-card.index',compact(
            'page_title',
            'api',
        ));
    }
    /**
     * Method for update virtual card api information
     * @param Illuminate\Http\Request $request
     */
    public function update(Request $request){
        $validator                      = Validator::make($request->all(), [
            'api_method'                => 'required|in:strowallet',
            'strowallet_public_key'     => 'required_if:api_method,strowallet',
            'strowallet_secret_key'     => 'required_if:api_method,strowallet',
            'strowallet_url'            => 'required_if:api_method,strowallet',
            'card_details'              => 'required|string',
            'image'                     => "nullable|mimes:png,jpg,jpeg,webp,svg",
            'card_limit'                => ['required','numeric',Rule::in([1, 2, 3]),],
        ]);

        if($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $request->merge(['name'=>$request->api_method]);
        $data               = array_filter($request->except('_token','api_method','_method','card_limit','card_details','image'));
        $api                = VirtualCardApi::first();
        $api->card_limit    = $request->card_limit;
        $api->config        = $data;
        if ($request->hasFile("image")) {
            try {
                $image = get_files_from_fileholder($request, "image");
                $upload_file = upload_files_from_path_dynamic($image, "card-api");
                $api->image = $upload_file;
            } catch (Exception $e) {
                return back()->with(['error' => [__("Ops! Failed To Upload Image.")]]);
            }
        }
        try{
            $api->save();
        }catch(Exception $e){
            return back()->with(['error' => [__("Something went wrong! Please try again.")]]);
        }
        
        return back()->with(['success' => [__("Virtual card api credentials updated successfully")]]);
    }
    /**
     * Method for view virtual card logs
     * @return view
     */
    public function transactionLogs()
    {
        $page_title = __("Virtual Card Logs");
        $transactions = Transaction::with(
          'user:id,firstname,lastname,email,username,full_mobile',
        )->where('type', PaymentGatewayConst::TYPEVIRTUALCARD)->latest()->paginate(20);

        return view('admin.sections.virtual-card.logs', compact(
            'page_title',
            'transactions'
        ));
    }
    public function exportData(){
        $file_name = now()->format('Y-m-d_H:i:s') . "_Virtual_Card_Logs".'.xlsx';
        return Excel::download(new VirtualCardTrxExport, $file_name);
    }
}
