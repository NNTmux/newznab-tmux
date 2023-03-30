<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NewAccountCreatedEmail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @var \App\Models\User
     */
    private $user;

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
     *
     * @return void
     */
    public function __construct($user)
    {
        $this->user = $user;
        $this->siteEmail = config('mail.from.address');
        $this->siteTitle = config('app.name');
    }

    /**
     * Build the message.
     */
    public function build(): static
    {
        return $this->from($this->siteEmail)->subject('New account registered')->view('emails.newAccountCreated')->with(['username' => $this->user->username, 'site' => $this->siteTitle]);
    }
}
