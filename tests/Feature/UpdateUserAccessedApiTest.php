<?php

namespace Tests\Feature;

use App\Events\UserAccessedApi;
use App\Listeners\UpdateUserAccessedApi;
use stdClass;
use Tests\TestCase;

final class UpdateUserAccessedApiTest extends TestCase
{
    public function test_event_has_ip_property(): void
    {
        // Use a simple stdClass as the event just stores the user reference
        $user = new stdClass;
        $user->id = 1;
        $event = new UserAccessedApi($user, '192.168.1.100');
        $this->assertEquals('192.168.1.100', $event->ip);
        $this->assertSame($user, $event->user);
    }

    public function test_event_ip_is_null_when_not_provided(): void
    {
        $user = new stdClass;
        $user->id = 1;
        $event = new UserAccessedApi($user);
        $this->assertNull($event->ip);
    }

    public function test_listener_is_properly_structured(): void
    {
        // Test that the listener class exists and is properly structured
        $listener = new UpdateUserAccessedApi;
        $this->assertInstanceOf(UpdateUserAccessedApi::class, $listener);
        $this->assertTrue(method_exists($listener, 'handle'));
    }

    public function test_event_stores_user_and_ip_correctly(): void
    {
        $user = new stdClass;
        $user->id = 999;
        $event = new UserAccessedApi($user, '10.0.0.1');
        $this->assertEquals(999, $event->user->id);
        $this->assertEquals('10.0.0.1', $event->ip);
    }
}
