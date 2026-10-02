<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Mail\SendMail;
use App\Models\User;
use App\Rules\PhoneLength;
use App\Traits\ApiResponse;
use App\Traits\Notify;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use App\Http\Helpers\Payscribe\PayscribeCustomersHelper;
use App\Http\Helpers\Payscribe\Collections\NGNVirtualAccountsHelper;
use App\Notifications\User\LoginNotification;

class UserAuthController extends Controller
{
    use ApiResponse,Notify;
    private $bearer = 'eyJ0eXAiOiJKV1QiLCJrZXlJZCI6InZhc19qd3QiLCJhbGciOiJIUzUxMiJ9.eyJjbGllbnRJZCI6IlBLX1RFU1RfMkF1Znc0OXdjaSIsImFjY291bnRObyI6IjEwMTgwMDA2IiwiYXV0aG9yaXRpZXMiOlsiQklMTFNfUEFZTUVOVCIsIlRPUF9VUCJdLCJzdWIiOiJWQVNfVEVTVCIsImlzcyI6Ijlwc2IuY29tLm5nIiwiaWF0IjoxNjgwMDEwNjIyLCJqdGkiOiIyMzMzZWQ2MS02OTEyLTRkMzItYjVjZi1jYTkzNjFhZGJjY2IiLCJleHAiOjE2ODAwMTc4MjJ9.h9kb-ii-RZ-p9TjCbfhna8hAMbAfFTufCOhsu9Peh6MrZ28a2UuTr3vuHCDNYu0FPxqPlhLP0kFOo-1rCYB4eQ';

    private function strongPassword()
    {
        return Password::min(8)
            ->letters()
            ->mixedCase()
            ->numbers()
            ->symbols()
            ->uncompromised();
    }


    public function register(Request $request){
        $basic = basicControl();
        $phoneCode = $request->input('phone_code');
        $registerRules = [
            'firstname' => 'required|string',
            'lastname' => 'required|string',
            'username' => 'required|string|alpha_dash|min:5|unique:users,username',
            'email' => 'required|string|email|unique:users,email',
            'password' => $basic->strong_password == 0 ?
                ['required', 'confirmed', 'min:6'] :
                ['required', 'confirmed', $this->strongPassword()],
            'password_confirmation' => 'required| min:6',
            'phone_code' => 'required|string',
            'phone' => ['required', 'numeric', 'unique:users,phone', new PhoneLength($phoneCode)],
            'country' => 'required|string',
            'country_code' => 'required|string',
        ];

        $message = [
            'password.letters' => 'password must be contain letters',
            'password.mixed' => 'password must be contain 1 uppercase and lowercase character',
            'password.symbols' => 'password must be contain symbols',
        ];

        $data = Validator::make($request->all(), $registerRules,$message);
        if ($data->fails()) {
            return response()->json($this->withError(collect($data->errors())->collapse()));
        }

        $customerId=  "";//'b35adc30-af7c-461b-a6d2-fe46150117e6';
        $tier = 0;
        $payscribeCustomer = new PayscribeCustomersHelper();
        $data = [
            "first_name" => $request->firstname,
            "last_name" => $request->lastname,
            "phone" => $request->phone_code.$request->phone,
            "email" => $request->email,
            "country" => "NG"
        ];

        $payscribeResponse = $payscribeCustomer->createUser($data);

        if($payscribeResponse['status'] == true){
          $customerId  =  $payscribeResponse['message']['details']['customer_id'];
          $tier  =  $payscribeResponse['message']['details']['tier'];

        }
        else{
            return response()->json($this->withError($payscribeResponse));
        }

        $sponsorId = null;
        if ($request->has('sponsor')) {
            $sponsor = User::where('username', $request->sponsor)
                ->where('email_verification', 1)
                ->where('sms_verification', 1)
                ->where('status', 1)
                ->first();
            $sponsorId = $sponsor ? $sponsor->id : null;
        }
        $pin = mt_rand(1000, 9999);
        $user =  User::create([
            'firstname' => $request->firstname,
            'lastname' => $request->lastname,
            'username' => $request->username,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'user_pin' => Hash::make($pin),
            'phone' => $request->phone,
            'phone_code' => $request->phone_code,
            'country' => $request->country,
            'country_code' => $request->country_code,
            'email_verification' => ($basic->email_verification) ? 0 : 1,
            'sms_verification' => ($basic->sms_verification) ? 0 : 1,
            'remember_token' => Str::random(10),
            'referral_id' => $sponsorId,
            'payscribe_id' =>  $customerId,
            'tier' => $tier,
        ]);
        // Send welcome email
        // $this->sendWelcomeEmail($user);
        $user = User::where('email', $request->email)
                    ->orWhere('username', $request->username)
                    ->first();
        return response()->json([
            'status' => 'success',
            'message' => $user->createToken('token')->plainTextToken,
            'payscribe_id' => $customerId,
            'defualt_user_pin' => $pin,
            'firstname' => $user->firstname,
            'user' => $user
        ], 200);

    }

