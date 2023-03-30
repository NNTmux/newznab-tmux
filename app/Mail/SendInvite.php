<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SendInvite extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @var \App\Models\User
     */
    public $user;

    /**
     * @var string
     */
    public $invite;

    /**
     * @var mixed
     */
    private $siteEmail;

    /**
     * @var mixed
     */
    private $siteTitle;

    /**
     * SendInvite constructor.
     */
    public function __construct(User $user, $invite)
    {
        $this->user = $user;
        $this->invite = $invite;
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
        return $this->from($this->siteEmail)->subject('Invite received')->view('emails.sendinvite')->with(['invite' => $this->invite, 'username' => $this->user['username'], 'site' => $this->siteTitle, 'email' => $this->user['email']]);
    }
}
