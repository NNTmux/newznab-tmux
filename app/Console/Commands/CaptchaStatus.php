<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CaptchaStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'captcha:status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check CAPTCHA configuration status';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('CAPTCHA Configuration Status');
        $this->info('================================');
        $this->newLine();

        $provider = config('captcha.provider', 'recaptcha');
        $this->line("Active Provider: <fg=cyan>{$provider}</>");
        $this->newLine();

        // Check reCAPTCHA
        $this->info('Google reCAPTCHA:');
        $recaptchaEnabled = config('captcha.recaptcha.enabled');
        $recaptchaSitekey = config('captcha.recaptcha.sitekey');
        $recaptchaSecret = config('captcha.recaptcha.secret');

        $this->line('  Enabled: '.($recaptchaEnabled ? '<fg=green>Yes</>' : '<fg=red>No</>'));
        $this->line('  Site Key: '.(! empty($recaptchaSitekey) ? '<fg=green>Configured</>' : '<fg=red>Missing</>'));
        $this->line('  Secret: '.(! empty($recaptchaSecret) ? '<fg=green>Configured</>' : '<fg=red>Missing</>'));
        $this->newLine();

        // Check Turnstile
        $this->info('Cloudflare Turnstile:');
        $turnstileEnabled = config('captcha.turnstile.enabled');
        $turnstileSitekey = config('captcha.turnstile.sitekey');
        $turnstileSecret = config('captcha.turnstile.secret');

        $this->line('  Enabled: '.($turnstileEnabled ? '<fg=green>Yes</>' : '<fg=red>No</>'));
        $this->line('  Site Key: '.(! empty($turnstileSitekey) ? '<fg=green>Configured</>' : '<fg=red>Missing</>'));
        $this->line('  Secret: '.(! empty($turnstileSecret) ? '<fg=green>Configured</>' : '<fg=red>Missing</>'));
        $this->newLine();

        // Validation
        $recaptchaReady = $recaptchaEnabled && ! empty($recaptchaSitekey) && ! empty($recaptchaSecret);
        $turnstileReady = $turnstileEnabled && ! empty($turnstileSitekey) && ! empty($turnstileSecret);

        if ($recaptchaReady && $turnstileReady) {
            $this->error('⚠ WARNING: Both providers are enabled!');
            $this->warn('Only one CAPTCHA provider should be enabled at a time.');
            $this->warn("The system will use: {$provider}");
            $this->newLine();
        }

        if ($provider === 'recaptcha' && $recaptchaReady) {
            $this->info('✓ reCAPTCHA is properly configured and active');
        } elseif ($provider === 'turnstile' && $turnstileReady) {
            $this->info('✓ Turnstile is properly configured and active');
        } elseif ($provider === 'recaptcha' && ! $recaptchaReady) {
            $this->error('✗ reCAPTCHA is selected but not properly configured');
        } elseif ($provider === 'turnstile' && ! $turnstileReady) {
            $this->error('✗ Turnstile is selected but not properly configured');
        } else {
            $this->warn('⚠ No CAPTCHA provider is active');
        }

        $this->newLine();
        $this->comment('To change providers, update CAPTCHA_PROVIDER in your .env file');
        $this->comment('Then run: php artisan config:clear');

        return Command::SUCCESS;
    }
}
