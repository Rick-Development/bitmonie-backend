<?php

namespace App\Notifications\User;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BillPaymentNotification extends Notification
{
    use Queueable;

    public $type;
    public $amount;
    public $provider;
    public $status;
    public $reference;
    public $token;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($type, $amount, $provider, $status, $reference, $token = null)
    {
        $this->type = $type;
        $this->amount = $amount;
        $this->provider = $provider;
        $this->status = $status;
        $this->reference = $reference;
        $this->token = $token;
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
        $mail = (new MailMessage)
                    ->subject($this->type . " Payment Successful")
                    ->greeting("Hello " . $notifiable->fullname)
                    ->line("Your " . $this->type . " payment was successful.")
                    ->line("Amount: " . $this->amount . " NGN")
                    ->line("Provider: " . $this->provider)
                    ->line("Reference: " . $this->reference);

        if (!empty($this->token)) {
            $mail->line("Token/Code: " . $this->token);
        }

        return $mail->line("Status: " . $this->status)
            ->line('Thank you for using Bitmonie!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toDatabase($notifiable)
    {
        return [
            'title' => $this->type . " Payment",
            'message' => "Your payment of " . $this->amount . " NGN for " . $this->type . " (" . $this->provider . ") was successful.",
            'amount' => $this->amount,
            'provider' => $this->provider,
            'reference' => $this->reference,
            'token' => $this->token,
            'token_code' => $this->token,
            'status' => $this->status,
            'type' => 'BILL_PAYMENT_' . strtoupper(str_replace(' ', '_', $this->type)),
        ];
    }
}
