<?php

namespace App\Mail;

use App\Models\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AccountWillExpire extends Mailable
{
    use Queueable, SerializesModels;

    private $days;

    private $user;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($user, $days)
    {
        $this->user = $user;
        $this->days = $days;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from(Settings::settingValue('site.main.email'))->subject('Account about to expire')->view('emails.accountAboutToExpire')->with(['account' => $this->user->role->name, 'username' => $this->user->username, 'site' => Settings::settingValue('site.main.title'), 'days' => $this->days]);
    }
}
