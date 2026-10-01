<?php

namespace App\Http\Controllers\User\Auth;

use Exception;
use App\Models\User;
use Illuminate\Http\Request;
use App\Constants\GlobalConst;
use App\Http\Controllers\Controller;
use App\Traits\User\RegisteredUsers;
use Illuminate\Support\Facades\Hash;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use App\Providers\Admin\BasicSettingsProvider;
use Illuminate\Foundation\Auth\RegistersUsers;

class RegisterController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Register Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the registration of new users as well as their
    | validation and creation. By default this controller uses a trait to
    | provide this functionality without requiring any additional code.
    |
    */

    use RegistersUsers, RegisteredUsers;

    protected $basic_settings;

    public function __construct()
    {
        $this->basic_settings = BasicSettingsProvider::get();
        
        $this->middleware(function($request, $next) {
           
            if($this->basic_settings->user_registration == false) return redirect()->route('frontend.index');
            return $next($request);
        });
    }

    /**
     * Show the application registration form.
     *
     * @return \Illuminate\View\View
     */
    public function showRegistrationForm() {
        $client_ip = request()->ip() ?? false;
        $user_country = geoip()->getLocation($client_ip)['country'] ?? "";

        $page_title = "User Registration";
        return view('user.auth.register',compact(
            'page_title',
            'user_country'
        ));
    }

    /**
     * Handle a registration request for the application.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function register(Request $request)
    {
        $validated = $this->validator($request->all())->validate();
        $basic_settings             = $this->basic_settings;

        $validated['email_verified'] = ($basic_settings->email_verification == true) ? false : true;
        $validated['sms_verified']   = ($basic_settings->sms_verification == true) ? false : true;
        $validated['kyc_verified']   = ($basic_settings->kyc_verification == true) ? false : true;
        $validated['password']       = Hash::make($validated['password']);
        $validated['username']       = make_username($validated['firstname'],$validated['lastname']);


        $validated['account_no'] = generate_unique_number('users','account_no', 14);

        $validated['address']       = [
            'country'   => $validated['country'] ?? "",
        ];

        $validated['account_type'] = $validated['account_type'] ?? "";
        $validated['company_name'] = $validated['company_name'] ?? "";

        unset($validated['referral_code']);
        event(new Registered($user = $this->create($validated)));
        $this->guard()->login($user);

        return $this->registered($request, $user);
    }


    /**
     * Get a validator for an incoming registration request.
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    public function validator(array $data) {

        $basic_settings = $this->basic_settings;
        $password_rule = "required|confirmed|string|min:6";
        if($basic_settings->secure_password) {
            $password_rule = ["required","confirmed",Password::min(8)->letters()->mixedCase()->numbers()->symbols()->uncompromised()];
        }

        $agree_policy = $this->basic_settings->agree_policy == 1 ? 'required|in:on' : 'nullable';

        return Validator::make($data,[
            'account_type'      => 'required|in:personal,business',
            'firstname'         => 'required|string|max:60',
            'lastname'          => 'required|string|max:60',
            'email'             => 'required|string|email|max:150|unique:users,email',
            'country'           => 'required|string|max:50',
            'company_name'      => "required_if:account_type," . GlobalConst::BUSINESS_ACCOUNT,
            'password'          => $password_rule,
            'agree'             => $agree_policy,
            'referral_code'     => 'nullable|string|max:20',
        ]);
    }


    /**
     * Create a new user instance after a valid registration.
     *
     * @param  array  $data
     * @return \App\Models\User
     */
    protected function create(array $data)
    {
        return User::create($data);
    }


    /**
     * The user has been registered.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  mixed  $user
     * @return mixed
     */
    protected function registered(Request $request, $user)
    {
        try{
            $this->createUserWallets($user);
            if (!empty($request->referral_code)) {
                try {
                    app(\App\Services\ReferralService::class)->trackReferral($user, $request->referral_code);
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error("Failed to track referral: " . $e->getMessage());
                }
            }
        }catch(Exception $e) {
            $this->guard()->logout();
            $user->delete();
            return redirect()->back()->with(['error' => ['Something went wrong! Please try again']]);
        }
        return redirect()->intended(route('user.dashboard'));
    }
}
