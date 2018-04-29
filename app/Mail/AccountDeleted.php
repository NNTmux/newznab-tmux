<?php

namespace App\Mail;

use App\Models\User;
use App\Models\Settings;
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
     * Create a new message instance.
     *
     * @param $userId
     */
    public function __construct($userId)
    {
        $this->user = User::find($userId);
    }

    /**
     * @return $this
     * @throws \Exception
     */
    public function build()
    {
        return $this->from(Settings::settingValue('site.main.email'))->subject('User Account Deleted')->view('emails.accountDelete')->with(['username' => $this->user->username, 'site' => Settings::settingValue('site.main.title')]);
    }
}
