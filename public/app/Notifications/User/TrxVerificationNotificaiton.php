<?php

namespace App\Notifications\User;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TrxVerificationNotificaiton extends Notification
{
    use Queueable;

    protected $data;
    protected $otp_exp_sec;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($data, $otp_exp_sec)
    {
        $this->data = $data;
        $this->otp_exp_sec = $otp_exp_sec;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $fullname = $notifiable->fullname;
        $data = $this->data;
        $otp_exp_sec = $this->otp_exp_sec;
        return (new MailMessage)
                    ->subject("Transaction Verification")
                    ->greeting("Hello ".$fullname . "!")
                    ->line('Need to verify your account before any transaction.')
                    ->line("Your verification code: ".$data->code)
                    ->line("Verification code expire after: ".$otp_exp_sec. ' seconds')
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
            //
        ];
    }
}
