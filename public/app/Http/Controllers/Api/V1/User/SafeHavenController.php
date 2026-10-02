<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Services\SafeHavenService;
use App\Http\Helpers\Response;
use App\Models\VirtualAccounts;
use App\Models\Bank;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SafeHavenController extends Controller
{
    protected $safeHaven;

    public function __construct(SafeHavenService $safeHaven)
    {
        $this->safeHaven = $safeHaven;
    }

    /**
     * Get the list of banks from the local DB.
     */
    public function banks()
    {
        try {
            $banks = Bank::where('is_active', true)->orderBy('name')->get();
            return Response::successResponse('Bank list fetched', $banks);
        } catch (Exception $e) {
            return Response::errorResponse('Failed to fetch bank list: ' . $e->getMessage());
        }
    }

    /**
     * Get or create a fixed NGN sub-account for the user.
     */
    public function virtualAccount()
    {
        try {
            $user = auth()->user();
            
            // 1. Check if user already has a SafeHaven sub-account
            $account = $this->safeHaven->getUserSubAccount($user);

            if (!$account) {
                // 2. KYC Check (Tier 1 required)
                if ($user->kyc_tier < 1) {
                    return Response::errorResponse('Please complete KYC Tier 1 verification (BVN) to generate your permanent account number.', [
                        'action' => 'kyc_tier_1',
                        'type' => 'kyc_required'
                    ], 403);
                }

                // 3. Create if not exists
                $data = $this->safeHaven->createSubAccount($user);
                
                $account = VirtualAccounts::create([
                    'user_id' => $user->id,
                    'customer_id' => $data['_id'] ?? $data['id'] ?? null,
                    'customer' => $data['accountName'] ?? $user->fullname,
                    'account_id' => $data['_id'] ?? $data['id'] ?? null,
                    'account_number' => $data['accountNumber'] ?? null,
                    'account_name' => $data['accountName'] ?? $user->fullname,
                    'bank_name' => 'SafeHaven Microfinance Bank',
                    'bank_code' => '090286', // SafeHaven MFB Code
                    'currency' => 'NGN',
                    'account_type' => 'Sub-Account',
                    'status' => 'active',
                    'provider' => 'safehaven'
                ]);
            }

            // Ensure provider is set if it was missing (migration fix)
            if (empty($account->provider)) {
                $account->update(['provider' => 'safehaven']);
            }

            $syncedBalance = $this->safeHaven->syncUserWalletBalance($user, 'NGN');
            $wallet = $user->wallets()->where('currency_code', 'NGN')->first();
            $walletBalance = $syncedBalance ?? ($wallet ? number_format((float) $wallet->balance, 8, '.', '') : null);

            return Response::successResponse('Naira account details fetched', [
                'account_number' => $account->account_number,
                'account_name'   => $account->account_name,
                'bank_name'      => $account->bank_name,
                'wallet_balance' => $walletBalance,
                'wallet_balance_source' => $syncedBalance !== null ? 'safehaven_account' : 'local_wallet',
                'note'           => "This is your permanent NGN deposit account.",
                'instruction'    => "Transfer any amount to this account to fund your NGN wallet instantly.",
                'disclaimer'     => "Ensure you only send NGN. Third-party deposits may be subject to verification."
            ]);

        } catch (Exception $e) {
            return Response::errorResponse('Failed to fetch account details: ' . $e->getMessage());
        }
    }

    /**
     * Name Enquiry for outbound transfers.
     */
    public function nameEnquiry(Request $request)
    {
        $request->validate([
            'bank_code' => 'required',
            'account_number' => 'required|numeric'
        ]);

        try {
            $data = $this->safeHaven->nameEnquiry($request->bank_code, $request->account_number);
            return Response::successResponse('Account name verified', $data);
        } catch (Exception $e) {
            return Response::errorResponse($e->getMessage());
        }
    }

    /**
     * Outbound Transfer from NGN wallet to other bank accounts.
     */
    public function transfer(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:100',
            'bank_code' => 'required',
            'account_number' => 'required|numeric',
            'narration' => 'nullable|string|max:100',
            'name_enquiry_reference' => 'nullable|string',
            'session_id' => 'nullable|string',
        ]);

        try {
            $user = auth()->user();
            $wallet = $user->wallets()->where('currency_code', 'NGN')->firstOrFail();
            $amount = (float) $request->amount;

            // 1. Check Balance
            if (bccomp((string) $wallet->balance, (string) $amount, 8) < 0) {
                return Response::errorResponse('Insufficient wallet balance.');
            }

            $reference = 'transfer:' . Str::random(12);

            return DB::transaction(function () use ($wallet, $amount, $request, $reference, $user) {
                // 2. Debit Wallet
                \App\Services\WalletService::debit($wallet->id, (string)$amount, $reference, [
                    'type' => 'bank_transfer',
                    'bank_code' => $request->bank_code,
                    'account_number' => $request->account_number
                ]);

                // 3. Resolve Name Enquiry & Session Reference
                $nameEnquiryRef = $request->input('name_enquiry_reference') ?? $request->input('session_id');
                $beneficiaryBankCode = (string) $request->bank_code;
                $beneficiaryAccountNumber = (string) $request->account_number;

                if (!$nameEnquiryRef) {
                    $enquiry = $this->safeHaven->nameEnquiry($request->bank_code, $request->account_number);
                    $nameEnquiryRef = $enquiry['sessionId'] 
                        ?? $enquiry['data']['sessionId'] 
                        ?? $enquiry['session_id'] 
                        ?? $enquiry['nameEnquiryReference']
                        ?? null;

                    if (!empty($enquiry['bankCode']) || !empty($enquiry['data']['bankCode'])) {
                        $beneficiaryBankCode = (string) ($enquiry['bankCode'] ?? $enquiry['data']['bankCode']);
                    }
                    if (!empty($enquiry['accountNumber']) || !empty($enquiry['data']['accountNumber'])) {
                        $beneficiaryAccountNumber = (string) ($enquiry['accountNumber'] ?? $enquiry['data']['accountNumber']);
                    }
                }

                if (!$nameEnquiryRef) {
                    throw new Exception("Failed to generate name enquiry reference.");
                }

                // 4. Resolve Debit Account (User Sub-Account or configured Main/Settlement Account)
                $userSubAccount = $this->safeHaven->getUserSubAccount($user);
                $debitAccountNumber = $userSubAccount?->account_number 
                    ?: config('services.safeHeaven.main_account') 
                    ?: env('SAFE_HEAVEN_MAIN_ACCOUNT');

                if (!$debitAccountNumber) {
                    throw new Exception("No active debit account found for bank transfer. Please ensure your account or platform settlement account is configured.");
                }

                // 5. Call SafeHaven Transfer API
                $payload = [
                    "saveBeneficiary" => false,
                    "nameEnquiryReference" => (string) $nameEnquiryRef,
                    "debitAccountNumber" => (string) $debitAccountNumber,
                    "beneficiaryBankCode" => (string) $beneficiaryBankCode,
                    "beneficiaryAccountNumber" => (string) $beneficiaryAccountNumber,
                    "amount" => (float) $amount,
                    "narration" => $request->narration ?? "Withdrawal from wallet",
                    "paymentReference" => $reference
                ];

                try {
                    $result = $this->safeHaven->transfer($payload);
                    return Response::successResponse('Transfer initiated successfully', $result);
                } catch (Exception $e) {
                    // Refund on Failure
                    \App\Services\WalletService::credit($wallet->id, (string)$amount, $reference . ':refund', [
                        'reason' => 'SafeHaven API Failure: ' . $e->getMessage()
                    ]);
                    throw $e;
                }
            });

        } catch (Exception $e) {
            return Response::errorResponse('Transfer failed: ' . $e->getMessage());
        }
    }
}
