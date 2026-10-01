<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Helpers\Payscribe\BillsPayments\AirtimeHelper;
use App\Http\Helpers\Payscribe\PayscribeBalanceHelper;
use App\Http\Helpers\Payscribe\BillsPayments\BillPaymentHelper;
use App\Models\Transaction;
use App\Models\UserWallet;
use App\Traits\Notify;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Notifications\User\BillPaymentNotification;


class PayscribeAirtimeController extends Controller
{
    use Notify;

    private $billType = 'Airtime';

    public function __construct(private AirtimeHelper $airtimeHelper, private PayscribeBalanceHelper $payscribeBalanceHelper, private BillPaymentHelper $billPaymentHelper)
    {
    }

    public function airtime(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'network' => 'required | string',
            "amount" => 'required | string',
            "recipient" => 'required | string',
            "ported" => 'sometimes | boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $request->only([
            'network',
            "amount",
            "recipient",
            "ported"
        ]);

        $referenceId = Str::uuid();
        $refIdString = (string) $referenceId . '-auto_bill';
        $data = array_merge($data, ['ref' => $refIdString]);

        try {
            $validateBalance = $this->payscribeBalanceHelper->validateBalance($data['amount']);
            if (!!$validateBalance) {
                return $validateBalance;
            }

            $response = json_decode(
                $this->airtimeHelper->vendairtime($data, $refIdString),
                true
            );

            if ($response['status'] === true) {
                $this->payscribeBalanceHelper->createTransaction($data, $response, $this->billType);
                $this->deductAmount($data['amount']);

                // Notify User
                auth()->user()->notify(new BillPaymentNotification(
                    'Airtime',
                    $data['amount'],
                    $data['network'],
                    'Successful',
                    $response['message']['details']['ref'] ?? $refIdString
                ));
            }
            return $response;
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }

    }

    public function deductAmount($amount)
    {
        // dd($amount);
        $user = auth()->user()->id;
        $userWallets = UserWallet::where('user_id', $user)->first();
        // dd($userWallets);
        $userWallets->balance = bcsub($userWallets->balance, $amount, 8);
        $userWallets->save();
    }
}
