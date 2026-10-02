<?php

namespace App\Http\Controllers\API;

use App\Models\UserWallet;
use App\Models\BillPayment;
use App\Models\Transaction;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Helpers\Payscribe\PayscribeBalanceHelper;
use App\Http\Helpers\Payscribe\BillsPayments\DataBundleHelper;
use App\Http\Helpers\Payscribe\BillsPayments\BillPaymentHelper;
use App\Notifications\User\BillPaymentNotification;


class PayscribeDataBundleController extends Controller
{
    private $billType = 'Data Bundle';

    public function __construct(private DataBundleHelper $dataBundleHelper, private PayscribeBalanceHelper $payscribeBalanceHelper, private BillPaymentHelper $billPaymentHelper)
    {
    }
    public function dataLookup(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'network' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        $response = json_decode($this->dataBundleHelper->dataLookup($request->network), true);
        return $response;

    }

    public function dataVending(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'plan' => 'required|string',
            'recipient' => 'required|string',
            'network' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->only('plan', 'recipient', 'network');
        // $validateBalance = $this->payscribeBalanceHelper->validateBalance($data['amount']);

        // if (!!$validateBalance) {
        //     return $validateBalance;
        // }

        $referenceId = Str::uuid();
        $referenceIdString = (string) $referenceId . '-auto_bill';
        $data = array_merge($data, ['ref' => $referenceIdString]);

        $response = json_decode($this->dataBundleHelper->dataVending($data), true);

        if ($response['status'] === true) {
            $user = auth()->user();
            $user_balance = UserWallet::where('user_id', $user->id)
                ->where('currency_code', 'NGN')
                ->value('balance');

            $amount = (int) $response['message']['details']['amount'];
            if ($user_balance < $amount) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Insufficient balance'
                ], 500);
            }

            $this->payscribeBalanceHelper->createTransaction($data, $response, $this->billType);
            // $this->sendBillPaymentEmail($data['amount'], $this->billType);

            // Notify User
            auth()->user()->notify(new BillPaymentNotification(
                'Data Bundle',
                $amount,
                $data['network'],
                'Successful',
                $response['message']['details']['ref'] ?? $referenceIdString
            ));
        }

        return $response;
    }


}
