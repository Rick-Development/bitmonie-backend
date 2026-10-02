<?php

namespace App\Http\Controllers\Api\V1\User\Auth;

use Exception;
use App\Models\User;
use Illuminate\Http\Request;
use App\Constants\GlobalConst;
use App\Http\Helpers\Response;
use App\Models\VirtualAccounts;
use App\Services\QuidaxService;
use App\Models\UserAuthorization;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Traits\User\RegisteredUsers;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Auth\Events\Registered;
use App\Http\Resources\User\UserResource;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use App\Providers\Admin\BasicSettingsProvider;
use Illuminate\Foundation\Auth\RegistersUsers;
use App\Notifications\User\Auth\SendAuthorizationCode;
use App\Http\Helpers\Payscribe\PayscribeCustomersHelper;
use App\Http\Controllers\Api\SafeHeavenIdentityCheckController;
use App\Http\Helpers\Payscribe\Collections\NGNVirtualAccountsHelper;

class RegisterController extends Controller
{
    use RegistersUsers, RegisteredUsers;

    protected $basic_settings;
    protected $quidax;

    public function __construct(QuidaxService $quidax, private SafeHeavenIdentityCheckController $identityCheckController, private PayscribeCustomersHelper $payscribeCustomersHelper, private NGNVirtualAccountsHelper $nGNVirtualAccountsHelper)
    {
        $this->quidax = $quidax;
        $this->basic_settings = BasicSettingsProvider::get();
        $this->middleware(function ($request, $next) {
            if ($this->basic_settings->user_registration == false)
                return Response::error([__("Currently user registration is not available")], [], 400);
            return $next($request);
        });
    }

    /**
     * Handle a registration request for the application.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function register(Request $request)
    {
        $validator = $this->validator($request->all());
        if ($validator->fails()) {
            return Response::error($validator->errors()->all(), []);
        }

        $validated = $validator->validate();
        $basic_settings = $this->basic_settings;

        $validated['email_verified'] = ($basic_settings->email_verification == true) ? false : true;
        $validated['sms_verified'] = ($basic_settings->sms_verification == true) ? false : true;
        $validated['kyc_verified'] = ($basic_settings->kyc_verification == true) ? false : true;
        $validated['password'] = Hash::make($validated['password']);

        // Use provided username or auto-generate one
        if (empty($validated['username'])) {
            $validated['username'] = make_username($validated['firstname'], $validated['lastname']);
        }

        if (User::where("username", $validated['username'])->exists())
            return Response::error([__('User already exists!')], [], 400);

        $validated['account_no'] = null;

        $validated['address'] = [
            'country' => $validated['country'] ?? "",
        ];

        $validated['account_type'] = $validated['account_type'] ?? "";
        $validated['company_name'] = $validated['company_name'] ?? "";
        $providerWarnings = [];

        try {
            try {
                $quidax_response = $this->quidax->createSubAccount([
                    'email' => $validated['email'],
                    'first_name' => $validated['firstname'],
                    'last_name' => $validated['lastname'],
                ]);

                if (!isset($quidax_response['data'])) {
                    throw new \Exception("Invalid Quidax response");
                }

                $quidax_data = $quidax_response['data'];
                $validated['quidax_id'] = $quidax_data['id'] ?? null;
                $validated['quidax_sn'] = $quidax_data['sn'] ?? null;
                $validated['quidax_display_name'] = $quidax_data['display_name'] ?? null;
                $validated['quidax_reference'] = $quidax_data['reference'] ?? null;
            } catch (\Exception $e) {
                $providerWarnings['quidax'] = $e->getMessage();
                Log::warning('Registration continued without Quidax provisioning', [
                    'email' => $validated['email'],
                    'error' => $e->getMessage(),
                ]);
            }

            unset($validated['referral_code']);
            event(new Registered($user = $this->create($validated)));

            if (!empty($request->referral_code)) {
                try {
                    app(\App\Services\ReferralService::class)->trackReferral($user, $request->referral_code);
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error("Failed to track referral API: " . $e->getMessage());
                }
            }

            $data = [
                'first_name' => $validated['firstname'],
                'last_name' => $validated['lastname'],
                'email' => $validated['email'],
                'phone' => $validated['mobile'],
                'country' => $validated['country'] ?? null,
            ];
            try {
                $this->createCustomer($data);
            } catch (\Exception $e) {
                $providerWarnings['payscribe'] = $e->getMessage();
                Log::warning('Registration continued without Payscribe customer provisioning', [
                    'user_id' => $user->id,
                    'email' => $validated['email'],
                    'error' => $e->getMessage(),
                ]);
            }

            // $new_user = User::where('email', $validated['email'])->first();
            // $ngnAccountData = [
            //     "account_type" => "static",
            //     "currency" => "NGN",
            //     "customer_id" => $new_user->payscribe_customer_id,
            //     "bank" => "9psb",
            // ];
            // $this->create_parmenent_virtual_account($ngnAccountData);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Registration failed! Please try again.',
                'error' => $e->getMessage(), // log internally
            ], 500);
        }


        // get user with all information
        try {
            $user = User::find($user->id);
        } catch (Exception $e) {
            return Response::error([__('Failed to fetch user information. Please try again')], [], 500);
        }

        try {
            $token = $user->createToken("auth_token")->accessToken;
        } catch (Exception $e) {
            return Response::error([__('Failed to generate user token! Please try again')], [], 500);
        }
        if ($basic_settings->email_verification == true) {
            $auth_token = generate_unique_string("user_authorizations", "token", 200);
            $data = [
                'user_id' => $user->id,
                'code' => generate_random_code(),
                'token' => $auth_token,
                'created_at' => now(),
            ];
            DB::beginTransaction();
            try {
                UserAuthorization::where("user_id", $user->id)->delete();
                DB::table("user_authorizations")->insert($data);
                try {
                    $user->notify(new SendAuthorizationCode((object) $data));
                } catch (Exception $e) {
                }
                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                $error = ['Something went worng! Please try again.'];
                return Response::error($error);
            }
            $status = false;
        } else {
            $status = true;
            $auth_token = '';
        }
        if ($basic_settings->email_verification == 1 && $basic_settings->email_notification == 1) {
            $message = ['Please check email and verify your account'];
        } else {
            $message = ['Registration successful'];
        }


        $data = [
            'email_verification' => $basic_settings->email_verification,
            'kyc_verification' => $basic_settings->kyc_verification,
            'token' => $token,
            'image_path' => get_files_public_path('user-profile'),
            'default_image' => get_files_public_path('default'),
            'user_info' => new UserResource($user),
            'provider_warnings' => $providerWarnings,
            'authorization' => [
                'status' => $status,
                'token' => $auth_token,
            ],
        ];
        return Response::success($message, $data);
    }
    /**
     * Get a validator for an incoming registration request.
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    public function validator(array $data)
    {

        $basic_settings = $this->basic_settings;
        $password_rule = "required|confirmed|string|min:6";
        if ($basic_settings->secure_password) {
            $password_rule = ["required", "confirmed", Password::min(8)->letters()->mixedCase()->numbers()->symbols()->uncompromised()];
        }

        $agree_policy = $this->basic_settings->agree_policy == 1 ? 'required|in:on' : 'nullable';

        return Validator::make($data, [
            // 'account_type'      => 'required|in:personal,business',
            'firstname' => 'required|string|max:60',
            'lastname' => 'required|string|max:60',
            'username' => 'nullable|string|min:3|max:30|alpha_dash|unique:users,username',
            'email' => 'required|string|email|max:150|unique:users,email',
            'country' => 'required|string|max:50',
            // 'company_name'      => "required_if:account_type," . GlobalConst::BUSINESS_ACCOUNT,
            'password' => $password_rule,
            'agree' => $agree_policy,
            'mobile' => 'required|unique:users,mobile',
            'referral_code' => 'nullable|string|max:20'
        ]);
    }

    /**
     * Get the guard to be used during registration.
     *
     * @return \Illuminate\Contracts\Auth\StatefulGuard
     */
    protected function guard()
    {
        return Auth::guard("api");
    }

