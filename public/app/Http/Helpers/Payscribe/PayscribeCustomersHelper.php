<?php

namespace App\Http\Helpers\Payscribe;

use App\Http\Helpers\ConnectionHelper;

class PayscribeCustomersHelper  extends ConnectionHelper{

    public function __construct(){
        parent::__construct();
    }

    public function createUser(array $data){
        $url = '/customers/create';
        $response = $this->post( $url,$data);
        return json_decode($response,true);
    }


    public function upgradeToTier1(array $data){
        $url = '/customers/create/tier1';

        return  $this->patch($url,$data);

    }

    public function upgradeToTier2(array $data){
        $url = '/customers/create/tier2';


        return  $this->patch($url,$data);

    }
    public function createFullCustomer(array $data)
    {

        $url = '/customers/create/full';

        $data = [
            "first_name" => "Juston",
            "last_name" => "Vandervort",
            "phone" => "2347044667722",
            "email" => "Ali.Stanton@gmail.com",
            "dob" => "1990-06-20",
            "country" => "NG",
            "address" => [
                "street" => "56, Adeola Odeku, Victoria Island",
                "city" => "Ojota",
                "state" => "Lagos",
                "country" => "NG",
                "postal_code" => "882700"
            ],
            "identification_type" => "BVN",
            "identification_number" => "22288771100",
            "photo" => "http://placeimg.com/640/480",
            "identity" => [
                "type" => "NIN",
                "number" => "22288771100",
                "country" => "NG",
                "image" => "http://placeimg.com/640/480"
            ]
        ];

        $response = $this->post($url,$data);

        return [
            'id' => rand(1, 1000),
            'name' => 'name',//$data['name'],
            'email' => 'name', //$data['email'],
        ];
    }

    public function getAcustomer($customerID){
        $url = "/customers/$customerID/details";
        $response = $this->get($url);
    }

    public function GetAllCustomer(){
        $url = '/customers/?page=1&page_size=10';
        $response = $this->get($url);
        return json_decode($response,true);
    }



    public function  getCustomerTransactions(array $data,$customerID){
        $url = '/customers/$customerID/transactions?page=1&page_size=10';
        $data =[
            "start_date" => "2024-07-01",
            "end_date" => "2024-07-30"
        ];
         $this->post($url,$data);
    }




}
