<?php

namespace App\Exports;

use App\Constants\PaymentGatewayConst;
use App\Models\Transaction;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class VirtualCardTrxExport implements FromArray, WithHeadings{

    public function headings(): array
    {
        return [
            ['SL','TRX ID', 'USER','TYPE','AMOUNT','CHARGE','STATUS','TIME'],
        ];
    }

    public function array(): array
    {
        return Transaction::with(
            'user:id,firstname,lastname,email,username,full_mobile',
          )->where('type', PaymentGatewayConst::TYPEVIRTUALCARD)->latest()->get()->map(function($item,$key){
            return [
                'id'        => $key + 1,
                'trx'       => $item->trx_id,
                'user'      => $item->user->fullname,
                'type'      => $item->remark,
                'amount'    => get_amount($item->request_amount,$item->request_currency),
                'charge'    => get_amount($item->total_charge,$item->request_currency),
                'status'    => __( $item->stringStatus->value),
            ];
         })->toArray();

    }
}