    /**
     * Create a new user instance after a valid registration.
     *
     * @param  array  $data
     * @return \App\Models\User
     */
    protected function create(array $data)
    {
        $user = User::create($data);
        // dd($user);
        $this->create_user_wallet($user);
        return $user;
    }

    public function createCustomer($newUser): JsonResponse
    {
        $user = User::where('email', $newUser['email'])->firstOrFail();
        // dd($user->firstname);

        $data = $newUser;

        $response = $this->payscribeCustomersHelper->createUser($data);
        // dd($response);
        if ($response && ($response['status'] ?? false) == true) {
            $details = $response['message']['details'] ?? [];

            $user->payscribe_customer_id = $details['customer_id'] ?? $user->payscribe_customer_id;
            $user->payscribe_tier = $details['tier'] ?? $user->payscribe_tier ?? 'tier_0';
            $user->payscribe_customer_phone = $details['phone'] ?? $newUser['phone'] ?? $user->payscribe_customer_phone;
            $user->payscribe_customer_country = $details['country'] ?? $newUser['country'] ?? $user->payscribe_customer_country;

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

    public function create_parmenent_virtual_account($data)
    {
        $user = User::where('payscribe_customer_id', $data['customer_id'])->first();

        $response = $this->nGNVirtualAccountsHelper->createVirtualAccount($data);
        // dd($response);

        if ($response && $response['status'] == true) {
            $user->account_no = $response['message']['details']['account'][0]['account_number'];
            $user->save();

            VirtualAccounts::create([
                    'user_id' => $user->id,
                    'customer_id' => $response['message']['details']['customer']['id'],
                    'customer' => $response['message']['details']['customer']['name'],
                    'account_id' => $response['message']['details']['account'][0]['id'],
                    'account_number' => $response['message']['details']['account'][0]['account_number'],
                    'account_name' => $response['message']['details']['account'][0]['account_name'],
                    'bank_name' => $response['message']['details']['account'][0]['bank_name'],
                    'bank_code' => $response['message']['details']['account'][0]['bank_code'],
                    'currency' => $response['message']['details']['account'][0]['currency'],
                    'account_type' => $response['message']['details']['account'][0]['account_type'],
                    'status' => $response['message']['details']['status'],
                ]
            );
        }

        return response()->json($response, $response['status_code']);
    }

    public function create_user_wallet($new_user_data)
    {
        \App\Models\UserWallet::firstOrCreate(
            [
                'user_id' => $new_user_data['id'],
                'currency_code' => 'NGN',
            ],
            [
                'balance' => '0.00000000',
                'status' => true,
                'currency_id' => '1',
            ]
        );
    }

}
