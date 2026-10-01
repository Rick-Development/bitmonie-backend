<?php

namespace App\Http\Controllers\User;

use Exception;
use Illuminate\Http\Request;
use App\Constants\GlobalConst;
use App\Http\Helpers\Response;
use App\Models\Admin\SetupKyc;
use Illuminate\Support\Carbon;
use App\Models\UserAuthorization;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Traits\ControlDynamicInputFields;
use Illuminate\Support\Facades\Validator;
use App\Providers\Admin\BasicSettingsProvider;
use Illuminate\Validation\ValidationException;
use App\Notifications\User\Auth\SendAuthorizationCode;
use App\Notifications\User\TrxVerificationNotificaiton;

class AuthorizationController extends Controller
{
    use ControlDynamicInputFields;
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function showMailFrom($token)
    {
        $user_authorize = UserAuthorization::where("token",$token)->first();

        $resend_time = 0;
        if(Carbon::now() <= $user_authorize->created_at->addMinutes(GlobalConst::USER_PASS_RESEND_TIME_MINUTE)) {
            $resend_time = Carbon::now()->diffInSeconds($user_authorize->created_at->addMinutes(GlobalConst::USER_PASS_RESEND_TIME_MINUTE));
        }

        $page_title = setPageTitle("Mail Authorization");
        return view('user.auth.authorize.verify-mail',compact("page_title","token",'resend_time'));
    }

    public function mailResendToken($token) {
        $user_authorize = UserAuthorization::where("token",$token)->first();
        $resend_code = generate_random_code();
        try{
            $user_authorize->update([
                'code'          => $resend_code,
                'created_at'    => now(),
            ]);
            $data = $user_authorize->toArray();
            $user_authorize->user->notify(new SendAuthorizationCode((object) $data));
        }catch(Exception $e) {
            throw ValidationException::withMessages([
                'code'      => "Something went wrong! Please try again.",
            ]);
        }
        return redirect()->route('user.authorize.mail',$token)->with(['success' => [__('Mail OTP Resend Success').'!']]);
    }

