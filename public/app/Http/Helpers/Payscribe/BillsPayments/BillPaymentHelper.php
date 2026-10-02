<?php

namespace App\Http\Helpers\Payscribe\BillsPayments;

use App\Http\Helpers\Payscribe\BillsPayments\DataBundleHelper;
use App\Http\Helpers\Payscribe\BillsPayments\AirtimeHelper;
use App\Http\Helpers\Payscribe\BillsPayments\CableTVSubscriptionHelper;
use App\Http\Helpers\Payscribe\BillsPayments\ElectricityBillsHelper;
use App\Http\Helpers\Payscribe\BillsPayments\EpinsHelper;
use App\Http\Helpers\Payscribe\BillsPayments\InternetSubscriptionHelper;
use App\Http\Helpers\Payscribe\BillsPayments\IntAirtimeDataHelper;
use App\Http\Helpers\Payscribe\BillsPayments\FundBetWalletHelper;
use App\Http\Helpers\SafeHeaven\TransferHelper;
use App\Models\Transaction;
use App\Traits\Notify;
use App\Models\BillPayment;
use App\Models\UserAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BillPaymentHelper {
    use Notify;
    public function __construct(private DataBundleHelper $dataBundleHelper, private AirtimeHelper $airtimeHelper, private CableTVSubscriptionHelper $cableTVSubHelper, private ElectricityBillsHelper $electricityBillsHelper, private EpinsHelper $epinsHelper, private InternetSubscriptionHelper $internetSubHelper, private IntAirtimeDataHelper $intAirtimeDataHelper, private TransferHelper $transferHelper, private FundBetWalletHelper $fundBetWalletHelper) {}


    /**
     * Fund the masterallet
     */

    public function trigger($transData){
        if(Str::contains($transData['ref_id'], '-cardwithdrawal')){
            return $this->withdrawFromCard($transData);
        }
        else{
            return $this->fundBillsWallet($transData);

        }
    }
    private function fundBillsWallet($transData){
        $account_details = DB::table('safe_credentials')->first();
        $accountNum = $account_details->account_number;
        $bankCode = $account_details->bank_code;
       
        $data = [
            "accountNumber" => $accountNum,
            "bankCode" => $bankCode,
        ];

        // perform name enquiry
        $response = $this->transferHelper->nameEnquiry($data);

        if ($response['statusCode'] === 200) {
            $nameEnquiryReference = $response['data']['sessionId'];
        }

        // fund the masterallet
        $debitAccount = UserAccount::where('user_id', $transData->user_id)->where('account_type', 'NGN')->value('account_number');

        $data = [
            'amount' => (int) $transData['amount'],
            'nameEnquiryReference' => $nameEnquiryReference,
            'beneficiaryBankCode' => $bankCode,
            'beneficiaryAccountNumber' => $accountNum,
            'saveBeneficiary' => false,
            'narration' => $transData['transactional_type'],
            'paymentReference' => $transData['ref_id'],
            'debitAccountNumber' => $debitAccount,
        ];

        ///update transction


        //Transfer to the masterallet

        $response = $this->transferHelper->transfer($data);
        ///TODO Re-Update transcation table
        return $response;
    }

    private function withdrawFromCard($transData) {
        $account_details = DB::table('safe_credentials')->first();

        $accountNum = UserAccount::where('user_id', $transData->user_id)->where('account_type', 'NGN')->value('account_number');
        $bankCode = $account_details->bank_code; // from database
        
        $data = [
            "accountNumber" => $accountNum,
            "bankCode" => $bankCode,
        ];

        // perform name enquiry
        $response = $this->transferHelper->nameEnquiry($data);

        if ($response['statusCode'] === 200) {
            $nameEnquiryReference = $response['data']['sessionId'];
        }

        // fund the masterallet
        
        $debitAccount = $account_details->account_number;// from databse
        // $debitAccount = '0111579316';// from databse


        $data = [
            'amount' => (int) $transData['amount'],
            'nameEnquiryReference' => $nameEnquiryReference,
            'beneficiaryBankCode' => $bankCode,
            'beneficiaryAccountNumber' => $accountNum,
            'saveBeneficiary' => false,
            'narration' => $transData['transactional_type'],
            'paymentReference' => $transData['ref_id'],
            'debitAccountNumber' => $debitAccount,
        ];

        ///update transction


        //Transfer to the masterallet

        $response = $this->transferHelper->transfer($data);
        ///TODO Re-Update transcation table
        return $response;
    }

    private function createTransaction($request, $response, $modelPath) {
            $balance = auth()->user()->account_balance - $request['amount'];
            $transId = $response['message']['details']['trans_id'] ?? null;
            Transaction::create([
                'transactional_type' => $modelPath,
                'user_id' => auth()->user()->id,
                'amount' => $response['message']['details']['amount'],
                'currency' => 'NGN',
                'balance' => $balance,
                'trx_type' => '-',
                'remarks' => $response['description'],
                'trx_id' => $transId,
                'transaction_status' => 'pending',
            ]);
            // $this->payscribeBalanceHelper->updateUserBalance($balance);
        }


}