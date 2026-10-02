<?php

namespace App\Notifications\User;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LoginNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $ip;
    public $time;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($ip)
    {
        $this->ip = $ip;
        $this->time = now()->toDateTimeString();
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
                    ->subject("Bitmonie - Login Notification")
                    ->greeting("Hello " . $notifiable->firstname)
                    ->line("There was a new login to your account.")
                    ->line("Time: " . $this->time)
                    ->line("IP Address: " . $this->ip)
                    ->line("If this was not you, please contact support immediately.")
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
            'title' => "New Login",
            'message' => "New login detected on your account.",
            'ip' => $this->ip,
            'time' => $this->time,
            'type' => 'USER_LOGIN',
        ];
    }
}
