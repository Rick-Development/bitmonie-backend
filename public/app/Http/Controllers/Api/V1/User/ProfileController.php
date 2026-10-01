<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Models\UserWallet;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use App\Http\Helpers\Response;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Http\Resources\User\UserResource;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use App\Providers\Admin\BasicSettingsProvider;

class ProfileController extends Controller
{
    /**
     * Method for get profile password
     */
    public function profileInfo()
    {
        $user = auth()->guard("api")->user();
        $response_data = UserResource::make($user);
        // if($user->pin_status == false){

        $image_paths = [
            'base_url' => url(path: "/"),
            'path_location' => files_asset_path_basename("user-profile"),
            'default_image' => files_asset_path_basename("profile-default"),
        ];
        $kyc_image_paths = [
            'base_url' => url("/"),
            'path_location' => files_asset_path_basename("kyc-files"),
            'default_image' => files_asset_path_basename("default"),
        ];

        $instructions = [
            'kyc_verified' => "0: Default, 1: Approved, 2: Pending, 3:Rejected",
        ];

        return Response::success([__('Profile info fetch successfully!')], [
            'instructions' => $instructions,
            'user_info' => $response_data,
            'image_paths' => $image_paths,
            'kyc_image_paths' => $kyc_image_paths,
            'countries' => get_all_countries(['id', 'name', 'mobile_code']),
        ], 200);
    }
    /**
     * Method for update profile info
     */
    public function profileInfoUpdate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'firstname' => "required|string|max:60",
            'lastname' => "required|string|max:60",
            'country' => "required|string|max:50",
            'phone_code' => "nullable|string|max:20",
            'phone' => "nullable|string|max:20|unique:users,mobile," . Auth::id(),
            'state' => "nullable|string|max:50",
            'city' => "nullable|string|max:50",
            'gender' => "nullable|string|in:Male,Female,Other",
            'birthdate' => "nullable",
            'zip_code' => "nullable|numeric",
            'address' => "nullable|string|max:250",
            'image' => "nullable|image|mimes:jpg,png,svg,webp|max:10240",
        ]);

        if ($validator->fails()) {
            return Response::error($validator->errors()->all(), []);
        }

        $validated = $validator->validate();

        $validated['mobile'] = get_only_numeric_data($validated['phone'] ?? '');
        $validated['mobile_code'] = get_only_numeric_data($validated['phone_code'] ?? '');
        $complete_phone = $validated['mobile_code'] . $validated['mobile'];
        $validated['full_mobile'] = $complete_phone;
        $validated = Arr::except($validated, ['agree', 'phone_code', 'phone']);


        $user = auth()->guard(get_auth_guard())->user();

        $validated['address'] = [
            'country' => $validated['country'] ?? "",
            'state' => $validated['state'] ?? "",
            'city' => $validated['city'] ?? "",
            'zip' => $validated['zip_code'] ?? "",
            'address' => $validated['address'] ?? "",
        ];

        if ($request->hasFile("image")) {
            $image = upload_file($validated['image'], 'junk-files', $user->image);
            $upload_image = upload_files_from_path_dynamic([$image['dev_path']], 'user-profile');
            delete_file($image['dev_path']);
            $validated['image'] = $upload_image;
        }

        try {
            $user->update($validated);
        } catch (Exception $e) {
            return Response::error([__("Something went wrong! Please try again")], [], 400);
        }

        return Response::success([__('Profile successfully updated!')], [], 200);
    }
    /**
     * Method for update profile password
     */
    public function profilePasswordUpdate(Request $request)
    {
        $basic_settings = BasicSettingsProvider::get();
        $password_rule = "required|string|min:6|confirmed";
        if ($basic_settings->secure_password) {
            $password_rule = ["required", Password::min(8)->letters()->mixedCase()->numbers()->symbols()->uncompromised(), "confirmed"];
        }

        $validator = Validator::make($request->all(), [
            'current_password' => "required|string",
            'password' => $password_rule,
        ]);

        if ($validator->fails())
            return Response::error($validator->errors()->all(), []);
        $validated = $validator->validate();

        if (!Hash::check($validated['current_password'], auth()->guard("api")->user()->password)) {
            return Response::error([__('Current password didn\'t match')], [], 400);
        }

        try {
            auth()->guard("api")->user()->update([
                'password' => Hash::make($validated['password']),
            ]);
        } catch (Exception $e) {
            return Response::error([__('Something went wrong! Please try again')], [], 400);
        }

        return Response::success([__('Password successfully updated!')], [], 200);
    }
    /**
     * Method for delete user profile
     */
    public function deleteProfile()
    {
        $user = Auth::guard(get_auth_guard())->user();
        if (!$user) {
            $message = ['success' => ['No user found']];
            return Response::error($message, []);
        }

        try {
            $user->status = 0;
            $user->save();
            $user->delete();

            return Response::success(['User deleted successful'], $user);
        } catch (Exception $e) {
            return Response::error(['Something went wrong, please try again!'], []);
        }
    }

    public function logout(Request $request)
    {
        $user = Auth::guard(get_auth_guard())->user();
        $token = $user->token();
        try {
            $token->revoke();
        } catch (Exception $e) {
            return Response::error([__('Something went wrong! Please try again')], [], 500);
        }
        return Response::success([__('Logout success!')], [], 200);
    }

    public function get_balances()
    {
        $user = auth()->user();

        $get_balance = UserWallet::where('user_id', $user->id)->first();
        return response()->json([
            'status' => true,
            'data' => [
                'balance' => $get_balance
            ]
        ]);
    }
}
