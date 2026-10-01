<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CryptoWithdrawalNotificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public User $user;
    public array $details;

    public function __construct(User $user, array $details)
    {
        $this->user = $user;
        $this->details = $details;
    }

    public function build()
    {
        $status = ucfirst((string) ($this->details['status'] ?? 'Updated'));

        return $this->subject("Crypto Withdrawal {$status}")
            ->markdown('emails.crypto.withdrawal');
    }
}
