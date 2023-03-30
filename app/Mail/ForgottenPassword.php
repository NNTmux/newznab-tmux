<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ForgottenPassword extends Mailable
{
    use Queueable, SerializesModels;

    public $user;

    public $resetLink;

    /**
     * @var mixed
     */
    private $siteEmail;

    /**
     * @var mixed
     */
    private $siteTitle;

    /**
     * Create a new message instance.
     */
    public function __construct($resetLink)
    {
        $this->resetLink = $resetLink;
        $this->siteEmail = config('mail.from.address');
        $this->siteTitle = config('app.name');
    }

    /**
     * Build the message.
     *
     *
     * @throws \Exception
     */
    public function build(): static
    {
        return $this->from($this->siteEmail)->subject('Forgotten password reset')->view('emails.forgottenPassword')->with(['resetLink' => $this->resetLink, 'site' => $this->siteTitle]);
    }
}
