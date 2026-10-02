<?php

namespace App\Notifications\User;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SafeHavenCreditNotification extends Notification
{
    use Queueable;

    public $amount;
    public $reference;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($amount, $reference)
    {
        $this->amount = $amount;
        $this->reference = $reference;
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
                    ->subject("Incoming Bank Transfer Received")
                    ->greeting("Hello " . $notifiable->fullname)
                    ->line("You have received an incoming bank transfer.")
                    ->line("Amount: NGN " . $this->amount)
                    ->line("Reference: " . $this->reference)
                    ->line("Your wallet has been credited successfully.")
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
            'title' => "Account Credited",
            'message' => "You received NGN " . $this->amount,
            'amount' => $this->amount,
            'currency' => 'NGN',
            'reference' => $this->reference,
            'type' => 'BANK_TRANSFER_CREDIT',
        ];
    }
}
