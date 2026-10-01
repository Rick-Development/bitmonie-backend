<?php

namespace App\Notifications\User\FundTransfer;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class OwnBankReceiverNotification extends Notification
{
    use Queueable;

    public $user;
    public $data;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($user,$data)
    {
        $this->user = $user;
        $this->data = $data;
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

        $user     = $this->user;
        $data     = $this->data;
        $trx_id   = $this->data->trx_id;
        $date     = Carbon::now();
        $dateTime = $date->format('Y-m-d h:i:s A');

        return (new MailMessage)
                ->greeting("Hello ".$user->fullname." !")
                ->subject("Fund Received Successful")
                ->line("Your Fund Transfer request send successfully")
                ->line("Transaction Id: " .$trx_id)
                ->line("Received Amount: " .  get_amount($data->receive_amount, $data->payment_currency))
                ->line("Sender Full Name: " .  $user->fullName)
                ->line("Sender Account Number: " .  $user->account_no)
                ->line("Date And Time: " .$dateTime)
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
