<?php

namespace App\Notifications\User;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LoanNotification extends Notification
{
    use Queueable;

    public $type;
    public $amount;
    public $asset;
    public $status;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($type, $amount, $asset, $status)
    {
        $this->type = $type;
        $this->amount = $amount;
        $this->asset = $asset;
        $this->status = $status;
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
                    ->subject("Crypto Loan Update: " . $this->type)
                    ->greeting("Hello " . $notifiable->fullname)
                    ->line("There is an update on your loan activity.")
                    ->line("Type: " . $this->type)
                    ->line("Amount: " . $this->amount . " " . $this->asset)
                    ->line("Status: " . $this->status)
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
            'title' => "Loan Update: " . $this->type,
            'message' => "Your " . $this->type . " of " . $this->amount . " " . $this->asset . " is " . $this->status,
            'amount' => $this->amount,
            'asset' => $this->asset,
            'status' => $this->status,
            'type' => 'LOAN_' . strtoupper(str_replace(' ', '_', $this->type)),
        ];
    }
}
