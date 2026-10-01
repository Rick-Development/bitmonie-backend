<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RampBuyNotification extends Notification
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
                    ->subject('Bitmonie Buy Order Confirmed')
                    ->line('Your request to buy crypto with ' . $this->transaction->from_amount . ' ' . strtoupper($this->transaction->from_currency) . ' has been received and payment has been processed.')
                    ->line('Reference: ' . $this->transaction->merchant_reference)
                    ->line('Your ' . strtoupper($this->transaction->to_currency) . ' will be delivered to your wallet address once the transaction is confirmed.')
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
            'title' => 'Bitmonie Buy Order Confirmed',
            'message' => 'Your buy order ' . $this->transaction->merchant_reference . ' has been processed successfully.',
            'transaction_id' => $this->transaction->id,
            'amount' => $this->transaction->from_amount,
            'currency' => $this->transaction->from_currency,
        ];
    }
}
