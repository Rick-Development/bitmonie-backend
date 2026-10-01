<?php

namespace App\Http\Helpers\Payscribe\Collections;

use App\Http\Helpers\ConnectionHelper;


class NGNVirtualAccountsHelper extends ConnectionHelper{
    

    public function __construct(){
        parent::__construct();
    }

    public function createVirtualAccount($data){
        $url = '/collections/virtual-accounts/create';
        // $data = [
        //     "account_type" => "static",
        //     "currency" => "NGN",
        //     "customer_id" => "5f819910-f9ed-41f8-a0ae-b6a934b41014",
        //     "bank" => ["9psb"]
        // ];
        $response  = $this->post($url,$data);
        return json_decode($response,true);
    }


    public function getVirtualAccountDetails($data){
         $url = "/collections/virtual-accounts/$data";
         $response = $this->get($url);
        return json_decode($response,true);
        
    }

    public function deactivateVirtualAccount($data){
        $url = '/collections/virtual-accounts/deactivate';
          
        //  $data = [
        //      "account" => "5031240100"
        //  ];
        $response = $this->post($url,$data);
        return json_decode($response,true);
    }

    public function activateVirtualAccount($data){
        $url =  '/collections/virtual-accounts/activate';
          
        //  $data = [
        //     "account" => "5031240100"
        // ];
       $response = $this->post($url,$data);
        return json_decode($response,true);
        
    }

    public function createDynamicTemporaryVirtualAccount($data){
        $url =  '/collections/virtual-accounts/create';
        // $data = [
        //     "account_type" => "dynamic",
        //     "ref" => "62ed253e-f6ba-44cb-84bb-ab0790c7bf88",
        //     "currency" => "NGN",
        //     "order" => [
        //         "amount" => 250,
        //         "amount_type" => "EXACT",
        //         "description" => "A new payment for Sokoya Philip Order with #9713e031-37ad-44c4-b914-903dc2e6ab87",
        //         "expiry" => [
        //             "duration" => 1,
        //             "duration_type" => "hours"
        //         ]
        //     ],
        //     "customer" => [
        //         "name" => "Sokoya Philip",
        //         "email" => "hello@payscribe.ng",
        //         "phone" => "07038067493"
        //     ]
        // ];
        $response = $this->post($url,$data);    
        return json_decode($response,true);

    }


    public function verifyPayment($data){
        $url = '/collections/virtual-accounts/confirm-payment';
        $data = [
            "trans_id" => "",
            "session_id" => "100004240805170152117622486170",
            "amount" => 100,
            "account_number" => "5300000011"
        ];

        $this->post($url,$data);
        
    }


    public function simulateATransaction($data){
        $url = '//collections/virtual-accounts/simulate-transfer';
        $data = [
            "ref" => "300a7a03-af2a-4f72-aea7-78bb6fb9bff9",
            "amount" => "500.00",
            "description" => "A test transfer",
            "currency" => "NGN",
            "account" => "5031230079",
            "name" => "Sokoya Philip",
            "bank" => "120001",
            "sender_account_number" => "1100000309",
            "sender_name" => "Ms. Salvatore Stoltenberg",
            "hash" => ""
        ];

        $this->post($url,$data);
        
    }




}