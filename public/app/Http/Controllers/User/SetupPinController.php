<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SetupPinController extends Controller
{
    /**
     * Method for show setup page
     * @return view
     */
    public function index(){
        $page_title = "Setup Pin";

        return view('user.sections.setup-pin.index',compact('page_title'));
    }
    /**
     * Method for store pin information
     * @param Illuminate\Http\Request $request
     */
    public function store(Request $request){
        $validator      = Validator::make($request->all(),[
            'pin_code'  => 'required|digits:4',
        ]);
        if($validator->fails()){
            return back()->withErrors($validator)->withInput($request->all());
        }
        $validated  = $validator->validated();
        $user       = auth()->user();
        try{
            $user->update([
                'pin_code'  => $validated['pin_code'],
                'pin_status'    => true,
            ]);
        }catch(Exception $e){
            return back()->with(['error' => ['Something went wrong! Please try again.']]);
        }
        return back()->with(['success' => ['Pin setup successfully.']]);
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
            return back()->withErrors($validator)->withInput($request->all());
        }
        $validated  = $validator->validated();
        $user       = auth()->user();
        if($validated['old_pin'] != $user->pin_code){
            return back()->with(['error' => ['Old pin code not matched!']]);
        }
        try{
            $user->update([
                'pin_code'  => $validated['new_pin'],
                'pin_status'    => true,
            ]);
        }catch(Exception $e){
            return back()->with(['error' => ['Something went wrong! Please try again.']]);
        }
        return back()->with(['success' => ['Pin updated successfully.']]);
    }
}
