<?php

namespace App\Mail;

use App\Models\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WelcomeEmail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @var \App\Models\User
     */
    private $user;

    /**
     * Create a new message instance.
     *
     * @param \App\Models\User $user
     */
    public function __construct($user)
    {
        $this->user = $user;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from(Settings::settingValue('site.main.email'))->subject('Welcome to '.Settings::settingValue('site.main.title'))->view('emails.welcome')->with(['username' => $this->user->username, 'site' => Settings::settingValue('site.main.title')]);
    }
}