    public function login(Request $request)
    {
        try {
            $credentials = $request->only('username', 'password');

            $validator = Validator::make($credentials, [
                'username' => 'required|string',
                'password' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json($this->withError(collect($validator->errors())->collapse()));
            }
            $user = User::where('email', $credentials['username'])
                ->orWhere('username', $credentials['username'])
                ->first();


            if (!$user || !Hash::check($credentials['password'], $user->password)) {
                return response()->json($this->withError('credentials do not match'));
            }
            $data['message'] = 'User logged in successfully.';
            $data['token'] = $user->createToken($user->email)->accessToken;
            // $data['payscribe_id'] = $user->payscribe_id;
            $data['account_number'] = $user->account_number;
            $data['user_pin'] = $user->user_pin;
            $data['firstname'] = $user->firstname;
            $data['user'] = $user;


            Log::info('Triggering LoginNotification for user: ' . $user->id);
            $user->notify(new LoginNotification($request->ip()));
            Log::info('LoginNotification dispatched.');

            //   return response()->json([
            //     'status' => 'success',
            //     'message' => $user->createToken('token')->plainTextToken,
            //     'payscribe_id' => payscribe_id,
            // ], 200);

            return response()->json($this->withSuccess($data));
        }catch (\Exception $e){
            return response()->json($this->withError($e->getMessage()));
        }
    }



    public function loginWithPin(Request $request)
    {
        try {
            $credentials = $request->only('username', 'pin');

            $validator = Validator::make($credentials, [
                'username' => 'required | string',
                'pin' => 'required | string',
            ]);
            if ($validator->fails()) {
                return response()->json($this->withError(collect($validator->errors())->collapse()));
            }
            $user = User::where('email', $credentials['username'])
                ->orWhere('username', $credentials['username'])
                ->first();


            // if (!$user || !Hash::check($credentials['pin'], $user->user_pin)) {
            //     return response()->json($this->withError('credentials do not match'));
            // }

            // Check if the user exists and the PIN matches
            if (!$user || !Hash::check($credentials['pin'], $user->user_pin)) {
                return response()->json($this->withError('Invalid PIN or user not found.' ));
            }

            // Generate a token for the user
            $data['message'] = 'User logged in successfully.';
            $data['token'] = $user->createToken($user->email)->plainTextToken;
            $data['account_number'] = $user->account_number;
            $data['user_pin'] = $user->user_pin;
            $data['firstname'] = $user->firstname;
            $data['user'] = $user;

            // Notify the user of the login
            Log::info('Triggering LoginNotification (PIN) for user: ' . $user->id);
            $user->notify(new LoginNotification($request->ip()));
            Log::info('LoginNotification (PIN) dispatched.');

            return response()->json($this->withSuccess($data));
        } catch (\Exception $e) {
            return response()->json($this->withError($e->getMessage()));
        }
    }


    public function logout()
    {
        auth()->user()->tokens()->delete();
        return response()->json($this->withSuccess('User is logged out successfully'));
    }

    public function getEmailForRecoverPass(Request $request)
    {
        $validateUser = Validator::make($request->all(),
            [
                'email' => 'required|email',
            ]);

        if ($validateUser->fails()) {
            return response()->json($this->withError(collect($validateUser->errors())->collapse()));
        }

        try {
            $user = User::where('email', $request->email)->first();
            if (!$user) {
                return response()->json($this->withError('Email does not exit on record'));
            }

            $code = rand(10000, 99999);
            $data['email'] = $request->email;
            $data['message'] = 'OTP has been send';
            $user->verify_code = $code;
            $user->save();

            $basic = basicControl();
            $message = 'Your Password Recovery Code is ' . $code;
            $email_from = $basic->sender_email;
            @Mail::to($request->email)->send(new SendMail($email_from, "Recovery Code", $message));

            return response()->json($this->withSuccess($data));
        } catch (\Exception $e) {
            return response()->json($this->withError($e->getMessage()));
        }
    }


    public function getCodeForRecoverPass(Request $request)
    {
        $validateUser = Validator::make($request->all(),
            [
                'code' => 'required',
                'email' => 'required|email',
            ]);

        if ($validateUser->fails()) {
            return response()->json($this->withError(collect($validateUser->errors())->collapse()));
        }

        try {
            $user = User::where('email', $request->email)->first();
            if (!$user) {
                return response()->json($this->withError('Email does not exit on record'));
            }

            if ($user->verify_code == $request->code && $user->updated_at > Carbon::now()->subMinutes(5)) {
                $user->verify_code = null;
                $user->save();
                return response()->json($this->withSuccess('Code Matching'));
            }

            return response()->json($this->withError('Invalid Code'));
        } catch (\Exception $e) {
            return response()->json($this->withError($e->getMessage()));
        }
    }

    public function updatePass(Request $request)
    {
        $basic = basicControl();
        $rules = [
            'email' => 'required|email|exists:users,email',
            'password' => $basic->strong_password == 0 ?
                ['required', 'confirmed', 'min:6'] :
                ['required', 'confirmed', $this->strongPassword()],
            'password_confirmation' => 'required| min:6',
        ];
        $message = [
            'email.exists' => 'Email does not exist on record'
        ];
        $validateUser = Validator::make($request->all(), $rules,$message);
        if ($validateUser->fails()) {
            return response()->json($this->withError(collect($validateUser->errors())->collapse()));
        }
        $user = User::where('email', $request->email)->first();
        $user->password = Hash::make($request->password);
        $user->save();
        return response()->json($this->withSuccess('Password Updated'));
    }

    public function updateUser(Request $request) {
        $data = $request->validate([

        ]);
    }


private function handleCustomerResponse($response)
{
    // Check if the response status is true and details exist
    if (isset($response['original']['status']) && $response['original']['status'] === true) {
        $customerDetails = $response['original']['message']['details'];

        // Extract details
        $customerId = $customerDetails['customer_id'] ?? null;
        $firstName = $customerDetails['first_name'] ?? null;
        $lastName = $customerDetails['last_name'] ?? null;
        $email = $customerDetails['email'] ?? null;
        $phone = $customerDetails['phone'] ?? null;
        $country = $customerDetails['country'] ?? null;
        $tier = $customerDetails['tier'] ?? null;
        $createdAt = $customerDetails['created_at'] ?? null;

        // Return or further process the details
        return response()->json([
            'status' => 'success',
            'customer' => [
                'customer_id' => $customerId,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'phone' => $phone,
                'country' => $country,
                'tier' => $tier,
                'created_at' => $createdAt,
            ],
        ]);
    }

    // Handle the error case
    return response()->json([
        'status' => 'error',
        'message' => $response,
    ]);
}



}
