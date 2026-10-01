<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CryptoDepositNotificationMail extends Mailable implements ShouldQueue
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
        $subject = (($this->details['status'] ?? null) === 'failed')
            ? 'Crypto Deposit Failed'
            : 'Crypto Deposit Confirmed';

        return $this->subject($subject)
            ->markdown('emails.crypto.deposit');
    }
}
