<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RampSellNotification extends Notification
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
        return (new MailMessage)
                    ->subject('Bitmonie Sell Order Initiated')
                    ->line('Your request to sell ' . $this->transaction->from_amount . ' ' . strtoupper($this->transaction->from_currency) . ' for ' . strtoupper($this->transaction->to_currency) . ' has been initiated.')
                    ->line('Reference: ' . $this->transaction->merchant_reference)
                    ->line('Your payout details are being processed.')
                    ->action('View Transaction', url('/'))
                    ->line('Thank you for using Bitmonie!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            'title' => 'Bitmonie Sell Order Initiated',
            'message' => 'Your sell order ' . $this->transaction->merchant_reference . ' is being processed.',
            'transaction_id' => $this->transaction->id,
            'amount' => $this->transaction->from_amount,
            'currency' => $this->transaction->from_currency,
        ];
    }
}
