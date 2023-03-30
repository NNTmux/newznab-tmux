<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AccountDeleted extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @var \Illuminate\Database\Eloquent\Model|null|object|static
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
     */
    public function __construct($user)
    {
        $this->user = $user;
        $this->siteEmail = config('mail.from.address');
        $this->siteTitle = config('app.name');
    }

    /**
     *
     * @throws \Exception
     */
    public function build(): static
    {
        return $this->from($this->siteEmail)->subject('User Account Deleted')->view('emails.accountDelete')->with(['username' => $this->user->username, 'site' => $this->siteTitle]);
    }
}
