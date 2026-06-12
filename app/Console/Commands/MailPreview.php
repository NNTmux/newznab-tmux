<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\IncidentImpactEnum;
use App\Enums\IncidentStatusEnum;
use App\Mail\AccountChange;
use App\Mail\AccountDeleted;
use App\Mail\AccountExpired;
use App\Mail\AccountWillExpire;
use App\Mail\ContactUs;
use App\Mail\ForgottenPassword;
use App\Mail\IncidentDetected;
use App\Mail\InvitationMail;
use App\Mail\NewAccountCreatedEmail;
use App\Mail\WelcomeEmail;
use App\Models\Invitation;
use App\Models\ServiceIncident;
use App\Models\User;
use App\Notifications\VerifyEmailBranded;
use Illuminate\Console\Command;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Markdown;
use Illuminate\Support\Carbon;

/**
 * Generates static HTML previews for every transactional and notification email
 * the application can send. Useful for spot-checking the themed templates
 * across mail clients without needing to wire up Mailpit or trigger real
 * application events. Output lands in `storage/app/mail-previews/*.html`.
 */
class MailPreview extends Command
{
    protected $signature = 'mail:preview
                            {--out= : Override the output directory (defaults to storage/app/mail-previews)}';

    protected $description = 'Render every mailable to static HTML files for visual QA across email clients.';

    public function handle(): int
    {
        $directory = (string) ($this->option('out') ?: storage_path('app/mail-previews'));
        if (! is_dir($directory) && ! mkdir($directory, 0o755, true) && ! is_dir($directory)) {
            $this->components->error("Could not create output directory: {$directory}");

            return self::FAILURE;
        }

        $user = $this->fakeUser();
        $invitation = $this->fakeInvitation();
        [$activeIncident, $resolvedIncident] = $this->fakeIncidents();

        /** @var array<string, callable(): Mailable> $mailables */
        $mailables = [
            'welcome' => fn (): Mailable => new WelcomeEmail($user),
            'forgotten_password' => fn (): Mailable => new ForgottenPassword('https://example.test/reset/abc123'),
            'new_account_created' => fn (): Mailable => new NewAccountCreatedEmail($user),
            'account_change' => fn (): Mailable => new AccountChange($user),
            'account_will_expire' => fn (): Mailable => new AccountWillExpire($user, 5),
            'account_expired' => fn (): Mailable => new AccountExpired($user),
            'account_deleted' => fn (): Mailable => new AccountDeleted($user),
            'contact_us' => fn (): Mailable => new ContactUs(
                'admin@example.test',
                'visitor@example.test',
                "Hello!\nQuestion inside.",
            ),
            'invitation' => fn (): Mailable => new InvitationMail($invitation),
            'incident_detected' => fn (): Mailable => new IncidentDetected($activeIncident, false),
            'incident_resolved' => fn (): Mailable => new IncidentDetected($resolvedIncident, true),
        ];

        foreach ($mailables as $name => $factory) {
            $html = $factory()->render();
            file_put_contents("{$directory}/{$name}.html", $html);
            $this->components->info("Wrote {$name}.html (".number_format(strlen($html)).' bytes)');
        }

        $this->writeVerifyPreview($directory);

        $this->newLine();
        $this->components->info("All previews written to: {$directory}");

        return self::SUCCESS;
    }

    private function writeVerifyPreview(string $directory): void
    {
        $notifiable = new class
        {
            public int $id = 7;

            public string $email = 'tester@example.test';

            public string $username = 'tester';

            public function getKey(): int
            {
                return $this->id;
            }

            public function getEmailForVerification(): string
            {
                return $this->email;
            }
        };

        $message = (new VerifyEmailBranded)->toMail($notifiable);
        $markdown = app(Markdown::class);
        $html = (string) $markdown->render($message->markdown, $message->data());
        file_put_contents("{$directory}/verify_email.html", $html);
        $this->components->info('Wrote verify_email.html ('.number_format(strlen($html)).' bytes)');
    }

    private function fakeUser(): User
    {
        $user = new User;
        $user->id = 99;
        $user->username = 'tester';
        $user->email = 'tester@example.test';
        $user->setRelation('role', new class
        {
            public string $name = 'Silver';
        });

        return $user;
    }

    private function fakeInvitation(): Invitation
    {
        $inviter = new User;
        $inviter->id = 1;
        $inviter->username = 'mod_alice';

        $invitation = new Invitation;
        $invitation->token = 'preview-token-abc123';
        $invitation->email = 'newuser@example.test';
        $invitation->expires_at = Carbon::now()->addDays(7);
        $invitation->setRelation('invitedBy', $inviter);

        return $invitation;
    }

    /**
     * @return array{0: ServiceIncident, 1: ServiceIncident}
     */
    private function fakeIncidents(): array
    {
        $services = collect([
            (object) ['name' => 'NZB Search'],
            (object) ['name' => 'API'],
        ]);

        $active = new ServiceIncident;
        $active->title = 'API latency spike';
        $active->description = "p99 latency exceeded thresholds.\nFollow status page for updates.";
        $active->status = IncidentStatusEnum::Identified;
        $active->impact = IncidentImpactEnum::Critical;
        $active->started_at = Carbon::now()->subMinutes(15);
        $active->setRelation('services', $services);

        $resolved = clone $active;
        $resolved->status = IncidentStatusEnum::Resolved;
        $resolved->resolved_at = Carbon::now();

        return [$active, $resolved];
    }
}
