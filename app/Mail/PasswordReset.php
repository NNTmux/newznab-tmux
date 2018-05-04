<?php

namespace App\Mail;

use App\Models\User;
use App\Models\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PasswordReset extends Mailable
{
    use Queueable, SerializesModels;

    public $user;

    public $newPass;

    /**
     * Create a new message instance.
     *
     * @param $userId
     * @param $newPass
     */
    public function __construct($userId, $newPass)
    {
        $this->user = User::find($userId);
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
