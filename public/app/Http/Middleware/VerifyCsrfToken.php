<?php

namespace App\Http\Middleware;

use App\Constants\PaymentGatewayConst;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        'user/username/check',
        'user/check/email',
        'user/add-money/success/response/' . PaymentGatewayConst::SSLCOMMERZ,
        'user/add-money/cancel/response/' . PaymentGatewayConst::SSLCOMMERZ,
        'user/add-money/success/response/' . PaymentGatewayConst::RAZORPAY,
        'user/add-money/cancel/response/' . PaymentGatewayConst::RAZORPAY,
    ];
}
