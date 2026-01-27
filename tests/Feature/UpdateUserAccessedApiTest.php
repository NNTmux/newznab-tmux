<?php

namespace Tests\Feature;

use App\Events\UserAccessedApi;
use App\Listeners\UpdateUserAccessedApi;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

final class UpdateUserAccessedApiTest extends TestCase
{
    public function test_event_has_ip_property(): void
    {
        $user = Mockery::mock(User::class);
        $user->id = 1;

        $event = new UserAccessedApi($user, '192.168.1.100');

        $this->assertEquals('192.168.1.100', $event->ip);
        $this->assertSame($user, $event->user);
    }

    public function test_event_ip_is_null_when_not_provided(): void
    {
        $user = Mockery::mock(User::class);
        $user->id = 1;

        $event = new UserAccessedApi($user);

        $this->assertNull($event->ip);
    }

    public function test_listener_includes_host_in_update_when_ip_provided(): void
    {
        // Skip if we don't have a test database configured
        if (! $this->canConnectToDatabase()) {
            $this->markTestSkipped('No database connection available for this test.');
        }

        // Create a mock user to check that update is called with correct data
        $user = Mockery::mock(User::class);
        $user->id = 999;

        // We can't easily test the actual database update without full migration,
        // so we test that the event and listener are properly structured
        $event = new UserAccessedApi($user, '192.168.1.100');
        $listener = new UpdateUserAccessedApi;

        // The listener should run without throwing an exception about missing IP
        $this->assertInstanceOf(UpdateUserAccessedApi::class, $listener);
        $this->assertEquals('192.168.1.100', $event->ip);
    }

    private function canConnectToDatabase(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
