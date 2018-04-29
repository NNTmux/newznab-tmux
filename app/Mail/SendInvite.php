<?php

namespace App\Mail;

use App\Models\User;
use App\Models\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SendInvite extends Mailable
{
    use Queueable, SerializesModels;

    public $user;

    public $invite;

    /**
     * Create a new message instance.
     *
     * @param $userId
     * @param $invite
     */
    public function __construct($userId, $invite)
    {
        $this->user = User::find($userId);
        $this->invite = $invite;
    }

    /**
     * Build the message.
     *
     * @return $this
     * @throws \Exception
     */
    public function build()
    {
        return $this->from(Settings::settingValue('site.main.email'))->subject('Invite received')->view('emails.sendinvite')->with(['invite' => $this->invite, 'username' => $this->user->username, 'site' => Settings::settingValue('site.main.title'), 'email' => $this->user->email]);
    }
}
