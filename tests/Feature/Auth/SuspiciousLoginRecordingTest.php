<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Tests\TestCase;

class SuspiciousLoginRecordingTest extends TestCase
{
    private const string LOGIN_ATTEMPTS_MIGRATION = 'migrations/2026_11_19_171205_create_login_attempts_table.php';

    protected function setUp(): void
    {
        parent::setUp();

        config(['suspicious-login.enabled' => true]);
        (require database_path(self::LOGIN_ATTEMPTS_MIGRATION))->up();
    }

    public function test_successful_login_is_stored_in_the_migrated_login_attempts_table(): void
    {
        $user = User::factory()->make(['id' => 42]);

        event(new Login('web', $user, false));

        $this->assertDatabaseHas('login_attempts', [
            'authenticatable_type' => $user->getMorphClass(),
            'authenticatable_id' => 42,
            'successful' => true,
        ]);
    }

    public function test_failed_login_is_stored_with_the_submitted_identifier(): void
    {
        event(new Failed('web', null, ['username' => 'intruder@example.test', 'password' => 'wrong']));

        $this->assertDatabaseHas('login_attempts', [
            'identifier' => 'intruder@example.test',
            'successful' => false,
        ]);
    }
}
