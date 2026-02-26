<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AccountExpired extends Mailable
{
    use Queueable, SerializesModels;

    public \App\Models\User $user;

    private mixed $siteEmail;

    private mixed $siteTitle;

    /**
     * Create a new message instance.
     */
    public function __construct(\App\Models\User $user)
    {
        $this->user = $user;
        $this->siteEmail = config('mail.from.address');
        $this->siteTitle = config('app.name');
    }

    /**
     * Build the message.
     *
     *
     * @throws \Exception
     */
    public function build(): static
    {
        return $this->from($this->siteEmail)->subject('Account expired')->view('emails.accountExpired')->with(['account' => $this->user->role->name, 'username' => $this->user->username, 'site' => $this->siteTitle]);
    }
}
