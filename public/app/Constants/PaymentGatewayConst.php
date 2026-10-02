<?php
namespace App\Constants;
use App\Models\UserWallet;
use Illuminate\Support\Str;

class PaymentGatewayConst {

    const AUTOMATIC                 = "AUTOMATIC";
    const MANUAL                    = "MANUAL";
    const ADDMONEY                  = "Add Money";
    const MONEYOUT                  = "Money Out";
    const ACTIVE                    =  true;

    const TYPEADDMONEY                  = "ADD-MONEY";
    const TYPEMONEYOUT                  = "MONEY-OUT";
    const TYPEWITHDRAW                  = "WITHDRAW";
    const TYPECOMMISSION                = "COMMISSION";
    const TYPEBONUS                     = "BONUS";
    const TYPETRANSFERMONEY             = "TRANSFER-MONEY";
    const TYPEMONEYEXCHANGE             = "MONEY-EXCHANGE";
    const TYPEADDSUBTRACTBALANCE        = "ADD-SUBTRACT-BALANCE";
    const TYPEMAKEPAYMENT               = "MAKE-PAYMENT";
    const TYPECAPITALRETURN             = "CAPITAL-RETURN";
    const TYPEREFERBONUS                = "REFER-BONUS";
    const TYPE_OWN_BANK_TRANSFER        = "OWN-BANK-TRANSFER";
    const TYPE_OTHER_BANK_TRANSFER      = "OTHER-BANK-TRANSFER";
    const TYPE_MOBILE_WALLET_TRANSFER   = "MOBILE-WALLET-TRANSFER";
    const SEND_MONEY                    = "SEND-MONEY";
    const TYPEVIRTUALCARD               = "VIRTUAL-CARD";
    const CARDBUY                       = "CARD-BUY";
    const CARDFUND                      = "CARD-FUND";
    const SALARYDISBURSEMENT            = "Salary Disbursement";

    const ENV_SANDBOX               = "SANDBOX";
    const ENV_PRODUCTION            = "PRODUCTION";

    const APP                       = "APP";

    const STATUSSUCCESS             = 1;
    const STATUSPENDING             = 2;
    const STATUSHOLD                = 3;
    const STATUSREJECTED            = 4;
    const STATUSWAITING             = 5;

    const PAYPAL                    = 'paypal';
    const G_PAY                     = 'gpay';
    const COIN_GATE                 = 'coingate';
    const QRPAY                     = 'qrpay';
    const TATUM                     = 'tatum';
    const STRIPE                    = 'stripe';
    const FLUTTERWAVE               = 'flutterwave';
    const SSLCOMMERZ                = 'sslcommerz';
    const RAZORPAY                  = 'razorpay';
    const PERFECT_MONEY             = 'perfect-money';
    const AUTHORIZE_DOT_NET         = 'authorize-dot-net';

    const SEND                      = "SEND";
    const RECEIVED                  = "RECEIVED";
    const PENDING                   = "PENDING";
    const REJECTED                  = "REJECTED";
    const CREATED                   = "CREATED";
    const SUCCESS                   = "SUCCESS";
    const EXPIRED                   = "EXPIRED";

    const FIAT                      = "FIAT";
    const CRYPTO                    = "CRYPTO";
    const CRYPTO_NATIVE             = "CRYPTO_NATIVE";

    const PROJECT_CURRENCY_SINGLE   = "PROJECT_CURRENCY_SINGLE";
    const PROJECT_CURRENCY_MULTIPLE = "PROJECT_CURRENCY_MULTIPLE";

    const ASSET_TYPE_WALLET         = "WALLET";
    const CALLBACK_HANDLE_INTERNAL  = "CALLBACK_HANDLE_INTERNAL";

    const NOT_USED  = "NOT-USED";
    const USED      = "USED";
    const SENT      = "SENT";

    const REDIRECT_USING_HTML_FORM = "REDIRECT_USING_HTML_FORM";

    public static function add_money_slug() {
        return Str::slug(self::ADDMONEY);
    }

    public static function money_out_slug() {
        return Str::slug(self::MONEYOUT);
    }

    public static function register($alias = null) {
        $gateway_alias  = [
            self::PAYPAL            => "paypalInit",
            self::G_PAY             => "gpayInit",
            self::COIN_GATE         => "coinGateInit",
            self::QRPAY             => "qrpayInit",
            self::TATUM             => 'tatumInit',
            self::STRIPE            => 'stripeInit',
            self::FLUTTERWAVE       => 'flutterwaveInit',
            self::SSLCOMMERZ        => 'sslCommerzInit',
            self::RAZORPAY          => 'razorpayInit',
            self::PERFECT_MONEY     => 'perfectMoneyInit',
            self::AUTHORIZE_DOT_NET => 'authorizeDotNetInit'
        ];

        if($alias == null) {
            return $gateway_alias;
        }

        if(array_key_exists($alias,$gateway_alias)) {
            return $gateway_alias[$alias];
        }
        return "init";
    }

    public static function registerWallet() {
        return [
            'web'       => UserWallet::class,
            'api'       => UserWallet::class,
        ];
    }

    public static function apiAuthenticateGuard() {
        return [
            'api'   => 'web',
        ];
    }

    public static function registerRedirection() {
        return [
            'web'       => [
                'return_url'    => 'user.add.money.payment.success',
                'cancel_url'    => 'user.add.money.payment.cancel',
                'callback_url'  => 'user.add.money.payment.callback',
                'redirect_form' => 'user.add.money.payment.redirect.form',
                'btn_pay'       => 'user.add.money.payment.btn.pay',
            ],
            'api'       => [
                'return_url'    => 'api.user.add.money.payment.success',
                'cancel_url'    => 'api.user.add.money.payment.cancel',
                'callback_url'  => 'user.add.money.payment.callback',
                'redirect_form' => 'user.add.money.payment.redirect.form',
                'btn_pay'       => 'api.user.add.money.payment.btn.pay',
            ],
        ];
    }

    public static function registerGatewayRecognization() {
        return [
            'isGpay'            => self::G_PAY,
            'isPaypal'          => self::PAYPAL,
            'isCoinGate'        => self::COIN_GATE,
            'isQrpay'           => self::QRPAY,
            'isTatum'           => self::TATUM,
            'isStripe'          => self::STRIPE,
            'isFlutterwave'     => self::FLUTTERWAVE,
            'isSslCommerz'      => self::SSLCOMMERZ,
            'isRazorpay'        => self::RAZORPAY,
            'isPerfectMoney'    => self::PERFECT_MONEY,
            'isAuthorizeDotNet' => self::AUTHORIZE_DOT_NET,
        ];
    }

}
