<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class QuidaxService
{
    protected $baseUrl;
    protected $rampUrl;
    protected $p2pUrl;
    protected $apiSecret;
    protected $curl;

    public function __construct()
    {
        $this->baseUrl = config('services.quidax.url');
        $this->rampUrl = config('services.quidax.ramp_url');
        $this->p2pUrl = config('services.quidax.p2p_url');
        $this->apiSecret = config('services.quidax.secret');
        

        $this->curl = new CurlService(
            $this->baseUrl,
            $this->apiSecret,
            $this->rampUrl,
            $this->p2pUrl,
        );
    }


    public function createSubAccount($data){
        //  [
        //     'email' => 'test@gmail.com',
        //     'first_name' => 'test',
        //     'last_name' => 'user'
        //   ]
        return $this->curl->post("v1/users",$data);
    }
    public function getUser()
    {
        return $this->curl->get("v1/users/me");
    }

    public function fetchUserWallets($quidax_id)
    {
        return $this->curl->get("v1/users/{$quidax_id}/wallets");
    }

    public function fetchUserWallet($quidax_id,$currency)
    {
        return $this->curl->get("v1/users/{$quidax_id}/wallets/{$currency}");
    }

    public function fetchPaymentAddress($quidax_id,$currency){
        return $this->curl->get("v1/users/{$quidax_id}/wallets/{$currency}/address");
    }
    public function fetchPaymentAddressses($quidax_id,$currency){
        return $this->curl->get("v1/users/{$quidax_id}/wallets/{$currency}/addresses");
    }


    public function createCryptoPaymentAddress($quidax_id,$currency,$data){
          return $this->curl->post("v1/users/{$quidax_id}/wallets/{$currency}/addresses?network={$data}");
    }

    public function fetch_withdraws($quidax_id, $currency, $status)
    {
        return $this->curl->get("v1/users/{$quidax_id}/withdraws?order_by=asc&currency={$currency}&state={$status}");
    }

    public function cancel_withdrawal($quidax_id, $withdrawal_id)
    {
        return $this->curl->get("v1/users/{$quidax_id}/withdraws/{$withdrawal_id}/cancel");
    }

    public function create_withdrawal($quidax_id, $data)
    {
        return $this->curl->post("v1/users/$quidax_id/withdraws", $data);
    }

    public function createSwapQuotation($quidax_id,$data){
          return $this->curl->post("v1/users/{$quidax_id}/swap_quotation",$data);
    }

    public function swap($quidax_id,$quotation_id){
          return $this->curl->post("v1/users/{$quidax_id}/swap_quotation/{$quotation_id}/confirm");
    }

    public function refresh_instant_swap_quotation($quidax_id, $quotation_id, $data)
    {
        return $this->curl->post("v1/users/{$quidax_id}/swap_quotation/{$quotation_id}/refresh", $data);
    }

    public function fetch_swap_transaction($quidax_id, $swap_transaction_id)
    {
        return $this->curl->get("v1/users/{$quidax_id}/swap_transactions/{$swap_transaction_id}");
    }

    public function initiate_ramp_transaction($data)
    {
        return $this->curl->post("v1/merchants/custodial/on_ramp_transactions/initiate", $data);
    }

    public function get_swap_transacdtion($quidax_id)
    {
        return $this->curl->get("v1/users/{$quidax_id}/swap_transactions");
    }

    public function temporary_swap_quotation($quidax_id, $data)
    {
        return $this->curl->post("v1/users/{$quidax_id}/temporary_swap_quotation", $data);
    }

    public function fetch_deposits($quidax_id, $currency, $state)
    {
        return $this->curl->get("v1/users/{$quidax_id}/deposits?currency={$currency}&state={$state}");
    }

    public function fetch_a_deposit($quidax_id, $deposit_id)
    {
        return $this->curl->get("v1/users/{$quidax_id}/deposits/{$deposit_id}");
    }

    public function get_all_public_adverts($data)
    {
        return $this->curl->get("v1/p2p/adverts", $data);
    }

    public function get_single_public_advert($advert_id)
    {
        return $this->curl->get("v1/p2p/adverts/{$advert_id}");
    }

    // --- Instant Orders ---

    public function createInstantOrder($quidax_id, $data)
    {
        return $this->curl->post("v1/users/{$quidax_id}/instant_orders", $data);
    }

    public function confirmInstantOrder($quidax_id, $order_id)
    {
        return $this->curl->post("v1/users/{$quidax_id}/instant_orders/{$order_id}/confirm");
    }

    public function requoteInstantOrder($quidax_id, $order_id)
    {
        return $this->curl->post("v1/users/{$quidax_id}/instant_orders/{$order_id}/requote");
    }

    public function getInstantOrder($quidax_id, $order_id)
    {
        return $this->curl->get("v1/users/{$quidax_id}/instant_orders/{$order_id}");
    }

    public function getInstantOrders($quidax_id)
    {
        return $this->curl->get("v1/users/{$quidax_id}/instant_orders");
    }

    /**
     * Transfer funds from Main Account to Sub Account (Internal Transfer)
     */
    /**
     * Transfer funds from Main Account to Sub Account (Internal Transfer)
     * Endpoint: POST /users/{user_id}/wallets/{currency}/transfer (Hypothetical strict path) or /users/transfer
     */
    public function fundSubAccount($quidax_id, $amount, $currency)
    {
        // Using the generic transfer endpoint usually moves from Main -> Recipient
        // Payload: { "recipient_id": "...", "amount": "...", "currency": "..." }
        $data = [
            'recipient_id' => $quidax_id,
            'amount' => $amount,
            'currency' => $currency
        ];
        return $this->curl->post("v1/users/transfer", $data);
    }

    public function transfer($from_quidax_id, $to_quidax_id, $amount, $currency)
    {
        $data = [
            'recipient_id' => $to_quidax_id,
            'amount' => $amount,
            'currency' => $currency
        ];
        return $this->curl->post("v1/users/{$from_quidax_id}/transfer", $data);
    }

    /**
     * Transfer funds from Sub Account to Escrow (Main Account)
     * Strategy: Withdraw from Sub-Account -> Main Account Wallet Address
     */
    public function transferToEscrow($quidax_id, $amount, $currency, ?string $transactionNote = null)
    {
        // 1. Get Main Account Wallet Address dynamically via API
        // We use 'me' to refer to the account owning the API Key (The Main Account)
        try {
            $addressResponse = $this->fetchPaymentAddress('me', $currency);
            $escrowAddress = $addressResponse['data']['address'] ?? null;
            
            if (!$escrowAddress) {
                // Try create if not exists? Or just fetch addresses list?
                // Some wallets need 'address_creation' first. Assuming it exists for Main Account.
                 throw new \Exception("Could not fetch Main Account Address for {$currency}");
            }
        } catch (\Exception $e) {
             throw new \Exception("Escrow Setup Error: " . $e->getMessage());
        }

        $data = [
            'currency' => $currency,
            'amount' => $amount,
            'fund_uid' => $escrowAddress, // Destination Address
            'transaction_note' => $transactionNote ?: 'P2P Escrow Lock'
        ];

        // 2. Perform Withdrawal from User's Sub-Account context
        return $this->create_withdrawal($quidax_id, $data);
    }

    public function getWithdrawalFee(
    string $currency,
    $amount,
    string $network
): array {
    $currency = strtolower(trim($currency));
    $network = strtolower(trim($network));

    return $this->curl->get(
        "v1/users/me/fee_rule?" .
        "currency={$currency}" .
        "&amount={$amount}" .
        "&network={$network}"
    );
}

    public function getTicker($market)
    {
        return $this->curl->get("v1/tickers/{$market}");
    }

}
