<?php

namespace App\Notifications\User;

use Illuminate\Bus\Queueable;
use App\Constants\GlobalConst;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class BeneficiaryNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    private $data;
    private $user;
    private $method;
    public function __construct($data, $user)
    {
        $this->data   = $data;
        $this->user   = $user;
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
        $data   = $this->data;
        $user   = $this->user;

        switch($data->beneficiary_subtype){
            case GlobalConst::TRX_ACCOUNT_NUMBER:
                $full_name      = $data->account_holder_name;
                $account_number = "The account number is ". $data->account_number;
                break;
            default:
                $full_name      = $data->card_holder_name;
                $account_number = "The card number is ". $data->account_number;
        }

        return (new MailMessage)
                    ->subject("Beneficiary Added")
                    ->greeting("Hello ".$user->fullname." !")
                    ->line("New Beneficiary Added For (".$data->method->name.") Beneficiary full name is ".$full_name." ".$account_number)
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
