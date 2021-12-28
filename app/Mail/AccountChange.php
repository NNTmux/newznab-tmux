<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AccountChange extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @var \App\Models\User
     */
    public $user;

    /**
     * @var mixed
     */
    private $siteEmail;

    /**
     * @var mixed
     */
    private $siteTitle;

    /**
     * AccountChange constructor.
     *
     * @param  \App\Models\User  $user
     */
    public function __construct($user)
    {
        $this->user = $user;
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
        return $this->from($this->siteEmail)->subject('Account Changed')->view('emails.accountChange')->with(['account' => $this->user->role->name, 'username' => $this->user->username, 'site' => $this->siteTitle]);
    }
}
