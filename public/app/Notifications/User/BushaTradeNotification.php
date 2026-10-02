<?php

namespace App\Notifications\User;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BushaTradeNotification extends Notification
{
    use Queueable;

    public $transaction;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($transaction)
    {
        $this->transaction = $transaction;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $trx = $this->transaction;
        $type = strtoupper($trx->type); // BUY or SELL
        $pair = strtoupper($trx->pair);
        $status = strtoupper($trx->status);
        
        $message = (new MailMessage)
                    ->subject("Crypto Trade $status: $type $pair")
                    ->greeting("Hello " . $notifiable->fullname)
                    ->line("Your $type order for $pair has been $status.")
                    ->line("Amount: " . $trx->amount)
                    ->line("Total: " . $trx->total . " " . $trx->currency)
                    ->line("Reference: " . $trx->reference);

        if ($status === 'SUCCESSFUL') {
            $message->line("Your wallet has been updated.");
        }

        return $message->line('Thank you for using Bitmonie!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toDatabase($notifiable)
    {
        $trx = $this->transaction;
        return [
            'title' => "Trade " . strtoupper($trx->type) . " " . strtoupper($trx->status),
            'message' => "Your " . $trx->type . " order for " . $trx->pair . " was " . $trx->status,
            'amount' => $trx->amount,
            'reference' => $trx->reference,
            'status' => $trx->status,
            'type' => 'CRYPTO_' . strtoupper($trx->type),
        ];
    }
}
