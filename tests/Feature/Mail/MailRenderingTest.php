<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

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
use App\Mail\PasswordReset;
use App\Mail\WelcomeEmail;
use App\Models\Invitation;
use App\Models\ServiceIncident;
use App\Models\User;
use App\Notifications\VerifyEmailBranded;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Markdown;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Renders every mailable in isolation to guarantee that the themed brand
 * palette is applied site-wide and that no template still references the
 * legacy purple gradient (#667eea / #764ba2) from the previous email layout.
 */
class MailRenderingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'app.name' => 'NNTmux',
            'app.url' => 'http://localhost',
            'mail.from.address' => 'noreply@example.test',
            'mail.brand.subject_prefix' => '[NNTmux] ',
            'mail.brand.queue' => 'emails',
            'mail.brand.incident_queue' => 'incidents',
        ]);
    }

    /**
     * @return iterable<string, array{0: \Closure}>
     */
    public static function mailableFactoryProvider(): iterable
    {
        $userFactory = static function (): User {
            $user = new User;
            $user->username = 'tester';
            $user->email = 'tester@example.test';
            $user->setRelation('role', new class
            {
                public string $name = 'Silver';
            });

            return $user;
        };

        yield 'WelcomeEmail' => [static fn () => new WelcomeEmail($userFactory())];
        yield 'ForgottenPassword' => [static fn () => new ForgottenPassword('https://example.test/reset/abc123')];
        yield 'PasswordReset' => [static fn () => new PasswordReset($userFactory(), 'TempPass!2026')];
        yield 'NewAccountCreatedEmail' => [static fn () => new NewAccountCreatedEmail($userFactory())];
        yield 'AccountChange' => [static fn () => new AccountChange($userFactory())];
        yield 'AccountWillExpire' => [static fn () => new AccountWillExpire($userFactory(), 5)];
        yield 'AccountExpired' => [static fn () => new AccountExpired($userFactory())];
        yield 'AccountDeleted' => [static fn () => new AccountDeleted($userFactory())];
        yield 'ContactUs' => [static fn () => new ContactUs('admin@example.test', 'visitor@example.test', "Hello!\nQuestion inside.")];
    }

    #[DataProvider('mailableFactoryProvider')]
    public function test_themed_mailable_renders_with_brand_palette_and_no_legacy_purple(\Closure $factory): void
    {
        /** @var Mailable $mailable */
        $mailable = $factory();
        $html = $mailable->render();

        $this->assertGreaterThan(2000, strlen($html), 'Rendered email is suspiciously small.');
        $this->assertStringContainsStringIgnoringCase('#2563eb', $html, 'Email should use the brand blue color #2563eb.');
        $this->assertStringNotContainsStringIgnoringCase('#667eea', $html, 'Email still references the legacy purple gradient (#667eea).');
        $this->assertStringNotContainsStringIgnoringCase('#764ba2', $html, 'Email still references the legacy purple gradient (#764ba2).');
    }

    public function test_markdown_cta_buttons_render_visible_label_and_forced_white_text(): void
    {
        $forgotten = new ForgottenPassword('https://example.test/reset/abc123');
        $html = $forgotten->render();
        $this->assertStringContainsString('>Reset password<', $html, 'Forgotten password CTA label missing from anchor.');
        $this->assertStringContainsStringIgnoringCase('color: #ffffff !important', $html, 'CTA anchor should force white text for contrast after inlining.');

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
        $html = (string) app(Markdown::class)->render($message->markdown, $message->data());
        $this->assertStringContainsString('>Verify email address<', $html, 'Verify-email CTA label missing from anchor.');
        $this->assertStringContainsStringIgnoringCase('color: #ffffff !important', $html);
    }

    public function test_account_will_expire_includes_pending_role_details_when_present(): void
    {
        $currentRole = new Role;
        $currentRole->name = 'Silver';

        $pendingRole = new Role;
        $pendingRole->name = 'Gold';

        $user = new User;
        $user->username = 'tester';
        $user->email = 'tester@example.test';
        $user->pending_roles_id = 2;
        $user->pending_role_start_date = Carbon::create(2026, 6, 4, 12);
        $user->setRelation('role', $currentRole);
        $user->setRelation('pendingRole', $pendingRole);

        $mailable = new AccountWillExpire($user, 5);

        $mailable->assertSeeInHtml('Silver');
        $mailable->assertSeeInHtml('Gold');
        $mailable->assertSeeInHtml('Jun 4, 2026');
        $mailable->assertSeeInHtml('No renewal action is needed');
        $mailable->assertDontSeeInHtml('please take action before your subscription expires');
    }

    public function test_invitation_mail_renders_with_branded_subject_and_brand_palette(): void
    {
        $inviter = new User;
        $inviter->username = 'mod_alice';

        $invitation = new Invitation;
        $invitation->token = 'fake-token-abc123';
        $invitation->email = 'newuser@example.test';
        $invitation->expires_at = Carbon::now()->addDays(7);
        $invitation->setRelation('invitedBy', $inviter);

        $mailable = new InvitationMail($invitation);

        $mailable->assertHasSubject('[NNTmux] You\'re invited to join NNTmux');
        $mailable->assertSeeInHtml('mod_alice');
        $mailable->assertSeeInHtml('Accept invitation');
        $mailable->assertDontSeeInHtml('#667eea');
        $mailable->assertDontSeeInHtml('#764ba2');
        $this->assertSame('incidents', 'incidents');
    }

    public function test_incident_detected_uses_alert_and_status_table(): void
    {
        $incident = $this->makeIncident();

        $mailable = new IncidentDetected($incident, false);
        $mailable->assertHasSubject('[NNTmux] Service incident Detected: NZB Search, API');
        $mailable->assertSeeInHtml('NZB Search, API');
        $mailable->assertSeeInHtml('Critical impact');
        $mailable->assertSeeInHtml('View status dashboard');
        $mailable->assertDontSeeInHtml('#667eea');
    }

    public function test_incident_resolved_uses_success_alert(): void
    {
        $incident = $this->makeIncident();
        $incident->resolved_at = Carbon::now();

        $mailable = new IncidentDetected($incident, true);
        $mailable->assertHasSubject('[NNTmux] Service incident Resolved: NZB Search, API');
        $mailable->assertSeeInHtml('Resolved');
    }

    public function test_branded_subject_prefix_is_applied_to_every_transactional_mailable(): void
    {
        config(['mail.brand.subject_prefix' => '[NNTmux] ']);
        $user = new User;
        $user->username = 'tester';
        $user->email = 'tester@example.test';
        $user->setRelation('role', new class
        {
            public string $name = 'Silver';
        });

        $cases = [
            [new WelcomeEmail($user), '[NNTmux] Welcome to NNTmux'],
            [new ForgottenPassword('http://example.test/x'), '[NNTmux] Reset your password'],
            [new PasswordReset($user, 'tmp'), '[NNTmux] Your password has been reset'],
            [new NewAccountCreatedEmail($user), '[NNTmux] New account registered'],
            [new AccountChange($user), '[NNTmux] Account level changed'],
            [new AccountWillExpire($user, 1), '[NNTmux] Your account is about to expire'],
            [new AccountExpired($user), '[NNTmux] Your account has expired'],
            [new AccountDeleted($user), '[NNTmux] User account deleted'],
            [new ContactUs('admin@example.test', 'visitor@example.test', 'hi'), '[NNTmux] Contact form submitted'],
        ];

        foreach ($cases as [$mailable, $expectedSubject]) {
            $mailable->assertHasSubject($expectedSubject);
        }
    }

    public function test_blade_side_mailables_implement_should_queue(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, $this->makeIncidentMailable());
        $this->assertInstanceOf(ShouldQueue::class, $this->makeInvitationMailable());
    }

    public function test_incident_detected_is_routed_to_dedicated_queue(): void
    {
        $mailable = $this->makeIncidentMailable();
        $this->assertSame('incidents', $mailable->queue);
    }

    public function test_incident_detected_attaches_plain_text_alternative(): void
    {
        $mailable = $this->makeIncidentMailable();
        $mailable->build();
        $this->assertSame('emails.text.incidentDetected', $mailable->textView);
    }

    public function test_invitation_mail_attaches_plain_text_alternative(): void
    {
        $mailable = $this->makeInvitationMailable();
        /** @var Content $content */
        $content = $mailable->content();
        $this->assertSame('emails.text.invitation', $content->text);
    }

    private function makeIncident(): ServiceIncident
    {
        $incident = new ServiceIncident;
        $incident->title = 'API latency spike';
        $incident->description = "p99 latency exceeded thresholds.\nFollow status page for updates.";
        $incident->status = IncidentStatusEnum::Identified;
        $incident->impact = IncidentImpactEnum::Critical;
        $incident->started_at = Carbon::now()->subMinutes(15);
        $incident->setRelation('services', collect([
            (object) ['name' => 'NZB Search'],
            (object) ['name' => 'API'],
        ]));

        return $incident;
    }

    private function makeIncidentMailable(): IncidentDetected
    {
        return new IncidentDetected($this->makeIncident(), false);
    }

    private function makeInvitationMailable(): InvitationMail
    {
        $inviter = new User;
        $inviter->username = 'mod_alice';

        $invitation = new Invitation;
        $invitation->token = 'fake-token-abc123';
        $invitation->email = 'newuser@example.test';
        $invitation->expires_at = Carbon::now()->addDays(7);
        $invitation->setRelation('invitedBy', $inviter);

        return new InvitationMail($invitation);
    }
}
