<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Helpers\SafeHeaven\IdentityCheckHelper;
use App\Models\SafeHeavenCustomerDetails;
use App\Models\Tier;
use App\Models\User;
use App\Models\UserAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class SafeHeavenIdentityCheckController extends Controller
{
    public function __construct(private IdentityCheckHelper $identityCheckHelper) {}

    public function initiateVerification($data) {
        $account_details = DB::table('safe_credentials')->first();
        $data = array_merge($data, [
            'debitAccountNumber' => $account_details->account_number, //admin account number
            // 'debitAccountNumber' => "0111579316",
        ]);
        
        $response = $this->identityCheckHelper->initiateVerification($data);
        return $response;
    }

    public function validateVerification($data) {

        
        $response = $this->identityCheckHelper->validateVerification($data);
        
        return $response;
    }
}