<?php

namespace App\Mail;

use App\Models\Settings;
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
     * PasswordReset constructor.
     *
     * @param \App\Models\User $user
     * @param                  $newPass
     */
    public function __construct($user, $newPass)
    {
        $this->user = $user;
        $this->newPass = $newPass;
    }

    /**
     * Build the message.
     *
     * @return $this
     * @throws \Exception
     */
    public function build()
    {
        return $this->from(Settings::settingValue('site.main.email'))->subject('Password reset')->view('emails.passwordReset')->with(['newPass' => $this->newPass, 'userName' => $this->user->username, 'site' => Settings::settingValue('site.main.title')]);
    }
}
