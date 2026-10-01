<?php

namespace App\Notifications\Admin;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class SalaryDisbursementNotification extends Notification
{
    use Queueable;
    public $user;
    public $amount;
    public $admin;
    public $trx_id;
    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($user,$amount,$admin,$trx_id)
    {
        $this->user     = $user;
        $this->amount   = $amount;
        $this->admin    = $admin;
        $this->trx_id   = $trx_id;
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
        $user       = $this->user;
        $amount     = $this->amount;
        $admin      = $this->admin;
        $trx_id     = $this->trx_id;

        $date = Carbon::now();
        $dateTime = $date->format('Y-m-d h:i:s A');
        return (new MailMessage)
            ->greeting("Hello ". $user ." !")
            ->subject("Salary Disbursement By ". $admin)
            ->line("Amount: " . get_amount($amount,get_default_currency_code()))
            ->line("Transaction Id: " . $trx_id)
            ->line("Date And Time: " . $dateTime)
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
