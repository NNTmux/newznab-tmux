<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

/**
 * Branded replacement for Laravel's framework `VerifyEmail` notification.
 *
 * Renders through our themed Markdown mail components (see
 * `resources/views/vendor/mail/*` and `resources/views/emails/markdown/verify.blade.php`)
 * so verification emails match the rest of the transactional email branding
 * instead of falling back to the default Laravel notification look.
 */
class VerifyEmailBranded extends VerifyEmail implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->onQueue((string) config('mail.brand.queue', 'emails'));
    }

    public function toMail($notifiable): MailMessage
    {
        $url = $this->verificationUrl($notifiable);
        $site = (string) config('app.name');
        $prefix = (string) config('mail.brand.subject_prefix', '');
        $username = (string) ($notifiable->username ?? $notifiable->name ?? '');

        return (new MailMessage)
            ->subject($prefix.'Verify your email address')
            ->markdown('emails.markdown.verify', [
                'url' => $url,
                'site' => $site,
                'username' => $username,
                'preheader' => "Confirm your email address to activate your {$site} account.",
            ]);
    }

    protected function verificationUrl($notifiable)
    {
        if (static::$createUrlCallback) {
            return call_user_func(static::$createUrlCallback, $notifiable);
        }

        $origin = $this->normalizedAppUrl();

        if ($origin === null) {
            return parent::verificationUrl($notifiable);
        }

        $parameters = [
            'id' => $notifiable->getKey(),
            'hash' => sha1($notifiable->getEmailForVerification()),
            'expires' => Carbon::now()
                ->addMinutes((int) Config::get('auth.verification.expire', 60))
                ->getTimestamp(),
        ];

        ksort($parameters);

        $unsignedUrl = $origin.URL::route('verification.verify', $parameters, false);
        $key = Config::get('app.key');
        $signature = hash_hmac('sha256', $unsignedUrl, is_array($key) ? $key[0] : (string) $key);

        return $unsignedUrl.(str_contains($unsignedUrl, '?') ? '&' : '?').'signature='.$signature;
    }

    private function normalizedAppUrl(): ?string
    {
        $url = trim((string) Config::get('app.url', ''));

        if ($url === '') {
            return null;
        }

        if (! preg_match('~^https?://~i', $url)) {
            $url = 'https://'.$url;
        }

        return rtrim($url, '/');
    }
}
