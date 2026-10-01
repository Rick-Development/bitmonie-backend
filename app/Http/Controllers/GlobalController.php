<?php

namespace App\Http\Controllers;

use Exception;
use Carbon\Carbon;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Helpers\Response;
use Jenssegers\Agent\Facades\Agent;
use Illuminate\Support\Facades\Validator;

class GlobalController extends Controller
{

    /**
     * Funtion for get state under a country
     * @param country_id
     * @return json $state list
     */
    public function getStates(Request $request) {
        $request->validate([
            'country_id' => 'required|integer',
        ]);
        $country_id = $request->country_id;
        // Get All States From Country
        $country_states = get_country_states($country_id);
        return response()->json($country_states,200);
    }


    public function getCities(Request $request) {
        $request->validate([
            'state_id' => 'required|integer',
        ]);

        $state_id = $request->state_id;
        $state_cities = get_state_cities($state_id);

        return response()->json($state_cities,200);
        // return $state_id;
    }


    public function getCountries(Request $request) {
        $countries = get_all_countries();

        return response()->json($countries,200);
    }


    public function getTimezones(Request $request) {
        $timeZones = get_all_timezones();

        return response()->json($timeZones,200);
    }

    public function userInfoAccount(Request $request) {
        $validator = Validator::make($request->all(),[
            'text'      => "required|string",
        ]);

        if($validator->fails()) {
            return Response::error($validator->errors(),null,400);
        }

        $validated = $validator->validate();

        try{
            if(get_auth_guard() == "web") { // that means auth guard is user-User:: Model // A user can\'t make transaction to own
                $user = User::notAuth()->where('account_no',$validated['text'])->first();
            }else {
                $user = User::where('account_no',$validated['text'])->first();
            }
        }catch(Exception $e) {
            $error = ['error' => ['Something went wrong!. Please try again.']];
            return Response::error($error,null,500);
        }
        $success = ['success' => ['Successfully executed']];
        return Response::success($success,$user,200);
    }
    /**
     * Method for setcookie
     */
    public function setCookie(Request $request){
        $userAgent = $request->header('User-Agent');
        $cookie_status = $request->type;
        if($cookie_status == 'allow'){
            $response_message = __("Cookie Allowed Success");
            $expirationTime = 2147483647; //Maximum Unix timestamp.
        }else{
            $response_message = __("Cookie Declined");
            $expirationTime = Carbon::now()->addHours(24)->timestamp;// Set the expiration time to 24 hours from now.
        }
        $browser = Agent::browser();
        $platform = Agent::platform();
        $ipAddress = $request->ip();
        return response($response_message)->cookie('approval_status', $cookie_status,$expirationTime)
                                            ->cookie('user_agent', $userAgent,$expirationTime)
                                            ->cookie('ip_address', $ipAddress,$expirationTime)
                                            ->cookie('browser', $browser,$expirationTime)
                                            ->cookie('platform', $platform,$expirationTime);
    }
}
