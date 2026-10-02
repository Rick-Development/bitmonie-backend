<?php

namespace App\Http\Controllers\Api\V1\User;

use Exception;
use App\Constants\GlobalConst;
use App\Http\Helpers\Response;
use App\Models\Admin\Language;
use App\Models\Admin\SetupKyc;
use App\Models\Admin\UsefulLink;
use App\Models\Admin\AppSettings;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Route;
use App\Models\Admin\AppOnboardScreens;
use App\Providers\Admin\CurrencyProvider;
use App\Providers\Admin\BasicSettingsProvider;

class SettingController extends Controller
{
    /**
     * Method for basic settings data
     */
    public function basicSettings() {
        $basic_settings = BasicSettingsProvider::get()->only(['id','site_name','site_title','timezone','site_logo','site_logo_dark','site_fav','site_fav_dark','email_verification','agree_policy','base_color','user_registration']);

        $user_kyc_settings = SetupKyc::UserKyc()->first() ?? false;
        if($user_kyc_settings != false) {
            $user_kyc_settings = $user_kyc_settings->status;
        }

        $basic_settings['user_kyc_status'] =  $user_kyc_settings;

        $languages          = Language::select(['id','name','code','status'])->get();

        $app_settings       = AppSettings::select('splash_screen_image as image','version')->first();
        $onboard_screens    = AppOnboardScreens::orderByDesc('id')->where('status',1)->get()->map(function($data){
            $app_local = get_default_language_code();
            return[
                'id'            => $data->id,
                'heading'       => $data->heading->language->$app_local->heading ?? '',
                'title'         => $data->title->language->$app_local->title ?? '',
                'details'       => $data->details->language->$app_local->details ?? '',
                'image'         => $data->image,
                'status'        => $data->status,
                'created_at'    => $data->created_at,
                'updated_at'    => $data->updated_at,
            ];

        });

        $base_cur           = CurrencyProvider::default()->first();
        $base_cur->makeHidden(['admin_id','country','name','created_at','updated_at','type','flag','sender','receiver','default','status','editData']);

        $app_image_paths        = [
            'base_url'          => url("/"),
            'path_location'     => files_asset_path_basename("app-images"),
            'default_image'     => files_asset_path_basename("default"),
        ];

        return Response::success([__("Basic settings fetch successfully!")],[
            'basic_settings'        => $basic_settings,
            'base_cur'              => $base_cur,
            'web_links'             => [
                'privacy-policy'    => setRoute('frontend.useful.links',UsefulLink::where('type',GlobalConst::USEFUL_LINK_PRIVACY_POLICY)->first()?->slug),
                'about-us'          => Route::has('frontend.about') ? route('frontend.about') : url('/'),
                'contact-us'        => Route::has('frontend.contact') ? route('frontend.contact') : url('/'),
            ],
            'countries'         => get_all_countries(['id','name','mobile_code']),
            'languages'         => $languages,
            'splash_screen'     => $app_settings,
            'onboard_screens'   => $onboard_screens,
            'image_paths'       => [
                'base_path'     => url("/"),
                'path_location' => files_asset_path_basename("image-assets"),
                'default_image' => files_asset_path_basename("default"),
            ],
            'app_image_paths'   => $app_image_paths,
        ],200);
    }
    /**
     * Method for splash screen data
     */
    public function splashScreen() {
        $app_settings = AppSettings::select('splash_screen_image as image','version')->first();

        $image_paths = [
            'base_url'          => url("/"),
            'path_location'     => files_asset_path_basename("app-images"),
            'default_image'     => files_asset_path_basename("default"),
        ];

        return Response::success([__('Splash screen data fetch successfully!')],[
            'splash_screen' => $app_settings,
            'image_paths'   => $image_paths,
        ],200);
    }
    /**
     * Method for onboard screen data
     */
    public function onboardScreens() {
        $onboard_screens = AppOnboardScreens::orderByDesc('id')->where('status',1)->get()->map(function($data){
            $app_local = get_default_language_code();
            return[
                'id'            => $data->id,
                'title'         => $data->title->language->$app_local->title ?? '',
                'image'         => $data->image,
                'status'        => $data->status,
                'created_at'    => $data->created_at,
                'updated_at'    => $data->updated_at,
            ];

        });

        $image_paths = [
            'base_url'          => url("/"),
            'path_location'     => files_asset_path_basename("app-images"),
            'default_image'     => files_asset_path_basename("default"),
        ];

        return Response::success([__('Onboard screen data fetch successfully!')],[
            'onboard_screens'   => $onboard_screens,
            'image_paths'       => $image_paths,
        ],200);
    }
    /**
     * Method for get the languages
     */
    public function getLanguages() {
        try{
            $api_languages = get_api_languages();
        }catch(Exception $e) {
            return Response::error([$e->getMessage()],[],500);
        }

        return Response::success([__("Language data fetch successfully!")],[
            'languages' => $api_languages,
        ],200);
    }
    /**
     * Method for get all country list
     */
    public function countryList(){
        return Response::success([__('Country List Fetch Successfully!')],[
            'countries'     => get_all_countries(['id','name','mobile_code']),
        ],200);
    }
    
}
