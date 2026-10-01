<?php


use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\User\Auth\LoginController;
use App\Http\Controllers\Api\V1\User\Auth\RegisterController;
use App\Http\Controllers\Api\V1\User\Auth\AuthorizationController;
use App\Http\Controllers\Api\V1\User\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\Auth\BiometricController;

// User Auth Routes
Route::middleware(['api.user.auth.guard'])->group(function () {
    Route::controller(RegisterController::class)->group(function () {
        Route::post("register", "register");
    });

    Route::controller(LoginController::class)->group(function () {
        Route::post("login", "login");
        Route::post("login-with-pin", "loginWithPin");
    });

    // Forget password routes
    Route::controller(ForgotPasswordController::class)->prefix("password/forgot")->group(function () {
        Route::post('find/user', 'findUserSendCode');
        Route::post('verify/code', 'verifyCode');
        Route::post('resend/code', 'resendCode');
        Route::post('reset', 'resetPassword');
    });

    // Biometric Auth Routes
    Route::controller(BiometricController::class)->prefix("auth/biometric")->group(function () {
        Route::post('register', 'register')->middleware('auth:api'); // Register device (needs active session)
        Route::post('login', 'login'); // Login with signature (public route)
    });

});

Route::controller(AuthorizationController::class)->prefix("authorize")->middleware(['auth:api'])->group(function () {
    // Mail
    Route::prefix("mail")->group(function () {
        Route::get("send/code", "sendCodeToMail");
        Route::get("resend/code", "resendCodeToMail");
        Route::post("verify/code", "verifyMailCode");
    });
    // Kyc
    Route::prefix("kyc")->group(function () {
        Route::get('input-fields', 'getKycInputFields');
        Route::post('submit', 'KycSubmit');
    });

    // google 2FA
    Route::prefix("google/2fa")->group(function () {
        Route::post('verify', 'verifyGoogle2Fa');
    });

});
