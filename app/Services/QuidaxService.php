<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Exception;
use App\Models\UserWallet;
use App\Models\User;
use Illuminate\Support\Facades\DB;
class QuidaxService
{
    protected $baseUrl;
    protected $rampUrl;
    protected $p2pUrl;
    protected $apiSecret;
    protected $privateKey;
    protected $curl;


    public function __construct()
    {
        $this->baseUrl = config('services.quidax.url');
        $this->rampUrl = config('services.quidax.ramp_url');
        $this->p2pUrl = config('services.quidax.p2p_url');

        $this->apiSecret = config('services.quidax.secret');
        $this->privateKey = config('services.quidax.private');


        $this->curl = new CurlService(
            $this->baseUrl,
            $this->privateKey,
            $this->apiSecret,
            $this->rampUrl,
            $this->p2pUrl
        );
    }


    /**
     * Normalize currency codes
     */
    private function currency(string $currency): string
    {
        return strtolower(trim($currency));
    }


    /**
     * Validate Quidax response
     */
    private function ensureSuccess(
        array $response,
        string $message
    ): array {

        if (($response['status'] ?? null) !== 'success') {

            Log::error($message, [
                'response' => $response
            ]);

            throw new \Exception(
                $response['message'] ?? $message
            );
        }

        return $response;
    }


    /**
     * Create Quidax sub account
     */
    public function createSubAccount(array $data)
    {
        return $this->curl->post(
            "v1/users",
            $data
        );
    }


    /**
     * Get authenticated user
     */
    public function getUser()
    {
        return $this->curl->get(
            "v1/users/me"
        );
    }


    /**
     * Fetch user wallets
     */
    public function fetchUserWallets($quidax_id)
    {
        return $this->curl->get(
            "v1/users/{$quidax_id}/wallets"
        );
    }


    /**
     * Fetch single wallet
     */
    public function fetchUserWallet(
        $quidax_id,
        $currency
    ) {

        return $this->curl->get(
            "v1/users/{$quidax_id}/wallets/" .
            $this->currency($currency)
        );
    }


    /**
     * Fetch wallet deposit address
     */
    public function fetchPaymentAddress(
        $quidax_id,
        $currency
    ) {

        return $this->curl->get(
            "v1/users/{$quidax_id}/wallets/" .
            $this->currency($currency) .
            "/address"
        );
    }


    /**
     * Fetch all wallet addresses
     */
    public function fetchPaymentAddressses(
        $quidax_id,
        $currency
    ) {

        return $this->curl->get(
            "v1/users/{$quidax_id}/wallets/" .
            $this->currency($currency) .
            "/addresses"
        );
    }


    /**
     * Create crypto payment address
     */
    public function createCryptoPaymentAddress(
        $quidax_id,
        $currency,
        $network
    ) {

        return $this->curl->post(
            "v1/users/{$quidax_id}/wallets/" .
            $this->currency($currency) .
            "/addresses?network={$network}"
        );
    }    /**
     * Fetch withdrawals
     */
    public function fetch_withdraws(
        $quidax_id,
        $currency,
        $status
    ) {

        return $this->curl->get(
            "v1/users/{$quidax_id}/withdraws?" .
            "order_by=asc" .
            "&currency=" . $this->currency($currency) .
            "&state={$status}"
        );
    }


    /**
     * Fetch single withdrawal
     */
    public function fetch_a_withdrawal(
        $quidax_id,
        $withdrawal_id
    ) {

        return $this->curl->get(
            "v1/users/{$quidax_id}/withdraws/{$withdrawal_id}"
        );
    }


    /**
     * Cancel withdrawal
     */
    public function cancel_withdrawal($withdrawal_id)
    {
        return $this->curl->post(
            "v1/users/me/withdraws/{$withdrawal_id}/cancel"
        );
    }


    /**
     * Create withdrawal
     */
    public function create_withdrawal(
        $quidax_id,
        array $data,
         ?string $reference = null
    ) {
        

         if (
        $reference !== null
        && trim($reference) !== ''
    ) {
        $data['reference'] = trim($reference);
    }

    return $this->curl->post(
        "v1/users/{$quidax_id}/withdraws",
        $data
    );
    }



