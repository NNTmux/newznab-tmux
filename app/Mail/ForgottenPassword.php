<?php

namespace App\Mail;

use App\Models\User;
use App\Models\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ForgottenPassword extends Mailable
{
    use Queueable, SerializesModels;

    public $user;

    public $resetLink;

    /**
     * Create a new message instance.
     *
     * @param $userId
     * @param $newPass
     */
    public function __construct($userId, $resetLink)
    {
        $this->user = User::query()->where('id', $userId)->first();
        $this->resetLink = $resetLink;
    }

    /**
     * Build the message.
     *
     * @return $this
     * @throws \Exception
     */
    public function build()
    {
        return $this->from(Settings::settingValue('site.main.email'))->subject('Forgotten password reset')->view('emails.forgottenPassword')->with(['resetLink' => $this->resetLink, 'site' => Settings::settingValue('site.main.title')]);
    }
}
