<?php
namespace App\Traits\PaymentGateway;

use Exception;
use Illuminate\Support\Str;
use App\Http\Helpers\PaymentGateway;
use App\Constants\PaymentGatewayConst;


trait Authorize {

    private $authorize_gateway_credentials;
    private $request_credentials;

    public function authorizeDotNetInit($output) {
        if(!$output) $output = $this->output;

        $this->getAuthorizeRequestCredentials($output);

        try{
            // create link for btn pay
            return $this->authorizeCreateLinkForBtnPay($output);
        }catch(Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    public function getAuthorizeRequestCredentials($output = null)
    {
        if(!$this->authorize_gateway_credentials) $this->getAuthorizeCredentials($output);
        $credentials = $this->authorize_gateway_credentials;
        if(!$output) $output = $this->output;

        $request_credentials = [];
        $request_credentials['public_key']      = $credentials->public_key;
        $request_credentials['transaction_key'] = $credentials->transaction_key;
        $request_credentials['login_id']        = $credentials->login_id;
        $request_credentials['sandbox_url']     = $credentials->sandbox_url;
        $request_credentials['live_url']        = $credentials->live_url;
        $request_credentials['mode']            = $credentials->mode;

        $this->request_credentials = (object) $request_credentials;
        return (object) $request_credentials;
    }

    public function getAuthorizeCredentials($output)
    {
        $gateway = $output['gateway'] ?? null;
        if(!$gateway) throw new Exception("Payment gateway not available");

        $public_key_sample      = ['public key', 'public-key', 'public_key'];
        $transaction_key_sample = ['transaction key','transaction-key','transaction_key'];
        $login_id_sample        = ['login id','login_id','login-id'];
        $sandbox_url_sample     = ['sandbox url','sandbox-url','sandbox_url'];
        $live_url_sample        = ['live url','live-url','live_url'];

        $public_key      = PaymentGateway::getValueFromGatewayCredentials($gateway,$public_key_sample);
        $transaction_key = PaymentGateway::getValueFromGatewayCredentials($gateway,$transaction_key_sample);
        $login_id        = PaymentGateway::getValueFromGatewayCredentials($gateway,$login_id_sample);
        $sandbox_url     = PaymentGateway::getValueFromGatewayCredentials($gateway,$sandbox_url_sample);
        $live_url        = PaymentGateway::getValueFromGatewayCredentials($gateway,$live_url_sample);

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
            'public_key'      => $public_key,
            'transaction_key' => $transaction_key,
            'login_id'        => $login_id,
            'sandbox_url'     => $sandbox_url,
            'live_url'        => $live_url,
            'mode'            => $mode
        ];

        $this->authorize_gateway_credentials = $credentials;

        return $credentials;
    }

    /**
     * Create Link for Button Pay (JS Checkout)
     */
    public function authorizeCreateLinkForBtnPay($output)
    {
        $temp_record_token = generate_unique_string('temporary_datas','identifier',35);
        $this->razorPayJunkInsert($temp_record_token); // create temporary information

        $btn_link = $this->generateLinkForBtnPay($temp_record_token, PaymentGatewayConst::AUTHORIZE_DOT_NET);

        if(request()->expectsJson()) {
            $this->output['redirection_response']   = [];
            $this->output['redirect_links']         = [];
            $this->output['redirect_url']           = $btn_link;
            return $this->get();
        }

        return redirect($btn_link);
    }

    // public function razorPayJunkInsert($temp_token)
    // {
    //     $output = $this->output;

    //     $data = [
    //         'gateway'       => $output['gateway']->id,
    //         'currency'      => $output['currency']->id,
    //         'amount'        => json_decode(json_encode($output['amount']),true),
    //         'credentials'   => $this->request_credentials,
    //         'wallet_table'  => $output['wallet']->getTable(),
    //         'wallet_id'     => $output['wallet']->id,
    //         'creator_table' => auth()->guard(get_auth_guard())->user()->getTable(),
    //         'creator_id'    => auth()->guard(get_auth_guard())->user()->id,
    //         'creator_guard' => get_auth_guard()
    //     ];

    //     return TemporaryData::create([
    //         'type'          => PaymentGatewayConst::TYPEADDMONEY,
    //         'identifier'    => $temp_token,
    //         'data'          => $data,
    //     ]);
    // }


    /**
     * Button Pay page redirection with necessary data
     */
    public function authorizedotnetBtnPay($temp_data)
    {
        $data = $temp_data->data;
        $output = $this->output;

        $request_credentials  = $this->getAuthorizeRequestCredentials($output);
        $output['public_key'] = $request_credentials->public_key;
        $output['login_id']   = $request_credentials->login_id;
        $output['user']       = auth()->guard(get_auth_guard())->user();

        return view('payment-gateway.btn-pay.authorize-do-net', compact('output'));
    }

    public function isAuthorizeDotNet($gateway)
    {
        $search_keyword = ['authorizedotnet','authorize dot net','authorize dot net gateway', 'gateway authorize dot net'];
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

    /**
     * Razorpay Success Response
     */
    public function authorizedotnetSuccess($output)
    {
        $output['capture'] = $output['tempData']['data']->capture ?? [];
        try{
            $this->createTransaction($output);
        }catch(Exception $e) {
            throw new Exception($e->getMessage());
        }

    }

}
