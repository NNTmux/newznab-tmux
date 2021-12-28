<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PasswordReset extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @var \App\Models\User
     */
    private $user;

    /**
     * @var string
     */
    private $newPass;

    /**
     * @var mixed
     */
    private $siteEmail;

    /**
     * @var mixed
     */
    private $siteTitle;

    /**
     * PasswordReset constructor.
     *
     * @param  \App\Models\User  $user
     * @param  $newPass
     */
    public function __construct($user, $newPass)
    {
        $this->user = $user;
        $this->newPass = $newPass;
        $this->siteEmail = config('mail.from.address');
        $this->siteTitle = config('app.name');
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
        return $this->from($this->siteEmail)->subject('Password reset')->view('emails.passwordReset')->with(['newPass' => $this->newPass, 'userName' => $this->user->username, 'site' => $this->siteTitle]);
    }
}
