<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\P2POrder;
use App\Models\UserWallet;
use App\Models\P2PEscrow;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\QuidaxService;
use Exception;
use App\Models\User;
class ProcessExpiredP2PEscrows extends Command
{
    protected $signature = 'p2p:process-expired-escrows';
    protected $description = 'Automatically fulfill and release orders that have been in escrow/active status for more than 5 minutes.';
protected QuidaxService $quidax;
   public function __construct(QuidaxService $quidax)
{
    parent::__construct();

    $this->quidax = $quidax;
}
   
public function handle()
    {
        $threshold = Carbon::now()->subMinutes(5);

        // Use chunking to handle large volumes safely without memory exhaustion
        P2POrder::whereIn('status', ['accepted', 'paid', 'funded'])
            ->where('updated_at', '<=', $threshold)
            ->chunkById(100, function ($orders) {
                foreach ($orders as $order) {
                    $this->processOrder($order);
                }
            });

        $this->info('P2P auto-fulfillment sweep completed.');
    }

    private function processOrder(P2POrder $order)
    {
        $this->info("Processing order ID: {$order->id}");

        try {
            DB::transaction(function () use ($order) {
                
                // 1. Lock order for update
                $lockedOrder = P2POrder::where('id', $order->id)->lockForUpdate()->firstOrFail();

                if (!in_array($lockedOrder->status, ['accepted', 'paid', 'funded'], true)) {
                    return; // Skip if status changed concurrently
                }

                // 2. Release Crypto Escrow & Credit Buyer Wallet safely
                $this->releaseCryptoEscrow($lockedOrder);

                // 3. Release Fiat Escrow (Credits the seller, clears buyer escrow balance)
                $this->releaseFiatEscrow($lockedOrder);

                // 4. Complete Order Status
                $lockedOrder->update([
                    'status' => 'completed'
                ]);

                // 5. Broadcast Event safely
                try {
                    event(new \App\Events\P2POrderStatusUpdated($lockedOrder));
                } catch (\Throwable $e) {
                    Log::warning('P2P auto-fulfillment broadcast failed', [
                        'order_id' => $lockedOrder->id,
                        'error' => $e->getMessage(),
                    ]);
                }

                $this->info("Order ID: {$lockedOrder->id} successfully fulfilled and completed.");
            });

        } catch (\Throwable $e) {
            Log::error('Failed to auto-fulfill P2P order', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
            $this->error("Error processing order ID {$order->id}: {$e->getMessage()}");
        }
    }

   
private function releaseCryptoEscrow(P2POrder $order): void
{
    

    /*
    |--------------------------------------------------------------------------
    | Crypto Buyer Quidax Account
    |--------------------------------------------------------------------------
    */

    $buyer = User::findOrFail(
        $order->crypto_buyer_id
    );


    if (!$buyer->quidax_id) {
        throw new Exception(
            'Crypto buyer does not have Quidax account.'
        );
    }



    /*
    |--------------------------------------------------------------------------
    | Release from Platform Escrow
    |--------------------------------------------------------------------------
    */




    /*
    |--------------------------------------------------------------------------
    | Update local escrow
    |--------------------------------------------------------------------------
    */
    $buyerWallet = UserWallet::where('user_id', $order->crypto_buyer_id)
            ->where('currency_code', strtoupper($order->asset))
            ->lockForUpdate()
            ->firstOrFail();

        // Increment buyer's balance using precise math
        $buyerWallet->balance = bcadd($buyerWallet->balance, $order->amount, 8);
        $buyerWallet->save();


        

        // If you track crypto using a P2PEscrow table row like fiat, update it here:

        //trnsfer from #
        if ($order->crypto_escrow_id) {
            
            $cryptoEscrow = P2PEscrow::where('id', $order->crypto_escrow_id)
                ->where('status', 'held')
                ->lockForUpdate()
                ->first();

                
    $response = $this->quidax->releaseFromEscrow(
        $buyer->quidax_id,
        $cryptoEscrow->amount,
        strtolower($cryptoEscrow->asset),
        'P2P_ORDER_'.$order->id
    );



    if (($response['status'] ?? null) !== 'success') {

        Log::error(
            'Crypto escrow release failed',
            [
                'order_id'=>$order->id,
                'response'=>$response,
            ]
        );

        throw new Exception(
            'Unable to release crypto escrow.'
        );
    }

            if ($cryptoEscrow) {
            
             $cryptoEscrow->update([
        'status'=>'released',
        'transaction_ref'=>$response['data']['id'] ?? null
    ]);
            }

   
}
}
    private function releaseFiatEscrow(P2POrder $order)
    {
        if (!$order->fiat_escrow_id) {
            return; // Skip if no fiat escrow is attached
        }

        $escrow = P2PEscrow::where('id', $order->fiat_escrow_id)
            ->where('status', 'held')
            ->lockForUpdate()
            ->firstOrFail();

       

        $sellerWallet = UserWallet::where('user_id', $order->fiat_seller_id)
            ->where('currency_code', $escrow->asset)
            ->lockForUpdate()
            ->firstOrFail();

       

        $sellerWallet->balance = bcadd($sellerWallet->balance, $escrow->amount, 2);

        $sellerWallet->save();

        $escrow->update([
            'status' => 'released'
        ]);
    }
}