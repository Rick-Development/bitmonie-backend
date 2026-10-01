<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Helpers\Payscribe\BillsPayments\PayscribeHelper;
use App\Http\Helpers\Payscribe\PayscribePayoutHelper;
use App\Models\Transaction;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Traits\ApiResponse;
use App\Traits\Notify;


class PayscribeController extends Controller
{
    use ApiResponse,Notify;

    public function __construct(private PayscribeHelper $payscribeHelper, private PayscribePayoutHelper $payscribePayoutHelper) {}
    
    
public function fetchServices(Request $request){
       
        $registerRules = [
            'group_by' => 'required|string',
            ];
        $data = Validator::make($request->all(), $registerRules);
        if ($data->fails()) {
            return response()->json($this->withError(collect($data->errors())->collapse()));
        }
        
        
        
        $response = json_decode($this->payscribeHelper->fetchServices($request->group_by), true);
        return $response;
}

}