    /**
     * Internal transfer between Quidax accounts
     */
    public function transfer(
        $from_quidax_id,
        $to_quidax_id,
        $amount,
        $currency,
        ?string $reference = null,
        ?string $note = null
    ) {

        $data = [
            'fund_uid' => $to_quidax_id,
            'amount'   => $amount,
            'currency' => $this->currency($currency),

            'transaction_note' =>
                $note ?: 'Internal transfer',

            'narration' =>
                $note ?: 'Internal transfer',
        ];


        if ($reference !== null && trim($reference) !== '') {
            $data['reference'] = $reference;
        }


        return $this->create_withdrawal(
            $from_quidax_id,
            $data
        );
    }



    /**
     * Fund sub account from platform account
     */
    public function fundSubAccount(
        $quidax_id,
        $amount,
        $currency,
        ?string $reference = null,
        ?string $note = null
    ) {

        $data = [
            'fund_uid' => $quidax_id,
            'amount'   => $amount,
            'currency' => $this->currency($currency),

            'transaction_note' =>
                $note ?: 'Internal transfer to sub-account',

            'narration' =>
                $note ?: 'Internal transfer to sub-account',
        ];


        if ($reference !== null && trim($reference) !== '') {
            $data['reference'] = $reference;
        }


        return $this->create_withdrawal(
            'me',
            $data
        );
    }



    /**
     * Move crypto from user sub-account
     * into platform escrow wallet.
     *
     * Existing method name preserved.
     */
    public function transferToEscrow(
        $quidax_id,
        $amount,
        $currency,
        ?string $transactionNote = null
    ) {


        $currency = $this->currency($currency);


        /*
        |--------------------------------------------------------------------------
        | Get platform wallet address
        |--------------------------------------------------------------------------
        */

        $addressResponse =
            $this->fetchPaymentAddress(
                'me',
                $currency
            );


        $escrowAddress =
            $addressResponse['data']['address'] ?? null;



        if (!$escrowAddress) {

            throw new \Exception(
                "Unable to get platform {$currency} escrow address."
            );
        }



        /*
        |--------------------------------------------------------------------------
        | Create withdrawal from user wallet
        |--------------------------------------------------------------------------
        */

        $data = [

            'currency' => $currency,

            'amount' => $amount,

            /*
            Destination platform wallet
            */
            'fund_uid' => $escrowAddress,


            'transaction_note' =>
                $transactionNote ?: 'P2P escrow lock',

        ];



        $response =
            $this->create_withdrawal(
                $quidax_id,
                $data
            );


        return $response;
    }




    /**
     * Check platform wallet balance
     */
    public function fetchPlatformWallet(
        string $currency
    ): array {

        return $this->curl->get(
            "v1/users/me/wallets/" .
            $this->currency($currency)
        );
    }





