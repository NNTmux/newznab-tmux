<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Models\User;
use App\Notifications\VerifyEmailBranded;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use stdClass;
use Tests\TestCase;

class VerifyEmailBrandedTest extends TestCase
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
        ]);
    }

    public function test_branded_verification_extends_framework_notification_for_compatibility(): void
    {
        $this->assertInstanceOf(VerifyEmail::class, new VerifyEmailBranded);
    }

    public function test_branded_verification_implements_should_queue(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, new VerifyEmailBranded);
    }

    public function test_branded_verification_renders_through_themed_markdown_template(): void
    {
        $notifiable = new class extends stdClass
        {
            public int $id = 7;

            public string $username = 'tester';

            public string $email = 'tester@example.test';

            public function getKey(): int
            {
                return $this->id;
            }

            public function getEmailForVerification(): string
            {
                return $this->email;
            }
        };

        $notification = new VerifyEmailBranded;
        /** @var MailMessage $message */
        $message = $notification->toMail($notifiable);

        $this->assertSame('[NNTmux] Verify your email address', $message->subject);
        $this->assertSame('emails.markdown.verify', $message->markdown);
        $this->assertArrayHasKey('site', $message->viewData);
        $this->assertSame('NNTmux', $message->viewData['site']);
        $this->assertSame('tester', $message->viewData['username']);
        $this->assertNotEmpty($message->viewData['url']);
    }

    public function test_branded_verification_uses_configured_app_url_for_signed_links(): void
    {
        config(['app.url' => 'https://example.test']);

        $message = (new VerifyEmailBranded)->toMail($this->verificationNotifiable());
        $url = (string) $message->viewData['url'];

        $this->assertStringStartsWith('https://example.test/email/verify/', $url);
        $this->assertStringNotContainsString('localhost', $url);
        $this->assertTrue(URL::hasValidSignature(Request::create($url)));
    }

    public function test_branded_verification_normalizes_host_only_app_url_for_signed_links(): void
    {
        config(['app.url' => 'example.test']);

        $message = (new VerifyEmailBranded)->toMail($this->verificationNotifiable());
        $url = (string) $message->viewData['url'];

        $this->assertStringStartsWith('https://example.test/email/verify/', $url);
        $this->assertStringNotContainsString('localhost', $url);
        $this->assertTrue(URL::hasValidSignature(Request::create($url)));
    }

    public function test_user_dispatches_branded_verification_when_notifying(): void
    {
        Notification::fake();

        $user = new User;
        $user->id = 99;
        $user->email = 'verify@example.test';
        $user->username = 'verifyuser';

        $user->sendEmailVerificationNotification();

        Notification::assertSentTo($user, VerifyEmailBranded::class);
    }

    private function verificationNotifiable(): object
    {
        return new class extends stdClass
        {
            public int $id = 7;

            public string $username = 'tester';

            public string $email = 'tester@example.test';

            public function getKey(): int
            {
                return $this->id;
            }

            public function getEmailForVerification(): string
            {
                return $this->email;
            }
        };
    }
}
