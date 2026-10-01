<?php

namespace App\Notifications\User;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SavingsNotification extends Notification
{
    use Queueable;

    public $planType;
    public $action;
    public $amount;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($planType, $action, $amount)
    {
        $this->planType = $planType;
        $this->action = $action;
        $this->amount = $amount;
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
                    ->subject("Savings Alert: " . $this->planType . " " . $this->action)
                    ->greeting("Hello " . $notifiable->fullname)
                    ->line("Update on your " . $this->planType . " savings.")
                    ->line("Action: " . $this->action)
                    ->line("Amount: " . $this->amount . " NGN") // Assuming NGN default
                    ->line('Thank you for saving with us!');
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
            'title' => "Savings " . $this->action,
            'message' => "Your " . $this->planType . " savings " . strtolower($this->action) . " of " . $this->amount,
            'amount' => $this->amount,
            'plan_type' => $this->planType,
            'action' => $this->action,
            'type' => 'SAVINGS_' . strtoupper($this->action),
        ];
    }
}
