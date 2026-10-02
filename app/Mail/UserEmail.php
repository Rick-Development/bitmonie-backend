<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class UserEmail extends Mailable
{
    use Queueable, SerializesModels;

    public string $first_name;
    public string $subjectLine;
    public string $mail_body;

    public function __construct(
        string $first_name,
        string $subject,
        string $mail_body
    ) {
        $this->first_name = $first_name;
        $this->subjectLine = $subject;
        $this->mail_body = $mail_body;
    }

    public function build()
    {
        $siteName = function_exists('basicControl') ? (basicControl()?->site_title ?? config('app.name', 'Bitmonie')) : config('app.name', 'Bitmonie');

        return $this->subject($this->subjectLine)
            ->view('mail-templates.user._user_mail')
            ->with([
                'subject'     => $this->subjectLine,
                'messageBody' => $this->mail_body,
                'name'        => $this->first_name,

                // Optional
                'site_name'   => $siteName,
                'site_url'    => config('app.url'),
                'logo'        => public_path('logo.png'), // change if necessary
            ]);
    }
}