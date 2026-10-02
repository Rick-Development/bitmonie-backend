<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Helpers\Payscribe\Collections\NGNVirtualAccountsHelper;
use App\Models\VirtualAccounts;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PayscribeNGNVirtualAccountController extends Controller
{
    function __construct(private NGNVirtualAccountsHelper $nGNVirtualAccountsHelper)
    {
    }
    public function virtualAccountDetails()
    {
        $user = auth()->user();
        $account_num = $user->account_no;
        // dd($account_num);
        $response = $this->nGNVirtualAccountsHelper->getVirtualAccountDetails($account_num);
        // dd($response);
        // return response($account_num, 200);
        return response()->json($response, $response['status_code']);

    }

    public function deactivateVirtualAccount()
    {
        $user = auth()->user();
        $data = [
            "account" => $user->account_no
        ];

        $response = $this->nGNVirtualAccountsHelper->deactivateVirtualAccount($data);
        return response()->json($response, $response['status_code']);
        // return response()->json($data);
    }

    public function activateVirtualAccount()
    {
        $user = auth()->user();
        $data = [
            "account" => $user->account_no
        ];

        $response = $this->nGNVirtualAccountsHelper->activateVirtualAccount($data);
        return response()->json($response, $response['status_code']);
        // return response()->json($data);
    }

    public function dynamicTemporaryVirtualAccount(Request $request)
    {
        $user = auth()->user();

        $referenceId = Str::uuid();
        $referenceIdString = (string) $referenceId;
        $reqData = $request->validate([
            'amount' => 'required | integer',
        ]);

        $data = [
            "account_type" => "dynamic",
            "ref" => "$referenceIdString",
            "currency" => "NGN",
            "order" => [
                "amount" => $reqData['amount'],
                "amount_type" => "EXACT",
                "description" => "A new payment for {$user['firstname']} {$user['lastname']} Order with {$user['payscribe_id']}",
                "expiry" => [
                    "duration" => 1,
                    "duration_type" => "hours"
                ]
            ],
            "customer" => [
                "name" => $user['firstname'] . '' . $user['lastname'],
                "email" => $user['email'],
                "phone" => "+234" . $user['phone']
            ]
        ];
        // return response()->json($data);

        $response = $this->nGNVirtualAccountsHelper->createDynamicTemporaryVirtualAccount($data);

        return response()->json($response, $response['status_code']);

    }

    // public varifyPayment(Request $request){
    //     $dataReq = $request->validate([
    //         'amount' => 'required | integer',
    //         'account_number' => 'required | string',
    //     ]);
    //     $user = auth()->user();
    // }

    public function create_parmenent_virtual_account(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'account_type' => 'required | string',
            'currency' => 'required | string',
            'bank' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = [
            'account_type' => $request->account_type,
            'currency' => $request->currency,
            'customer_id' => auth()->user()->payscribe_customer_id,
            'bank' => $request->bank,
        ];
        // dd($data);

        $response = $this->nGNVirtualAccountsHelper->createVirtualAccount($data);
        // dd($response);

        if ($response && $response['status'] == true) {
            $user = auth()->user();
            $user->account_no = $response['message']['details']['account'][0]['account_number'];
            $user->save();

            VirtualAccounts::firstOrCreate(
                ['user_id' => auth()->id()],
                [
                    'customer_id' => $response['message']['details']['customer']['id'],
                    'customer' => $response['message']['details']['customer']['name'],
                    'account_id' => $response['message']['details']['account'][0]['id'],
                    'account_number' => $response['message']['details']['account'][0]['account_number'],
                    'account_name' => $response['message']['details']['account'][0]['account_name'],
                    'bank_name' => $response['message']['details']['account'][0]['bank_name'],
                    'bank_code' => $response['message']['details']['account'][0]['bank_code'],
                    'currency' => $response['message']['details']['account'][0]['currency'],
                    'account_type' => $response['message']['details']['account'][0]['account_type'],
                    'status' => $response['message']['details']['status'],
                ]
            );
        }

        return response()->json($response, $response['status_code']);
    }


}
