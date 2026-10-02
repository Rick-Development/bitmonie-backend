<?php

namespace App\Http\Controllers\Api\V1\User;

use Exception;
use Illuminate\Http\Request;
use App\Constants\GlobalConst;
use App\Http\Helpers\Response;
use App\Models\Admin\SetupKyc;
use App\Models\UserKycData;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Traits\ControlDynamicInputFields;
use Illuminate\Support\Facades\Validator;
use App\Services\YouVerifyService;

class SecurityController extends Controller
{
    use ControlDynamicInputFields;
    /**
     * Get Google 2FA
     * @method Get
     * @param Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
    */
    public function google2FA(){

        $user = Auth::guard(get_auth_guard())->user();

        $qr_code = generate_google_2fa_auth_qr();
        $qr_secrete = $user->two_factor_secret;
        $qr_status = $user->two_factor_status;

       

        return Response::success([__('Data fetch Successfully')],[
            'qr_code'    => $qr_code,
            'qr_secrete' => $qr_secrete,
            'qr_status'  => $qr_status,
            'alert'      => "Don't forget to add this application in your google authentication app. Otherwise you can't login in your account.",
        ],200);
    }
    /**
     * Method for update google 2fa status
     */
    public function google2FAStatusUpdate(Request $request){
        $validator = Validator::make($request->all(),[
            'status'        => "required|numeric",
        ]);

        if($validator->fails()){
            return Response::error([__($validator->errors()->all())],[],400);
        }

        $validated = $validator->validated();

        $user = Auth::guard(get_auth_guard())->user();

        try{
            $user->update([
                'two_factor_status'         => $validated['status'],
                'two_factor_verified'       => true,
            ]);
        }catch(Exception $e) {
            return Response::error([__('Something went wrong! Please try again')],[],500);
        }
        return Response::success([__('Google 2FA Updated Successfully!')],[],200);
    }
    /**
     * Method for verify google 2fa code
     */
    public function verifyGoogle2Fa(Request $request) {
        $validator = Validator::make($request->all(),[
            'code'      => "required|integer",
        ]);
        if($validator->fails()) return Response::error($validator->errors()->all(),[]);
        $validated = $validator->validate();

        $code = $validated['code'];

        $user = auth()->guard(get_auth_guard())->user();

        if(!$user->two_factor_secret) {
            return Response::error([__('Your secret key not stored properly. Please contact with system administrator')],[],400);
        }

        if(google_2fa_verify($user->two_factor_secret,$code)) {

            $user->update([
                'two_factor_verified'   => true,
            ]);

            return Response::success([__('Google 2FA successfully verified!')],[],200);
        }else if(google_2fa_verify($user->two_factor_secret,$code) === false) {
            return Response::error([__('Invalid authentication code')],[],400);
        }

        return Response::error([__('Failed to login. Please try again')],[],500);
    }
    // Get KYC Input Fields
    public function getKycInputFields() {

        $user = auth()->guard(get_auth_guard())->user();

        $instructions = "0: Default, 1: Approved, 2: Pending, 3:Rejected";

        if($user->kyc_verified == GlobalConst::VERIFIED) return Response::success([__("You are already KYC Verified User")],[
            'instructions'  => $instructions,
            'status'        => $user->kyc_verified,
            'input_fields'  => [],
        ],200);
        if($user->kyc_verified == GlobalConst::PENDING) return Response::success([__('Your KYC information is submitted. Please wait for admin confirmation')],[
            'instructions'  => $instructions,
            'status'        => $user->kyc_verified,
            'input_fields'  => [],
        ],200);
        $user_kyc = SetupKyc::userKyc()->first();
        if(!$user_kyc) return Response::error([__('User KYC section is under maintenance')],[],503);
        $kyc_data = $user_kyc->fields;
        if(!$kyc_data) return Response::error([__('User KYC section is under maintenance')],[],503);
        $kyc_fields = array_reverse($kyc_data);
        return Response::success([__('User KYC input fields fetch successfully!')],['instructions'  => $instructions,'status' => $user->kyc_verified,'input_fields' => $kyc_fields],200);
    }
    /**
     * Method for submit kyc information
     * @param Illuminate\Http\Request $request
     */
    public function KycSubmit(Request $request) {
        $user = auth()->guard(get_auth_guard())->user();
        if($user->kyc_verified == GlobalConst::VERIFIED) return Response::warning([__('You are already KYC Verified User')],[],400);

        $user_kyc_fields   = SetupKyc::userKyc()->first()->fields ?? [];
        $validation_rules  = $this->generateValidationRules($user_kyc_fields);

        $validated   = Validator::make($request->all(),$validation_rules)->validate();
        $get_values  = $this->placeValueWithFields($user_kyc_fields,$validated);

        $create = [
            'user_id'    => $user->id,
            'data'       => json_encode($get_values),
            'created_at' => now(),
        ];

        DB::beginTransaction();
        try{
            // Store / update local KYC payload
            DB::table('user_kyc_data')->updateOrInsert(["user_id" => $user->id], $create);

            // Mark as pending while external verification runs
            $user->update([
                'kyc_verified' => GlobalConst::PENDING,
            ]);

            // Kick off YouVerify ID verification
            /** @var YouVerifyService $youVerify */
            $youVerify = app(YouVerifyService::class);
            $result    = $youVerify->startIdVerification($user, (array) $get_values);

            UserKycData::where('user_id', $user->id)->update([
                'provider'           => 'youverify',
                'youverify_reference'=> $result['reference'] ?? null,
                'youverify_status'   => $result['status'] ?? null,
                'youverify_payload'  => $result['raw'] ?? null,
            ]);

            DB::commit();
        }catch(Exception $e) {
            DB::rollBack();
            $user->update([
                'kyc_verified'  => GlobalConst::DEFAULT,
            ]);
            $this->generatedFieldsFilesDelete($get_values);
            return Response::error([__('Something went wrong! Please try again')],[],500);
        }

        return Response::success([__('KYC information successfully submitted')],[],200);
    }

