<?php

namespace App\Notifications;

use App\Models\AccountDeletionRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountDeletionRequestSubmitted extends Notification
{
    use Queueable;

    public function __construct(private AccountDeletionRequest $deletionRequest)
    {
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('New Account Deletion Request: ' . $this->deletionRequest->reference_id)
            ->greeting('New account deletion request received')
            ->line('Reference ID: ' . $this->deletionRequest->reference_id)
            ->line('Full Name: ' . $this->deletionRequest->full_name)
            ->line('Email: ' . $this->deletionRequest->email)
            ->line('Phone Number: ' . $this->deletionRequest->phone_number)
            ->line('Reason: ' . ($this->deletionRequest->reason ?: 'Not provided'))
            ->line('Submitted At: ' . $this->deletionRequest->created_at->format('Y-m-d H:i:s'));
    }

    public function toArray($notifiable)
    {
        return [];
    }
}
