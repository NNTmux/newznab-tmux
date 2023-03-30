<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AccountWillExpire extends Mailable
{
    use Queueable, SerializesModels;

    private $days;

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
     *
     * @return void
     */
    public function __construct($user, $days)
    {
        $this->user = $user;
        $this->days = $days;
        $this->siteEmail = config('mail.from.address');
        $this->siteTitle = config('app.name');
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build(): static
    {
        return $this->from($this->siteEmail)->subject('Account about to expire')->view('emails.accountAboutToExpire')->with(['account' => $this->user->role->name, 'username' => $this->user->username, 'site' => $this->siteTitle, 'days' => $this->days]);
    }
}
