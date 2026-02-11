<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PasswordReset extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @var User
     */
    public $user;

    /**
     * @var string
     */
    public $newPass;

    /**
     * @var mixed
     */
    private $siteEmail;

    /**
     * @var mixed
     */
    private $siteTitle;

    /**
     * PasswordReset constructor.
     */
    public function __construct(User $user, mixed $newPass)
    {
        $this->user = $user;
        $this->newPass = $newPass;
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
        return $this->from($this->siteEmail)->subject('Password reset')->view('emails.passwordReset')->with(['newPass' => $this->newPass, 'userName' => $this->user->username, 'site' => $this->siteTitle]);
    }
}
