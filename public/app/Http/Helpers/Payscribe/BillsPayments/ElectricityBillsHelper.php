<?php
namespace App\Http\Helpers\Payscribe\BillsPayments;

use App\Http\Helpers\ConnectionHelper;


class  ElectricityBillsHelper extends ConnectionHelper {


    public function __construct(){
        parent::__construct();
    }
    public function validateElectricity($data){
        $url = "/electricity/validate";
        // $data= [
        //     "meter_number" => "54150143102",
        //     "meter_type"  => "prepaid",
        //     "amount"  => "1000",
        //     "service"  => "ikedc"
        // ];
        return $this->post($url,$data);

    }

    public function payElectricity($data){
        $url = "/electricity/vend";
        // $data= [
        //     "meter_number" => "54150143102",
        //     "meter_type"  => "prepaid",
        //     "amount"  => 100,
        //     "service"  => "ikedc",
        //     "phone"  => "07038067493",
        //     "customer_name"  => "26, Terrance Connelly",
        //     "ref" => "3a34de42-d4dc-42c1-904a-c28c6002cb8b"
        // ];

        return $this->post($url,$data);
    }

    #GET
     public function requeryTransaction($transactioID){
        $url = "/requery/?trans_id=$transactioID";
        return $this->get( $url);

     }
     
}