    /**
     * Get current KYC & YouVerify status for authenticated user
     * @method GET
     */
    public function getKycStatus(YouVerifyService $youVerify)
    {
        $user = auth()->guard(get_auth_guard())->user();

        $instructions = "0: Default, 1: Approved, 2: Pending, 3:Rejected";

        $kycData = UserKycData::where('user_id', $user->id)->first();

        // Optionally refresh status from YouVerify when we have a reference
        if ($kycData && $kycData->youverify_reference) {
            try {
                $remote = $youVerify->getVerificationStatus($kycData->youverify_reference);

                $kycData->youverify_status  = $remote['status'] ?? $kycData->youverify_status;
                $kycData->youverify_payload = $remote['raw'] ?? $kycData->youverify_payload;
                $kycData->save();

                $internalStatus = $youVerify->mapStatusToInternal($kycData->youverify_status);
                if (!is_null($internalStatus) && $internalStatus !== $user->kyc_verified) {
                    $user->update(['kyc_verified' => $internalStatus]);
                }
            } catch (\Throwable $e) {
                // Swallow provider errors here; frontend still sees last known status
            }
        }

        return Response::success(
            [__('KYC status fetched successfully')],
            [
                'instructions'      => $instructions,
                'status'            => $user->kyc_verified,
                'youverify_status'  => $kycData->youverify_status ?? null,
                'youverify_reference' => $kycData->youverify_reference ?? null,
            ],
            200
        );
    }
    /**
     * Method for check pin.
     * @param Illuminate\Http\Request $request
     */
    public function pinCheck(Request $request){
        $validator      = Validator::make($request->all(),[
            'pin'       => 'required'
        ]);
        if($validator->fails()) return Response::error($validator->errors()->all(),[]);
        $validated = $validator->validate();

        $user   = auth()->user();
        if($user->pin_code == $validated['pin']){
            return Response::success([__('Pin matched successfully.')],[],200);
        }else{
            return Response::error([__('Your entered pin does not matched.')],[],400);
        }
    }

    /**
     * Method for check login pin.
     * @param Illuminate\Http\Request $request
     */
    public function checkLoginPin(Request $request){
        $validator      = Validator::make($request->all(),[
            'login_pin' => 'required'
        ]);
        if($validator->fails()) return Response::error($validator->errors()->all(),[]);
        $validated = $validator->validate();

        $user   = auth()->user();
        if($user->login_pin == $validated['login_pin']){
            return Response::success([__('Login pin matched successfully.')],[],200);
        }else{
            return Response::error([__('Your entered login pin does not matched.')],[],400);
        }
    }

    /**
     * Method for update login pin.
     * @param Illuminate\Http\Request $request
     */
    public function updateLoginPin(Request $request){
        $validator      = Validator::make($request->all(),[
            'login_pin' => 'required|digits:6'
        ]);
        if($validator->fails()) return Response::error($validator->errors()->all(),[]);
        $validated = $validator->validate();

        $user   = auth()->user();
        try{
            $user->update([
                'login_pin' => $validated['login_pin'],
            ]);
        }catch(Exception $e){
            return Response::error([__('Something went wrong! Please try again.')],[],500);
        }

        return Response::success([__('Login pin updated successfully.')],[],200);
    }

}
