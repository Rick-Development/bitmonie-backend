<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\User\SettingController;
use App\Http\Controllers\Api\YouVerifyWebhookController;

// Settings
Route::controller(SettingController::class)->prefix("settings")->group(function(){
    Route::get("basic-settings","basicSettings");
    Route::get("splash-screen","splashScreen");
    Route::get("onboard-screens","onboardScreens");
    Route::get("languages","getLanguages")->withoutMiddleware('system.maintenance.api');
    Route::get('country-list','countryList');
    
});

// YouVerify webhook endpoint (callback URL for provider)
Route::post('webhook/youverify', [YouVerifyWebhookController::class, 'handle'])
    ->name('webhook.youverify');