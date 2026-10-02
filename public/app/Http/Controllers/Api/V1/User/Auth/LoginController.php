<?php

namespace App\Http\Controllers\Api\V1\User\Auth;

use Exception;
use App\Models\User;
use Illuminate\Http\Request;
use App\Constants\GlobalConst;
use App\Http\Helpers\Response;
use App\Models\UserAuthorization;
use App\Traits\User\LoggedInUsers;
use Illuminate\Support\Facades\DB;
use App\Models\Admin\BasicSettings;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Http\Resources\User\UserResource;
use Illuminate\Support\Facades\Validator;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use App\Notifications\User\Auth\SendAuthorizationCode;
use App\Http\Controllers\Api\V1\User\Auth\AuthorizationController;
use App\Services\QuidaxService;
use App\Http\Helpers\Payscribe\PayscribeCustomersHelper;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    protected $request_data;

    use AuthenticatesUsers, LoggedInUsers;

    /**
     * Handle a login request to the application.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    /**
     * Handle a PIN-based login request from the mobile app.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function loginWithPin(Request $request)
    {
        $this->request_data = $request;

        $validator = Validator::make($request->all(), [
            'username' => 'required|string',
            'pin'      => 'required|string|digits:4',
        ]);

        if ($validator->fails()) {
            return Response::error($validator->errors()->all(), []);
        }

        $validated = $validator->validate();

        // Resolve user by email or username
        $field = filter_var($validated['username'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        $user  = User::where($field, $validated['username'])->first();

        if (!$user) {
            return Response::error([__("User doesn't exist!")], [], 404);
        }

        if ($user->status != GlobalConst::ACTIVE) {
            return Response::error([__("Your account is temporarily banned. Please contact support.")]);
        }

        // Check if user has set up their PIN (transaction PIN = login PIN)
        if (!$user->pin_status || empty($user->pin_code)) {
            return Response::error([__('You have not set up a PIN yet. Please log in with your password and set up your PIN first.')], [], 400);
        }

        // Validate against pin_code (transaction PIN = login PIN)
        if (!Hash::check($validated['pin'], $user->pin_code)) {
            return Response::error([__('Invalid PIN. Please try again.')]);
        }

        // Ensure Quidax sub-account exists
        if ($user->quidax_id == null || $user->quidax_sn == null) {
            $quidax = new \App\Services\QuidaxService();
            $quidax_response = $quidax->createSubAccount([
                'email'      => preg_replace('/@.+$/', '@' . env('DOMAIN_EXT', 'bitmonie.com'), $user->email),
                'first_name' => $user->firstname,
                'last_name'  => $user->lastname,
            ]);

            if (!isset($quidax_response['data'])) {
                return Response::error([__('Login Failed! Could not verify account. Please try again.')], [], 500);
            }

            $qd = $quidax_response['data'];
            $user->update([
                'quidax_id'           => $qd['id'],
                'quidax_sn'           => $qd['sn'],
                'quidax_display_name' => $qd['display_name'],
                'quidax_reference'    => $qd['reference'],
            ]);
        }

        // Ensure Payscribe customer exists
        if ($user->payscribe_customer_id == null) {
            $this->createCustomer([
                'first_name' => $user->firstname,
                'last_name'  => $user->lastname,
                'email'      => $user->email,
                'phone'      => $user->mobile,
            ]);
        }

        // Generate token
        $token = $user->createToken("auth_token")->accessToken;

        return $this->authenticated($request, $user, $token);
    }

    public function login(Request $request)
    {
        //\Log::info($request->all());
        $this->request_data = $request;

        $validator = Validator::make($request->all(), [
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return Response::error($validator->errors()->all(), []);
        }

        $validated = $validator->validate();
        if (!User::where($this->username(), $validated['username'])->exists()) {
            return Response::error([__('User doesn\'t exists!')], [], 404);
        }

        $user = User::where($this->username(), $validated['username'])->first();
        if (!$user)
            return Response::error([__('User doesn\'t exists')]);

        if (Hash::check($validated['password'], $user->password)) {
            if ($user->status != GlobalConst::ACTIVE)
                return Response::error([__("Your account is temporary banded. Please contact with system admin")]);


            if($user->quidax_id == null || $user->quidax_sn == null){
              // Call Quidax API
            $quidax = new  QuidaxService();
            $quidax_response = $quidax->createSubAccount([
                'email' => preg_replace('/@.+$/', '@' . env('DOMAIN_EXT', 'bitmonie.com'), $user->email),
                'first_name' => $user->firstname,
                'last_name' => $user->lastname,
            ]);

            if (!isset($quidax_response['data'])) {
                throw new \Exception("Invalid Quidax response");
            }

            $quidax_data = $quidax_response['data'];

            $user->update([
                'quidax_id' => $quidax_data['id'],
                'quidax_sn' => $quidax_data['sn'],
                'quidax_display_name' => $quidax_data['display_name'],
                'quidax_reference' => $quidax_data['reference'],
            ]);
        }

        if($user->payscribe_customer_id == null){
            // Call Payscribe API
            $data = [
                'first_name' => $user->firstname,
                'last_name' => $user->lastname,
                'email' => $user->email,
                'phone' => $user->mobile,
            ];
            $this->createCustomer($data);
        }

            // User authenticated
            $token = $user->createToken("auth_token")->accessToken;
            return $this->authenticated($request, $user, $token);
        }

        return Response::error([__('username didn\'t match')]);
    }


        public function createCustomer($newUser)
    {
        $user = User::where('email', $newUser['email'])->firstOrFail();
        // dd($user->firstname);

        $data = $newUser;
        $payscribeCustomersHelper = new PayscribeCustomersHelper();

        $response = $payscribeCustomersHelper->createUser($data);
        \Log::info($response);
        // dd($response);
        if ($response && $response['status'] == true) {
            $user->payscribe_customer_id = $response['message']['details']['customer_id'];
            $user->payscribe_tier = $response['message']['details']['tier'];
            $user->payscribe_customer_phone = $response['message']['details']['phone'];
            $user->payscribe_customer_country = $response['message']['details']['country'];

            $user->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Customer created successfully',
                'data' => [
                    'customer_id' => $user->payscribe_customer_id,
                    'tier' => $user->payscribe_tier,
                    'phone' => $user->payscribe_customer_phone,
                ],
                'payscribe_response' => $response,
            ], 201);
        }

        return response()->json($response, $response['status_code'] ?? 200);
    }

    /**
     * Get the needed authorization credentials from the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    protected function credentials(Request $request)
    {
        $request->merge(['status' => true]);
        $request->merge([$this->username() => $request->credentials]);
        return $request->only($this->username(), 'password', 'status');
    }


    /**
     * Get the login username to be used by the controller.
     *
     * @return string
     */
    public function username()
    {
        $request = $this->request_data->all();
        $credentials = $request['username'];
        if (filter_var($credentials, FILTER_VALIDATE_EMAIL)) {
            return "email";
        }
        return "username";
    }


    /**
     * Get the guard to be used during authentication.
     *
     * @return \Illuminate\Contracts\Auth\StatefulGuard
     */
    protected function guard()
    {
        return Auth::guard("api");
    }


    /**
     * The user has been authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  mixed  $user
     * @return mixed
     */
    protected function authenticated(Request $request, $user, $token)
    {
        $basic_settings = BasicSettings::first();

        // Reset 2FA flag
        $user->update([
            'two_factor_verified' => false,
        ]);

        // Try refreshing wallets
        try {
            $this->refreshUserWallets($user);
        } catch (Exception $e) {
            return Response::error(
                [__('Login Failed! Failed to refresh wallet! Please try again')],
                [],
                500
            );
        }

        // Log the login attempt
        $this->createLoginLog($user);

        // 🔥 FIXED: Only send authorization code if verification is required AND user has NOT verified yet
        if ($basic_settings->email_verification == true && $user->email_verified == false) {
            $auth_token = generate_unique_string("user_authorizations", "token", 200);
            $data = [
                'user_id' => $user->id,
                'code' => generate_random_code(),
                'token' => $auth_token,
                'created_at' => now(),
            ];

            DB::beginTransaction();
            try {
                // Remove old codes for this user
                UserAuthorization::where("user_id", $user->id)->delete();

                // Insert the new code/token
                DB::table("user_authorizations")->insert($data);

                // Try notifying user (fail silently but you may want to log it)
                try {
                    $user->notify(new SendAuthorizationCode((object) $data));
                } catch (Exception $e) {
                    // log the error instead of swallowing silently
                    //\Log::warning("Authorization email failed for user {$user->id}: " . $e->getMessage());
                }

                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                throw new Exception(__("Something went wrong! Please try again"));
            }

            // User still needs to complete verification
            $status = false;
        } else {
            // User is good to go (already verified or verification not required)
            $status = true;
            $auth_token = '';
        }

        // Return the login response
        return Response::success(
            [__('User successfully logged in')],
            [
                'token' => $token,
                'user_info' => UserResource::make($user),
                'authorization' => [
                    'status' => $status,
                    'token' => $auth_token,
                ],
            ],
            200
        );
    }

}