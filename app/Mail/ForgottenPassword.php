<?php

namespace App\Mail;

use App\Models\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ForgottenPassword extends Mailable
{
    use Queueable, SerializesModels;

    public $user;

    public $resetLink;

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
     * @param $resetLink
     */
    public function __construct($resetLink)
    {
        $this->resetLink = $resetLink;
        $this->siteEmail = Settings::settingValue('site.main.email');
        $this->siteTitle = Settings::settingValue('site.main.title');
    }

    /**
     * Build the message.
     *
     * @return $this
     * @throws \Exception
     */
    public function build()
    {
        return $this->from($this->siteEmail)->subject('Forgotten password reset')->view('emails.forgottenPassword')->with(['resetLink' => $this->resetLink, 'site' => $this->siteTitle]);
    }
}
