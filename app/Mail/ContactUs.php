<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ContactUs extends Mailable
{
    use Queueable, SerializesModels;

    public $mailFrom;

    public $mailBody;

    /**
     * Create a new message instance.
     */
    public function __construct($mailFrom, $mailBody)
    {
        $this->mailFrom = $mailFrom;
        $this->mailBody = $mailBody;
    }

    /**
     * Build the message.
     *
     * @return $this
     *
     * @throws \Exception
     */
    public function build()
    {
        return $this->from($this->mailFrom)->subject('Contact form submitted')->replyTo($this->mailFrom)->view('emails.contactUs')->with(['mailBody' => $this->mailBody]);
    }
}
