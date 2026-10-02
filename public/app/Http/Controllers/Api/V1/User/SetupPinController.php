<?php

namespace App\Http\Controllers\Api\V1\User;

use Exception;
use Illuminate\Http\Request;
use App\Http\Helpers\Response;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class SetupPinController extends Controller
{
    /**
     * Method for store pin information
     * @param Illuminate\Http\Request $request
     */
    public function store(Request $request){
        $validator      = Validator::make($request->all(),[
            'pin_code'  => 'required|digits:4',
        ]);
        if($validator->fails()){
            return Response::error($validator->errors()->all(),[]);
        }
        $validated  = $validator->validated();
        $user       = auth()->user();
        try{
            $user->update([
                'pin_code'  => $validated['pin_code'],
                'pin_status'    => true,
            ]);
        }catch(Exception $e){
            return Response::error([__('Something went wrong! Please try again')],[],400);
        }
        return Response::success([__('Pin setup successfully.')],[],200);
    }
    /**
     * Method for update pin information
     * @param Illuminate\Http\Request $request
     */
    public function update(Request $request){
        $validator      = Validator::make($request->all(),[
            'old_pin'  => 'required|digits:4',
            'new_pin'  => 'required|digits:4',
        ]);
        if($validator->fails()){
            return Response::error($validator->errors()->all(),[]);
        }
        $validated  = $validator->validated();
        $user       = auth()->user();
        if($validated['old_pin'] != $user->pin_code){
            return Response::error([__('Old pin code not matched!')],[],400);
        }
        try{
            $user->update([
                'pin_code'      => $validated['new_pin'],
                'pin_status'    => true,
            ]);
        }catch(Exception $e){
            return Response::error([__('Something went wrong! Please try again')],[],400);
        }
        return Response::success([__('Pin updated successfully.')],[],200);
    }
}