    /**
     * Release crypto from platform escrow
     * to buyer.
     */
    public function releaseFromEscrow(
        string $recipientQuidaxId,
        string $amount,
        string $currency,
        ?string $transactionRef = null,
        string $mode = 'release'
    ): array {


        if (empty($recipientQuidaxId)) {

            Log::error(
                'Quidax escrow release missing recipient'
            );


            return [
                'status'=>'error',
                'message'=>'Recipient Quidax ID missing'
            ];
        }



        if (
            bccomp(
                $amount,
                '0',
                8
            ) <= 0
        ) {

            return [
                'status'=>'error',
                'message'=>'Invalid escrow amount'
            ];
        }



        $currency =
            $this->currency($currency);



        /*
        |--------------------------------------------------------------------------
        | Check platform balance
        |--------------------------------------------------------------------------
        */

        $wallet =
            $this->fetchPlatformWallet(
                $currency
            );


        $available =
            $wallet['data']['balance'] ?? '0';



        if (
            bccomp(
                $available,
                $amount,
                8
            ) < 0
        ) {


            Log::critical(
                'Quidax escrow insufficient balance',
                [
                    'required'=>$amount,
                    'available'=>$available,
                    'currency'=>$currency,
                    'reference'=>$transactionRef
                ]
            );


            return [
                'status'=>'error',
                'message'=>
                    "Insufficient platform {$currency} balance"
            ];
        }




        $note =
            $mode === 'refund'
            ? 'P2P escrow refund'
            : 'P2P escrow release';



        if ($transactionRef) {

            $note .=
                " | ref:{$transactionRef}";
        }



        Log::info(
            'Releasing Quidax escrow',
            [
                'recipient'=>$recipientQuidaxId,
                'amount'=>$amount,
                'currency'=>$currency,
                'mode'=>$mode
            ]
        );



        /*
        |--------------------------------------------------------------------------
        | Send from main account
        |--------------------------------------------------------------------------
        */

        $response =
            $this->curl->post(
                "v1/users/me/send_money",
                [
                    'currency'=>$currency,

                    'amount'=>$amount,

                    'fund_uid'=>$recipientQuidaxId,

                    'transaction_note'=>$note,
                ]
            );



        if (
            ($response['status'] ?? null)
            !== 'success'
        ) {


            Log::error(
                'Quidax escrow release failed',
                [
                    'response'=>$response
                ]
            );
        }


        return $response;
    }    /**
     * Fetch deposits
     */
    public function fetch_deposits(
        $quidax_id,
        $currency,
        $state
    ) {

        return $this->curl->get(
            "v1/users/{$quidax_id}/deposits?" .
            "currency=" . $this->currency($currency) .
            "&state={$state}"
        );
    }



    /**
     * Fetch single deposit
     */
    public function fetch_a_deposit(
        $quidax_id,
        $deposit_id
    ) {

        return $this->curl->get(
            "v1/users/{$quidax_id}/deposits/{$deposit_id}"
        );
    }




    /*
    |--------------------------------------------------------------------------
    | Swap
    |--------------------------------------------------------------------------
    */


    public function createSwapQuotation(
        $quidax_id,
        array $data
    ) {

        return $this->curl->post(
            "v1/users/{$quidax_id}/swap_quotation",
            $data
        );
    }



    public function swap(
        $quidax_id,
        $quotation_id
    ) {

        return $this->curl->post(
            "v1/users/{$quidax_id}/swap_quotation/{$quotation_id}/confirm"
        );
    }




    public function refresh_instant_swap_quotation(
        $quidax_id,
        $quotation_id,
        array $data
    ) {

        return $this->curl->post(
            "v1/users/{$quidax_id}/swap_quotation/{$quotation_id}/refresh",
            $data
        );
    }




    public function temporary_swap_quotation(
        $quidax_id,
        array $data
    ) {

        return $this->curl->post(
            "v1/users/{$quidax_id}/temporary_swap_quotation",
            $data
        );
    }



    public function fetch_swap_transaction(
        $quidax_id,
        $swap_transaction_id
    ) {

        return $this->curl->get(
            "v1/users/{$quidax_id}/swap_transactions/{$swap_transaction_id}"
        );
    }



    public function get_swap_transacdtion(
        $quidax_id
    ) {

        return $this->curl->get(
            "v1/users/{$quidax_id}/swap_transactions"
        );
    }




    /*
    |--------------------------------------------------------------------------
    | Ramp
    |--------------------------------------------------------------------------
    */


    public function initiate_ramp_transaction(
        array $data
    ) {

        return $this->curl->post(
            "v1/merchants/custodial/on_ramp_transactions/initiate",
            $data
        );
    }




    /*
    |--------------------------------------------------------------------------
    | Public P2P adverts
    |--------------------------------------------------------------------------
    */


    public function get_all_public_adverts(
        array $data = []
    ) {

        return $this->curl->get(
            "v1/p2p/adverts",
            $data
        );
    }



    public function get_single_public_advert(
        $advert_id
    ) {

        return $this->curl->get(
            "v1/p2p/adverts/{$advert_id}"
        );
    }





    /*
    |--------------------------------------------------------------------------
    | Instant Orders
    |--------------------------------------------------------------------------
    */


    public function createInstantOrder(
        $quidax_id,
        array $data
    ) {

        return $this->curl->post(
            "v1/users/{$quidax_id}/instant_orders",
            $data
        );
    }




