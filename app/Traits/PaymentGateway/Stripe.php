<?php
namespace App\Traits\PaymentGateway;

use Exception;
use Stripe\StripeClient;
use Illuminate\Support\Str;
use App\Models\TemporaryData;
use App\Http\Helpers\PaymentGateway;
use App\Constants\PaymentGatewayConst;

trait Stripe {

    private $stripe_gateway_credentials;
    private $request_credentials;

    public function stripeInit($output = null) {
        if(!$output) $output = $this->output;
        return $this->createStripeCheckout($output);
    }

    public function createStripeCheckout($output) {

        $request_credentials = $this->getStripeRequestCredentials($output);
        $stripe_client = new StripeClient($request_credentials->token);

        $temp_record_token = generate_unique_string('temporary_datas','identifier',60);
        $this->setUrlParams("token=" . $temp_record_token); // set Parameter to URL for identifying when return success/cancel
        $redirection = $this->getRedirection();
        $url_parameter = $this->getUrlParams();

        $user = auth()->guard(get_auth_guard())->user();

        try{
            $checkout = $stripe_client->checkout->sessions->create([
                'mode'              => 'payment',
                'success_url'       => $this->setGatewayRoute($redirection['return_url'],PaymentGatewayConst::STRIPE,$url_parameter),
                'cancel_url'        => $this->setGatewayRoute($redirection['cancel_url'],PaymentGatewayConst::STRIPE,$url_parameter),
                'customer_email'    => $user->email,
                'line_items'        => [
                    [
                        'price_data'    => [
                            'product_data'      => [
                                'name'          => "Add Money",
                                'description'   => "Add Money To Wallet (" . $output['wallet']->currency->code . "). Payment Currency " . $output['currency']->currency_code,
                                'images'        => [
                                    [
                                        get_logo()
                                    ]
                                ]
                            ],
                            'unit_amount_decimal'   => get_amount(($output['amount']->total_amount * 100),null,2), // as per stripe policy,
                            'currency'              => $output['currency']->currency_code,
                        ],
                        'quantity'                  => 1,
                    ]
                ],
            ]);

            $response_array = json_decode(json_encode($checkout->getLastResponse()->json), true);

            $this->stripeJunkInsert($response_array, $temp_record_token);
        }catch(Exception $e) {
            throw new Exception($e->getMessage());
        }

        $redirect_url = $response_array['url'] ?? null;
        if(!$redirect_url) throw new Exception("Something went wrong! Please try again");

        if(request()->expectsJson()) { // API Response
            $this->output['redirection_response']   = $response_array;
            $this->output['redirect_links']         = [];
            $this->output['redirect_url']           = $redirect_url;
            return $this->get();
        }

        return redirect()->away($response_array['url']);
    }

    public function stripeJunkInsert($response, $temp_identifier) {
        $output = $this->output;

        $data = [
            'gateway'       => $output['gateway']->id,
            'currency'      => $output['currency']->id,
            'amount'        => json_decode(json_encode($output['amount']),true),
            'response'      => $response,
            'wallet_table'  => $output['wallet']->getTable(),
            'wallet_id'     => $output['wallet']->id,
            'creator_table' => auth()->guard(get_auth_guard())->user()->getTable(),
            'creator_id'    => auth()->guard(get_auth_guard())->user()->id,
            'creator_guard' => get_auth_guard(),
        ];

        return TemporaryData::create([
            'type'          => PaymentGatewayConst::TYPEADDMONEY,
            'identifier'    => $temp_identifier,
            'data'          => $data,
        ]);
    }

    public function getStripeCredentials($output)
    {
        $gateway = $output['gateway'] ?? null;
        if(!$gateway) throw new Exception("Payment gateway not available");

        $test_publishable_key_sample = ['test publishable','test publishable key','test public key','sandbox public key', 'test public','public key test','public test', 'stripe test public key','stripe test sandbox key'];
        $test_secret_key_sample = ['test secret','test secret key','test private','test private key','test live key','test production key','test production','test live','stripe test secret key','stripe test production key'];

        $live_publishable_key_sample    = ['live publishable','live publishable key','live public key','live public key', 'live public','public key live','public live', 'stripe live public key','stripe live sandbox key'];
        $live_secret_key_sample         = ['live secret','live secret key','live private','live private key','live live key','live production key','live production','live live','stripe live secret key','stripe live production key'];

        $test_publishable_key    = PaymentGateway::getValueFromGatewayCredentials($gateway,$test_publishable_key_sample);
        $test_secret_key         = PaymentGateway::getValueFromGatewayCredentials($gateway,$test_secret_key_sample);

        $live_publishable_key    = PaymentGateway::getValueFromGatewayCredentials($gateway,$live_publishable_key_sample);
        $live_secret_key         = PaymentGateway::getValueFromGatewayCredentials($gateway,$live_secret_key_sample);

        $mode = $gateway->env;

        $gateway_register_mode = [
            PaymentGatewayConst::ENV_SANDBOX => PaymentGatewayConst::ENV_SANDBOX,
            PaymentGatewayConst::ENV_PRODUCTION => PaymentGatewayConst::ENV_PRODUCTION,
        ];

        if(array_key_exists($mode,$gateway_register_mode)) {
            $mode = $gateway_register_mode[$mode];
        }else {
            $mode = PaymentGatewayConst::ENV_SANDBOX;
        }

        $credentials = (object) [
            'test_publishable_key'          => $test_publishable_key,
            'test_secret_key'               => $test_secret_key,
            'live_publishable_key'          => $live_publishable_key,
            'live_secret_key'               => $live_secret_key,
            'mode'                          => $mode
        ];

        $this->stripe_gateway_credentials = $credentials;

        return $credentials;
    }

    public function getStripeRequestCredentials($output = null)
    {
        if(!$this->stripe_gateway_credentials) $this->getStripeCredentials($output);
        $credentials = $this->stripe_gateway_credentials;
        if(!$output) $output = $this->output;

        $request_credentials = [];
        if($output['gateway']->env == PaymentGatewayConst::ENV_PRODUCTION) {
            $request_credentials['token']   = $credentials->live_secret_key;
        }else {
            $request_credentials['token']   = $credentials->test_secret_key;
        }

        $this->request_credentials = (object) $request_credentials;
        return (object) $request_credentials;
    }

    public function stripeSuccess($output) {
        $output['capture']      = $output['tempData']['data']->response ?? "";
        // need to insert new transaction in database
        try{
            $this->createTransaction($output);
        }catch(Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    public function isStripe($gateway)
    {
        $search_keyword = ['stripe','stripe gateway','gateway stripe','stripe payment gateway'];
        $gateway_name = $gateway->name;

        $search_text = Str::lower($gateway_name);
        $search_text = preg_replace("/[^A-Za-z0-9]/","",$search_text);
        foreach($search_keyword as $keyword) {
            $keyword = Str::lower($keyword);
            $keyword = preg_replace("/[^A-Za-z0-9]/","",$keyword);
            if($keyword == $search_text) {
                return true;
                break;
            }
        }
        return false;
    }

}
