<?php

namespace App\Traits;

use App\Traits\Notify;

trait SendEmail
{
    use Notify;
    public function sendBillPaymentEmail($amount,$billType)
    {
        $user = auth()->user();
        $params = [
            'amount' => $amount,
            'type' => strtolower($billType),
        ];
        $this->mail($user,'BILL_PAYMENT', $params);
    }
    
    public function sendTransferEmail($data,$user)
    {
        $params = [
            'amount' => $data['amount'],
            'credit_account_name' => $data['creditAccountName'],
            'credit_account_number' => $data['creditAccountNumber'],
            'transaction' => $data['paymentReference'],
            'date' => now()->format('d M Y, h:i A'),
        ];
        $this->mail($user,'MONEY_TRANSFER_USER', $params);
    }
    
    public function sendDepositEmail($data, $user)
    {
        $params = [
            'amount' => $data['amount'],
            'currency' => 'NGN',
            'debit_account_name' => $data['debitAccountName'],
            'debit_account_number' => $data['debitAccountNumber'],
            'transaction' => $data['paymentReference'],
            'date' => now()->format('d M Y, h:i A'),
        ];
        $this->mail($user,'FUNDS_DEPOSITED', $params);
    }
    
    public function sendCardWithdrawalEmail($amount, $card, $trans_id)
    {
        $user = auth()->user();
        $params = [
            'amount' => $amount,
            'currency' => 'USD',
            'cardNumber' => $card['first_six'] . ' ****** ' . $card['last_four'],
            'transaction' => $trans_id,
            'date' => now()->format('d M Y, h:i A'),
        ];
        $this->mail($user,'VIRTUAL_CARD_WITHDRAWN', $params);
    }
    
    public function sendCardDepositEmail($amount,$card, $trans_id)
    {
        $user = auth()->user();
        $params = [
            'amount' => $amount,
            'currency' => 'USD',
            'cardNumber' => $card['first_six'] . ' ****** ' . $card['last_four'],
            'transaction' => $trans_id,
            'date' => now()->format('d M Y, h:i A'),
        ];
        $this->mail($user,'VIRTUAL_CARD_FUND_APPROVE', $params);
    }
}