    /**
     * Verify authorization code.
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function mailVerify(Request $request,$token)
    {
        $request->merge(['token' => $token]);
        $request->validate([
            'token'     => "required|string|exists:user_authorizations,token",
            'code*'      => "required|integer",
        ]);

        $code = implode($request->code);

        $otp_exp_sec = BasicSettingsProvider::get()->otp_exp_seconds ?? GlobalConst::DEFAULT_TOKEN_EXP_SEC;
        $auth_column = UserAuthorization::where("token",$request->token)->where("code",$code)->first();
        if($auth_column->created_at->addSeconds($otp_exp_sec) < now()) {
            $this->authLogout($request);
            return redirect()->route('user.login')->with(['error' => ['Session expired. Please try again']]);
        }

        try{
            $auth_column->user->update([
                'email_verified'    => true,
            ]);
            $auth_column->delete();
        }catch(Exception $e) {
            $this->authLogout($request);
            return redirect()->route('user.login')->with(['error' => ['Something went wrong! Please try again']]);
        }

        return redirect()->intended(route("user.dashboard"))->with(['success' => ['Account successfully verified']]);
    }

    public function authLogout(Request $request) {
        auth()->guard("web")->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    public function showKycFrom() {
        $user = auth()->user();
        if($user->kyc_verified == GlobalConst::VERIFIED) return back()->with(['success' => ['You are already KYC Verified User']]);
        $page_title = setPageTitle("KYC Verification");
        $user_kyc = SetupKyc::userKyc()->first();
        if(!$user_kyc) return back();
        $kyc_data = $user_kyc->fields;
        $kyc_fields = [];
        if($kyc_data) {
            $kyc_fields = array_reverse($kyc_data);
        }
        return view('user.auth.authorize.verify-kyc',compact("page_title","kyc_fields"));
    }

    public function kycSubmit(Request $request) {

        $user = auth()->user();
        if($user->kyc_verified == GlobalConst::VERIFIED) return back()->with(['success' => ['You are already KYC Verified User']]);

        $user_kyc_fields = SetupKyc::userKyc()->first()->fields ?? [];
        $validation_rules = $this->generateValidationRules($user_kyc_fields);

        $validated = Validator::make($request->all(),$validation_rules)->validate();
        $get_values = $this->placeValueWithFields($user_kyc_fields,$validated);

        $create = [
            'user_id'       => auth()->user()->id,
            'data'          => json_encode($get_values),
            'created_at'    => now(),
        ];

        DB::beginTransaction();
        try{
            DB::table('user_kyc_data')->updateOrInsert(["user_id" => $user->id],$create);
            $user->update([
                'kyc_verified'  => GlobalConst::PENDING,
            ]);
            DB::commit();
        }catch(Exception $e) {
            DB::rollBack();
            $user->update([
                'kyc_verified'  => GlobalConst::DEFAULT,
            ]);
            $this->generatedFieldsFilesDelete($get_values);
            return back()->with(['error' => ['Something went wrong! Please try again']]);
        }

        return redirect()->route("user.profile.index")->with(['success' => ['KYC information successfully submitted']]);
    }

    public function showGoogle2FAForm() {
        $page_title =  "Authorize Google Two Factor";
        return view('user.auth.authorize.verify-google-2fa',compact('page_title'));
    }

    public function google2FASubmit(Request $request) {

        $request->validate([
            'code*'      => "required|integer",
        ]);

        $code = implode($request->code);

        $user = auth()->user();

        if(!$user->two_factor_secret) {
            return back()->with(['warning' => ['Your secret key not stored properly. Please contact with system administrator']]);
        }

        if(google_2fa_verify($user->two_factor_secret,$code)) {

            $user->update([
                'two_factor_verified'   => true,
            ]);

            return redirect()->intended(route('user.dashboard'));
        }

        return back()->with(['warning' => ['Failed to login. Please try again']]);
    }


    public function verificationCodeSend(){
        $user = Auth::user();
        $data = [
            'user_id'       => $user->id,
            'code'          => generate_random_code(),
            'token'         => generate_unique_string("user_authorizations","token",200),
            'created_at'    => now(),
        ];
        try{
            $otp_exp_sec = BasicSettingsProvider::get()->otp_exp_seconds ?? GlobalConst::DEFAULT_TOKEN_EXP_SEC;
            $minutes = floor(($otp_exp_sec / 60) % 60);
            $seconds = $otp_exp_sec % 60;
            $otp_exp_sec = "$minutes:$seconds";

            UserAuthorization::where("user_id",$user->id)->delete();
            $user_authorize = UserAuthorization::create($data);
            try{
                $user->notify(new TrxVerificationNotificaiton((object) $data, $otp_exp_sec));
            }catch(Exception $e){}
            

            $resend_time = 0;
            if(Carbon::now() <= $user_authorize->created_at->addMinutes(GlobalConst::USER_PASS_RESEND_TIME_MINUTE)) {
                $resend_time = Carbon::now()->diffInSeconds($user_authorize->created_at->addMinutes(GlobalConst::USER_PASS_RESEND_TIME_MINUTE));
            }

        }catch(Exception $e) {
            $error = ['error' => 'Something went wrong. Please try again.'];
            return Response::error($error, null, 500);
        }

        $success = ['success' => ['Verification Code Sended!']];
        $data=[
            'to_address'  => $user->email,
            'resend_time' => $resend_time,
        ];
        return Response::success($success,$data,200);
    }

    public function verificationCodeResend() {
        $user = Auth::user();
        $user_authorize = UserAuthorization::where("user_id",$user->id)->first();
        try{

            $otp_exp_sec = BasicSettingsProvider::get()->otp_exp_seconds ?? GlobalConst::DEFAULT_TOKEN_EXP_SEC;
            $minutes = floor(($otp_exp_sec / 60) % 60);
            $seconds = $otp_exp_sec % 60;
            $otp_exp_sec = "$minutes:$seconds";

            $data = [
                'code'          => generate_random_code(),
                'created_at'    => now(),
            ];

            $user_authorize->update($data);

            $data = $user_authorize->toArray();
            try{

                $user_authorize->user->notify(new TrxVerificationNotificaiton((object) $data, $otp_exp_sec));
            }catch(Exception $e){}

        }catch(Exception $e) {
            throw ValidationException::withMessages([
                'code'      => "Something went wrong! Please try again.",
            ]);
        }
        
        return back()->with(['success' => [__('Mail OTP Resend Success').'!']]);
    }

    public function verificationCodeCheck(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'code*'      => "required|integer",
        ]);

        if($validator->stopOnFirstFailure()->fails()){
            $error = ['errors' => $validator->errors()];
            return Response::error($error, null, 400);
        }

        $code = implode($request->code);
        $otp_exp_sec = BasicSettingsProvider::get()->otp_exp_seconds ?? GlobalConst::DEFAULT_TOKEN_EXP_SEC;
        $auth_column = UserAuthorization::where('user_id', Auth::id())->where("code",$code)->first();

        $check = false;
        $data = [
            'check' => $check
        ];

        if(!$auth_column){
            $error = ['error' => ['Invalid Verification Code!']];
            return Response::error($error,$data,500);
        }

        if($auth_column->created_at->addSeconds($otp_exp_sec) < now()) {
            $error = ['error' => ['Session expired. Please try again!']];
            return Response::error($error,$data,500);
        }

        $check = true;
        $data = [
            'check' => $check
        ];

        $success = ['success' => ['Verification Code Verified!']];
        return Response::success($success,$data,200);

    }
}
