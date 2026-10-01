<?php
namespace App\Traits\PaymentGateway;

use Exception;
use Illuminate\Support\Str;
use App\Models\TemporaryData;
use App\Http\Helpers\PaymentGateway;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use App\Constants\PaymentGatewayConst;
use App\Http\Controllers\Api\V1\User\AddMoneyController;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;

trait Flutterwave {

    private $flutterwave_gateway_credentials;
    private $request_credentials;
    private $flutterwave_api_base_url = "https://api.flutterwave.com/";
    private $flutterwave_api_v3       = "v3";

    public function flutterwaveInit($output = null) {
        if(!$output) $output = $this->output;
        $request_credentials = $this->getFlutterwaveRequestCredentials($output);
        return $this->createFlutterwavePaymentLink($output, $request_credentials);
    }


    public function registerFlutterwaveEndpoints($endpoint_key = null)
    {
        $endpoints = [
            'create-payment-link'       => $this->flutterwave_api_base_url . $this->flutterwave_api_v3 . "/payments",
        ];

        if($endpoint_key) {
            if(!array_key_exists($endpoint_key, $endpoints)) throw new Exception("Endpoint key [$endpoint_key] not registered! Register it in registerFlutterwaveEndpoints() method");

            return $endpoints[$endpoint_key];
        }

        return $endpoints;
    }

    public function createFlutterwavePaymentLink($output, $request_credentials) {

        $endpoint = $this->registerFlutterwaveEndpoints('create-payment-link');

        $temp_record_token = generate_unique_string('temporary_datas','identifier',60);
        $this->setUrlParams("token=" . $temp_record_token); // set Parameter to URL for identifying when return success/cancel

        $redirection = $this->getRedirection();
        $url_parameter = $this->getUrlParams();

        $user = auth()->guard(get_auth_guard())->user();

        $temp_data = $this->flutterWaveJunkInsert($temp_record_token); // create temporary information

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $request_credentials->token,
        ])->post($endpoint,[
            'tx_ref'        => $temp_record_token,
            'amount'        => $output['amount']->total_amount,
            'currency'      => $output['currency']->currency_code,
            'redirect_url'  => $this->setGatewayRoute($redirection['return_url'],PaymentGatewayConst::FLUTTERWAVE,$url_parameter),
            'customer'      => [
                'email' => $user->email,
                'name'  => $user->firstname ?? "",
            ],
            'customizations'    => [
                'title'     => "Add Money",
                'logo'      => get_fav(),
            ]
        ])->throw(function(Response $response, RequestException $exception) use ($temp_data) {
            $temp_data->delete();
            throw new Exception($exception->getMessage());
        })->json();

        $response_array = json_decode(json_encode($response), true);

        $temp_data_contains = json_decode(json_encode($temp_data->data),true);
        $temp_data_contains['response'] = $response_array;

        $temp_data->update([
            'data'  => $temp_data_contains,
        ]);


        // make api response
        if(request()->expectsJson()) {
            $this->output['redirection_response']   = $response_array;
            $this->output['redirect_links']         = [];
            $this->output['redirect_url']           = $response_array['data']['link'];
            return $this->get();
        }

        return redirect()->away($response_array['data']['link']);
    }

    public function flutterWaveJunkInsert($temp_token) {
        $output = $this->output;

        $data = [
            'gateway'       => $output['gateway']->id,
            'currency'      => $output['currency']->id,
            'amount'        => json_decode(json_encode($output['amount']),true),
            'wallet_table'  => $output['wallet']->getTable(),
            'wallet_id'     => $output['wallet']->id,
            'creator_table' => auth()->guard(get_auth_guard())->user()->getTable(),
            'creator_id'    => auth()->guard(get_auth_guard())->user()->id,
            'creator_guard' => get_auth_guard(),
        ];

        return TemporaryData::create([
            'type'          => PaymentGatewayConst::TYPEADDMONEY,
            'identifier'    => $temp_token,
            'data'          => $data,
        ]);
    }

    public function getFlutterwaveCredentials($output)
    {
        $gateway = $output['gateway'] ?? null;
        if(!$gateway) throw new Exception("Payment gateway not available");

        $public_key_sample = ['public key','test key','sandbox public key','public', 'test public','flutterwave public key', 'flutterwave public'];
        $secret_key_sample = ['secret','secret key','flutterwave secret','flutterwave secret key'];
        $encryption_key_sample    = ['encryption','encryption key','flutterwave encryption','flutterwave encryption key'];

        $public_key    = PaymentGateway::getValueFromGatewayCredentials($gateway,$public_key_sample);
        $secret_key         = PaymentGateway::getValueFromGatewayCredentials($gateway,$secret_key_sample);
        $encryption_key    = PaymentGateway::getValueFromGatewayCredentials($gateway,$encryption_key_sample);

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
            'public_key'                => $public_key,
            'secret_key'                => $secret_key,
            'encryption_key'            => $encryption_key,
            'mode'                      => $mode
        ];

        $this->flutterwave_gateway_credentials = $credentials;

        return $credentials;
    }

    public function getFlutterwaveRequestCredentials($output = null)
    {
        if(!$this->flutterwave_gateway_credentials) $this->getFlutterwaveCredentials($output);
        $credentials = $this->flutterwave_gateway_credentials;
        if(!$output) $output = $this->output;

        $request_credentials = [];
        if($output['gateway']->env == PaymentGatewayConst::ENV_PRODUCTION) {
            $request_credentials['token']   = $credentials->secret_key;
        }else {
            $request_credentials['token']   = $credentials->secret_key;
        }

        $this->request_credentials = (object) $request_credentials;
        return (object) $request_credentials;
    }

    public function isFlutterwave($gateway)
    {
        $search_keyword = ['flutterwave','flutterwave gateway','gateway flutterwave','flutterwave payment gateway'];
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

    public function flutterwaveSuccess($output) {

        $redirect_response = $output['tempData']['data']->callback_data ?? false;
        if($redirect_response == false) {
            throw new Exception("Invalid response");
        }

        if($redirect_response->status == "cancelled") {

            $identifier = $output['tempData']['identifier'];
            $response_array = json_decode(json_encode($redirect_response), true);

            if(isset($response_array['r-source']) && $response_array['r-source'] == PaymentGatewayConst::APP) {
                if($output['type'] == PaymentGatewayConst::TYPEADDMONEY) {
                    return (new AddMoneyController())->cancel(new Request([
                        'token' => $identifier,
                    ]), PaymentGatewayConst::FLUTTERWAVE);
                }
            }

            $this->setUrlParams("token=" . $identifier); // set Parameter to URL for identifying when return success/cancel
            $redirection = $this->getRedirection();
            $url_parameter = $this->getUrlParams();

            $cancel_link = $this->setGatewayRoute($redirection['cancel_url'],PaymentGatewayConst::FLUTTERWAVE,$url_parameter);
            return redirect()->away($cancel_link);
        }

        if($redirect_response->status == "success" || $redirect_response->status == "completed" || $redirect_response->status == "successful" ) {
            $output['capture']      = $output['tempData']['data']->response ?? "";
            try{
                $this->createTransaction($output);
            }catch(Exception $e) {
                throw new Exception($e->getMessage());
            }
        }

    }

}
