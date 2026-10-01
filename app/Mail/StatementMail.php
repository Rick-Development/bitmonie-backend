<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class StatementMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $filePath;
    public $dateRange;
    public $format;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($user, $filePath, $dateRange, $format = 'pdf')
    {
        $this->user = $user;
        $this->filePath = $filePath;
        $this->dateRange = $dateRange;
        $this->format = $format;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $mime = $this->format === 'csv' ? 'text/csv' : 'application/pdf';
        return $this->subject('Your BitMonie Statement of Account')
                    ->markdown('mail.statement_email')
                    ->attach($this->filePath, [
                        'as' => 'BitMonie_Statement_' . date('Y_m_d') . '.' . $this->format,
                        'mime' => $mime,
                    ]);
    }
}