    public function confirmInstantOrder(
        $quidax_id,
        $order_id
    ) {

        return $this->curl->post(
            "v1/users/{$quidax_id}/instant_orders/{$order_id}/confirm"
        );
    }




    public function requoteInstantOrder(
        $quidax_id,
        $order_id
    ) {

        return $this->curl->post(
            "v1/users/{$quidax_id}/instant_orders/{$order_id}/requote"
        );
    }




    public function getInstantOrder(
        $quidax_id,
        $order_id
    ) {

        return $this->curl->get(
            "v1/users/{$quidax_id}/instant_orders/{$order_id}"
        );
    }




    public function getInstantOrders(
        $quidax_id
    ) {

        return $this->curl->get(
            "v1/users/{$quidax_id}/instant_orders"
        );
    }
/**
 * Synchronize a user's local wallet balance with Quidax.
 *
 * This fetches the authoritative wallet balance from Quidax and
 * updates/creates the corresponding local UserWallet record.
 *
 * @param User   $user
 * @param string $asset
 * @return UserWallet
 *
 * @throws Exception
 */
public function syncUserWallet(
    User $user,
    string $asset
): UserWallet {

    if (empty($user->quidax_id)) {
        throw new Exception(
            'User does not have a Quidax account.'
        );
    }

    $asset = $this->currency($asset);

    if ($asset === '') {
        throw new Exception(
            'Wallet asset/currency is required.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Fetch authoritative balance from Quidax
    |--------------------------------------------------------------------------
    */

    $response = $this->fetchUserWallet(
        $user->quidax_id,
        $asset
    );

    if (
        !is_array($response)
        || ($response['status'] ?? null) !== 'success'
    ) {
        Log::error('Failed to synchronize user Quidax wallet.', [
            'user_id'     => $user->id,
            'quidax_id'   => $user->quidax_id,
            'currency'    => $asset,
            'response'    => $response,
        ]);

        throw new Exception(
            $response['message']
                ?? "Unable to fetch Quidax {$asset} wallet."
        );
    }

    $walletData = $response['data'] ?? null;

    if (!is_array($walletData)) {
        Log::error('Invalid Quidax wallet response.', [
            'user_id'   => $user->id,
            'quidax_id' => $user->quidax_id,
            'currency'  => $asset,
            'response'  => $response,
        ]);

        throw new Exception(
            'Invalid wallet data returned by Quidax.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Extract balances
    |--------------------------------------------------------------------------
    */

    $balance = $walletData['balance'] ?? '0';

    $lockedBalance =
        $walletData['locked_balance']
        ?? $walletData['locked']
        ?? '0';

    $quoteCurrency =
        $walletData['quote_unit']
        ?? null;

    /*
    |--------------------------------------------------------------------------
    | Validate balances
    |--------------------------------------------------------------------------
    */

    if (!is_numeric($balance)) {
        Log::error('Invalid Quidax wallet balance.', [
            'user_id'    => $user->id,
            'quidax_id'  => $user->quidax_id,
            'currency'   => $asset,
            'balance'    => $balance,
            'response'   => $response,
        ]);

        throw new Exception(
            'Quidax returned an invalid wallet balance.'
        );
    }

    if (!is_numeric($lockedBalance)) {
        $lockedBalance = '0';
    }

    /*
    |--------------------------------------------------------------------------
    | Update local wallet
    |--------------------------------------------------------------------------
    */

    return DB::transaction(function () use (
        $user,
        $asset,
        $balance,
        $lockedBalance,
        $quoteCurrency
    ) {

        $wallet = UserWallet::query()
            ->where('user_id', $user->id)
            ->where('currency_code', strtoupper($asset))
            ->lockForUpdate()
            ->first();

        if (!$wallet) {
            $wallet = UserWallet::create([
                'user_id'             => $user->id,
                'currency_code'       => strtoupper($asset),
                'balance'             => $balance,
                'reserved'            => $lockedBalance,
                'status'              => true,
                'quote_currency_code' => $quoteCurrency,
            ]);
        } else {
            $wallet->update([
                'balance'             => $balance,
                'reserved'            => $lockedBalance,
                'status'              => true,
                'quote_currency_code' => $quoteCurrency,
            ]);

            $wallet->refresh();
        }

        Log::info('User Quidax wallet synchronized.', [
            'user_id'        => $user->id,
            'quidax_id'      => $user->quidax_id,
            'currency'       => $asset,
            'balance'        => $balance,
            'locked_balance' => $lockedBalance,
        ]);

        return $wallet;
    });
}
    public function getTicker($market)
    {
        return $this->curl->get("v1/markets/tickers/{$market}");
    }

    /**
 * Get withdrawal fee rule.
 *
 * @param string $currency
 * @param string|float $amount
 * @param string $network
 *
 * @return array
 */
public function getWithdrawalFee(
    string $currency,
    $amount,
    string $network
): array {

    $currency = $this->currency($currency);

    $response = $this->curl->get(
        "v1/users/me/fee_rule?" .
        "currency={$currency}" .
        "&amount={$amount}" .
        "&network=" . strtolower(trim($network))
    );

    Log::info('Quidax withdrawal fee lookup.', [
        'currency' => $currency,
        'amount'   => $amount,
        'network'  => $network,
        'response' => $response,
    ]);

    return $response;
}
/**
 * Find a withdrawal by its idempotent reference.
 *
 * Quidax does not expose a dedicated "find withdrawal by reference"
 * endpoint, so we query the user's withdrawals and match the reference
 * locally.
 *
 * @param string $quidaxId
 * @param string $reference
 * @param string $currency
 * @return array|null
 */
/**
 * Find a Quidax withdrawal by its unique reference.
 *
 * Quidax does not expose a dedicated "find withdrawal by reference"
 * endpoint. We therefore query the withdrawal list for each possible
 * state and match the documented `reference` field locally.
 *
 * IMPORTANT:
 * A failure to query Quidax throws an exception. It must NOT be
 * interpreted as "withdrawal does not exist", otherwise the caller
 * could create a duplicate withdrawal.
 */
public function findWithdrawalByReference(
    string $quidaxId,
    string $reference,
    string $currency
): ?array {
    $quidaxId = trim($quidaxId);
    $reference = trim($reference);
    $currency = $this->currency($currency);

    if (
        $quidaxId === ''
        || $reference === ''
        || $currency === ''
    ) {
        return null;
    }

    foreach (['processing', 'done', 'rejected'] as $state) {

        $response = $this->fetch_withdraws(
            $quidaxId,
            $currency,
            $state
        );

        if (!is_array($response)) {
            throw new Exception(
                "Invalid Quidax withdrawal response for state {$state}."
            );
        }

        if (($response['status'] ?? null) !== 'success') {
            throw new Exception(
                $response['message']
                ?? "Unable to fetch Quidax withdrawals for state {$state}."
            );
        }

        $withdrawals = $response['data'] ?? [];

        if (!is_array($withdrawals)) {
            continue;
        }

        foreach ($withdrawals as $withdrawal) {

            if (!is_array($withdrawal)) {
                continue;
            }

            $withdrawalReference = trim(
                (string) ($withdrawal['reference'] ?? '')
            );

            if (
                $withdrawalReference !== ''
                && hash_equals(
                    $reference,
                    $withdrawalReference
                )
            ) {
                return [
                    'status'  => 'success',
                    'message' => 'Withdrawal found.',
                    'data'    => $withdrawal,
                ];
            }
        }
    }

    return null;
}

public function transferFromSubToMain(
    string $subAccountId,
    string $amount,
    string $currency = 'usdt',
    ?string $reference = null,
    ?string $note = null
): array {
    // Get main account ID
    $me = $this->getUser();
    $mainId = $me['data']['id'] ?? null;

    if (!$mainId) {
        throw new \Exception('Unable to fetch main account ID');
    }

    $data = [
        'currency'         => $this->currency($currency),
        'amount'           => $amount,
        'fund_uid'         => $mainId,          // real main account ID
        'transaction_note' => $note ?: 'Internal transfer',
        'narration'        => $note ?: 'Internal transfer',
    ];

    if ($reference) {
        $data['reference'] = $reference;
    }

    // No network needed for internal transfer
    return $this->create_withdrawal($subAccountId, $data);
}
}