<?php

namespace App\Http\Helpers\Payscribe\BillsPayments;

use App\Http\Helpers\ConnectionHelper;


class IntAirtimeDataHelper extends ConnectionHelper {
    public function __construct(){
        parent::__construct();
    }

    public function getIntBillCountries(){
        $url = "/international-bills/countries";

        return $this->get($url);
    }

    public function getIntBillProviders($data){
        $url = "/international-bills/providers?iso={$data['iso']}";

        return $this->get($url);
    }

    public function getIntBillProducts($data){
        $url = "/international-bills/products?iso={$data['iso']}&code={$data['code']}";

        return $this->get($url);
    }

    public function getIntEstimateRates($data){
        $url = "/international-bills/rate?iso=GH&sku=GH_MT_TopUp&amount=1.2";
        // $url = "/international-bills/rate?iso={$data['iso']}&sku={$data['sku']}&amount={$data['amount']}";

        return $this->get($url);
    }
    public function vendIntBills($data){
        $url = "/international-bills/vend";

        return $this->post($url, $data);
    }
}