<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ForgottenPassword extends Mailable
{
    use Queueable, SerializesModels;

    public mixed $user;

    public string $resetLink;

    private mixed $siteEmail;

    private mixed $siteTitle;

    /**
     * Create a new message instance.
     */
    public function __construct(string $resetLink)
    {
        $this->resetLink = $resetLink;
        $this->siteEmail = config('mail.from.address');
        $siteName = config('app.name');
        $this->siteTitle = is_array($siteName) ? (string) ($siteName[0] ?? '') : (string) ($siteName ?? '');
    }

    /**
     * Build the message.
     *
     *
     * @throws \Exception
     */
    public function build(): static
    {
        return $this->from($this->siteEmail)->subject('Forgotten password reset')->view('emails.forgottenPassword')->with(['resetLink' => $this->resetLink, 'site' => $this->siteTitle]);
    }
}
