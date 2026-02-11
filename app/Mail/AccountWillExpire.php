<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AccountWillExpire extends Mailable
{
    use Queueable, SerializesModels;

    private int $days;

    private \App\Models\User $user;

    private mixed $siteEmail;

    private mixed $siteTitle;

    /**
     * Create a new message instance.
     */
    public function __construct(\App\Models\User $user, int $days)
    {
        $this->user = $user;
        $this->days = $days;
        $this->siteEmail = config('mail.from.address');
        $this->siteTitle = config('app.name');
    }

    /**
     * Build the message.
     */
    public function build(): static
    {
        return $this->from($this->siteEmail)->subject('Account about to expire')->view('emails.accountAboutToExpire')->with(['account' => $this->user->role->name, 'username' => $this->user->username, 'site' => $this->siteTitle, 'days' => $this->days]);
    }
}
