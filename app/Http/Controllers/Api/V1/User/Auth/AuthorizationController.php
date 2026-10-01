<?php

namespace App\Http\Controllers\Api\V1\User\Auth;

use Exception;
use Illuminate\Http\Request;
use App\Constants\GlobalConst;
use App\Http\Helpers\Response;
use Illuminate\Support\Carbon;
use App\Models\UserAuthorization;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Traits\ControlDynamicInputFields;
use Illuminate\Support\Facades\Validator;
use App\Providers\Admin\BasicSettingsProvider;
use App\Notifications\User\Auth\SendAuthorizationCode;

class AuthorizationController extends Controller
{
    use ControlDynamicInputFields;

    public static function sendCodeToMail($user = null) {

        if(!$user && auth()->guard("api")->check() == false) throw new Exception(__("Access denied! Unauthenticated"));
        if(!$user) $user = auth()->guard("api")->user();

        $data = [
            'user_id'       => $user->id,
            'code'          => generate_random_code(),
            'token'         => generate_unique_string("user_authorizations","token",200),
            'created_at'    => now(),
        ];

        DB::beginTransaction();
        try{
            UserAuthorization::where("user_id",$user->id)->delete();
            DB::table("user_authorizations")->insert($data);
            try{
                $user->notify(new SendAuthorizationCode((object) $data));
            }catch(Exception $e){}
            DB::commit();
        }catch(Exception $e) {
            DB::rollBack();
            throw new Exception(__("Something went wrong! Please try again"));
        }

        return Response::success([__("Verification code send successfully!")],['token' => $data['token']],200);
    }

    public function resendCodeToMail(Request $request) {
        $validator = Validator::make($request->all(),[
            'token'     => "required|string|exists:user_authorizations,token"
        ]);
        if($validator->fails()) return Response::error($validator->errors()->all(),[]);
        $validated = $validator->validate();
        $user_authorize = UserAuthorization::where("token",$validated['token'])->first();

        if(!$user_authorize) return Response::error([__("Request token is invalid")],[],404);

        if(Carbon::now() <= $user_authorize->created_at->addMinutes(GlobalConst::USER_PASS_RESEND_TIME_MINUTE)) {
            return Response::error(['You can resend verification code after '.Carbon::now()->diffInSeconds($user_authorize->created_at->addMinutes(GlobalConst::USER_PASS_RESEND_TIME_MINUTE)). ' seconds'],['token' => $validated['token'], 'wait_time' => (string) Carbon::now()->diffInSeconds($user_authorize->created_at->addMinutes(GlobalConst::USER_PASS_RESEND_TIME_MINUTE))],400);
        }

        $resend_code = generate_random_code();
        try{
            $user_authorize->update([
                'code'          => $resend_code,
                'created_at'    => now(),
            ]);
            $data = $user_authorize->toArray();
            try{
                $user_authorize->user->notify(new SendAuthorizationCode((object) $data));
            }catch(Exception $e){}

        }catch(Exception $e) {
            return Response::error([__("Something went wrong! Please try again")],[],500);
        }

        return Response::success([__("Verification code resend successfully!")],['token' => $validated['token'],'wait_time' => ""],200);
    }

    public function verifyMailCode(Request $request) {
        $validator = Validator::make($request->all(),[
            'token'     => "required|string|exists:user_authorizations,token",
            'code'      => "required|integer",
        ]);
        if($validator->fails()) {
            return Response::error($validator->errors()->all(),[],400);
        }
        $validated = $validator->validate();

        if(!UserAuthorization::where("code",$request->code)->exists()) {
            return Response::error([__("Invalid OTP. Please try again")],[],404);
        }

        $otp_exp_sec = BasicSettingsProvider::get()->otp_exp_seconds ?? GlobalConst::DEFAULT_TOKEN_EXP_SEC;
        $auth_column = UserAuthorization::where("token",$request->token)->where("code",$request->code)->first();
        if($auth_column->created_at->addSeconds($otp_exp_sec) < now()) {
            $auth_column->delete();
            $this->authLogout($request);
            return Response::error([__("Session expired. Please try again")],[],440);
        }

        try{
            $auth_column->user->update([
                'email_verified'    => true,
            ]);
            $auth_column->delete();
        }catch(Exception $e) {
            $auth_column->delete();
            $this->authLogout($request);
            return Response::error([__("Something went wrong! Please try again")],[],500);
        }

        return Response::success([__("Account successfully verified")],[],200);
    }
}
