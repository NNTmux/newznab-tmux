<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ContactUs extends Mailable
{
    use Queueable, SerializesModels;

    public string $mailFrom;

    public string $mailBody;

    public string $mailTo;

    /**
     * Create a new message instance.
     */
    public function __construct(mixed $mailTo, mixed $mailFrom, mixed $mailBody)
    {
        $this->mailTo = $mailTo;
        $this->mailFrom = $mailFrom;
        $this->mailBody = $mailBody;
    }

    /**
     * Build the message.
     *
     *
     * @throws \Exception
     */
    public function build(): static
    {
        return $this->from($this->mailTo)->subject('Contact form submitted')->replyTo($this->mailFrom)->view('emails.contactUs')->with(['mailBody' => $this->mailBody]);
    }
}
