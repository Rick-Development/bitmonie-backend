<?php

namespace App\Notifications\User\VirtualCard;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class CardFundNotification extends Notification
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
        $this->user     = $user;
        $this->data     = $data;
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
        $data       = $this->data;
        $date       = Carbon::now();
        $dateTime   = $date->format('Y-m-d h:i:s A');
        return (new MailMessage)
            ->greeting(__("Hello")." ".$user->fullname." !")
            ->subject($data->title)
            ->line(__("Transaction ID").": " . $data->trx_id)
            ->line(__("Request Amount").": " . get_amount($data->request_amount,$data->request_currency))
            ->line(__("Fees & Charges").": " . get_amount($data->charges,$data->request_currency))
            ->line(__("Total Payable Amount").": " . get_amount($data->payable,$data->request_currency))
            ->line(__("Card Amount").": " . get_amount($data->card_amount,$data->request_currency))
            ->line(__("Card Pan").": " . $data->card_pan)
            ->line(__("Status").": " . $data->status)
            ->line(__("Date And Time").": " . $dateTime)
            ->line(__('Thank you for using Bitmonie!'));
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
