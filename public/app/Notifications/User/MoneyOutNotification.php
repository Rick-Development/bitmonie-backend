<?php

namespace App\Notifications\User;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class MoneyOutNotification extends Notification
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

        $user     = $this->user;
        $data     = $this->data;
        $trx_id   = $this->data->trx_id;
        $date     = Carbon::now();
        $dateTime = $date->format('Y-m-d h:i:s A');

        $status = "Pending";

        return (new MailMessage)
            ->greeting("Hello ".$user->fullname." !")
            ->subject("Money out money Via ". $data->gateway_currency->gateway->name.' ('.$data->gateway_currency->gateway->type.' )')
            ->line("Your Money out money request send successfully via ".$data->gateway_currency->gateway->name." , details of Money out:")
            ->line("Transaction Id: " .$trx_id)
            ->line("Request Amount: " . get_amount($data->request_amount).' '.get_default_currency_code())
            ->line("Exchange Rate: " ." 1 ". get_default_currency_code().' = '. get_amount($data->exchange_rate).' '.$data->gateway_currency->currency_code)
            ->line("Fees & Charges: " . get_amount($data->total_charge).' '.get_default_currency_code())
            ->line("Will Get: " .  get_amount($data->receive_amount,$data->gateway_currency->currency_code))
            ->line("Total Payable Amount: " . get_amount($data->total_payable,get_default_currency_code()))
            ->line("Status: ". $status)
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

    public function toDatabase($notifiable)
    {
        $data = $this->data;
        return [
            'title' => "Money Out Initiated",
            'message' => "Your money out request of " . get_amount($data->request_amount) . " " . get_default_currency_code() . " is pending.",
            'amount' => $data->request_amount,
            'transaction_id' => $data->trx_id,
            'type' => 'MONEY_OUT',
        ];
    }
}
