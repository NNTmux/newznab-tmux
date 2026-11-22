<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\BlockedIp;
use App\Models\LoginAttempt;
use App\Services\Security\IpBlockService;
use App\Services\Security\LoginAbuseDetector;
use Illuminate\Auth\Events\Failed;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LoginAbuseDetectorTest extends TestCase
{
    private const string IP = '203.0.113.50';

    private const string SECOND_IP = '203.0.113.60';

    private const string THIRD_IP = '203.0.113.70';

    private const string DEVICE = 'device-a';

    private const int EXISTING_ACCOUNT_ID = 77;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'suspicious-login.enabled' => true,
            'nntmux.ip_blocking.enabled' => true,
            'nntmux.ip_blocking.auto_block' => true,
            'nntmux.ip_blocking.unknown_account_threshold' => 2,
            'nntmux.ip_blocking.signed_in_unknown_account_threshold' => 3,
            'nntmux.ip_blocking.window_hours' => 24,
            'nntmux.ip_blocking.block_hours' => 24,
            'nntmux.ip_blocking.max_block_hours' => 720,
            'nntmux.ip_blocking.allowlist' => [],
            'trustedproxy.cloudflare.enabled' => false,
            'trustedproxy.proxies' => null,
        ]);

        DB::purge();
        DB::reconnect();
        Cache::flush();

        $this->createSchema();
        $this->travelTo('2026-11-21 12:00:00');
    }

    #[Test]
    public function an_address_that_tries_two_different_non_existent_accounts_is_blocked_for_a_day(): void
    {
        $this->unknownAccountAttempts(self::IP, ['ghost-one', 'ghost@example.test']);

        $created = app(LoginAbuseDetector::class)->evaluate(self::IP);

        $this->assertCount(1, $created);
        $this->assertSame(self::IP, $created[0]->ip_address);
        $this->assertSame('2026-11-22 12:00:00', $created[0]->expires_at?->toDateTimeString());
        $this->assertSame('Tried 2 accounts that do not exist within 24 hours', $created[0]->reason);
        $this->assertTrue(app(IpBlockService::class)->isBlocked(self::IP));
    }

    #[Test]
    public function two_mistyped_names_from_an_address_that_signed_in_successfully_are_not_enough(): void
    {
        $this->successfulSignIn(['ip_address' => self::IP]);
        $this->unknownAccountAttempts(self::IP, ['ghost-one', 'ghost-two']);

        $this->assertNotBlocked(self::IP);
    }

    #[Test]
    public function a_third_non_existent_account_blocks_an_address_that_signed_in_successfully(): void
    {
        $this->successfulSignIn(['ip_address' => self::IP]);
        $this->unknownAccountAttempts(self::IP, ['ghost-one', 'ghost-two', 'ghost-three']);

        $created = app(LoginAbuseDetector::class)->evaluate(self::IP);

        $this->assertCount(1, $created);
        $this->assertSame('Tried 3 accounts that do not exist within 24 hours', $created[0]->reason);
    }

    #[Test]
    public function a_successful_sign_in_on_the_same_device_from_another_address_also_raises_the_limit(): void
    {
        $this->successfulSignIn(['ip_address' => self::SECOND_IP, 'device_fingerprint' => self::DEVICE]);
        $this->unknownAccountAttempts(self::IP, ['ghost-one', 'ghost-two'], ['device_fingerprint' => self::DEVICE]);

        $this->assertSame([], app(LoginAbuseDetector::class)->evaluate(self::IP, self::DEVICE));
        $this->assertFalse(app(IpBlockService::class)->isBlocked(self::IP));
    }

    #[Test]
    public function a_successful_sign_in_older_than_the_window_does_not_raise_the_limit(): void
    {
        $this->successfulSignIn(['ip_address' => self::IP, 'created_at' => now()->subHours(25)]);
        $this->unknownAccountAttempts(self::IP, ['ghost-one', 'ghost-two']);

        $this->assertCount(1, app(LoginAbuseDetector::class)->evaluate(self::IP));
    }

    #[Test]
    public function retrying_the_same_wrong_name_does_not_add_up(): void
    {
        $this->unknownAccountAttempts(self::IP, ['ghost-one', 'ghost-one', 'GHOST-ONE', ' ghost-one ']);

        $this->assertNotBlocked(self::IP);
    }

    #[Test]
    public function wrong_passwords_on_existing_accounts_do_not_count(): void
    {
        $this->unknownAccountAttempts(self::IP, ['ghost-one']);
        foreach (['alice', 'bob', 'carol'] as $username) {
            $this->recordAttempt([
                'identifier' => $username,
                'authenticatable_type' => 'App\\Models\\User',
                'authenticatable_id' => self::EXISTING_ACCOUNT_ID,
            ]);
        }

        $this->assertNotBlocked(self::IP);
    }

    #[Test]
    public function attempts_older_than_the_window_do_not_count(): void
    {
        $this->unknownAccountAttempts(self::IP, ['ghost-one'], ['created_at' => now()->subHours(25)]);
        $this->unknownAccountAttempts(self::IP, ['ghost-two']);

        $this->assertNotBlocked(self::IP);
    }

    #[Test]
    public function a_device_that_switches_address_between_guesses_gets_every_address_it_used_blocked(): void
    {
        $this->unknownAccountAttempts(self::IP, ['ghost-one'], ['device_fingerprint' => self::DEVICE]);
        $this->unknownAccountAttempts(self::SECOND_IP, ['ghost-one'], ['device_fingerprint' => self::DEVICE]);
        $this->unknownAccountAttempts(self::THIRD_IP, ['ghost-two'], ['device_fingerprint' => self::DEVICE]);

        $created = app(LoginAbuseDetector::class)->evaluate(self::THIRD_IP, self::DEVICE);

        $blocked = array_map(static fn (BlockedIp $block): string => $block->ip_address, $created);
        sort($blocked);
        $this->assertSame([self::IP, self::SECOND_IP, self::THIRD_IP], $blocked);
        $this->assertSame(self::DEVICE, $created[0]->device_fingerprint);
    }

    #[Test]
    public function another_device_on_another_address_is_not_blocked_along_with_it(): void
    {
        $this->unknownAccountAttempts(self::IP, ['ghost-one', 'ghost-two', 'ghost-three'], ['device_fingerprint' => self::DEVICE]);
        $this->unknownAccountAttempts(self::SECOND_IP, ['typo@example.test'], ['device_fingerprint' => 'device-b']);

        app(LoginAbuseDetector::class)->evaluate(self::IP, self::DEVICE);

        $this->assertTrue(app(IpBlockService::class)->isBlocked(self::IP));
        $this->assertFalse(app(IpBlockService::class)->isBlocked(self::SECOND_IP));
    }

    #[Test]
    public function sign_ins_to_deleted_accounts_do_not_count(): void
    {
        DB::table('users')->insert([
            ['username' => 'FormerMember', 'email' => 'former@example.test', 'password' => 'x', 'deleted_at' => now()->subMonth()],
        ]);
        $this->unknownAccountAttempts(self::IP, ['formermember', 'Former@Example.test', 'ghost-one']);

        $this->assertNotBlocked(self::IP);
    }

    #[Test]
    public function a_private_address_is_never_blocked(): void
    {
        $this->unknownAccountAttempts('10.0.0.8', ['ghost-one', 'ghost-two', 'ghost-three']);

        $this->assertNotBlocked('10.0.0.8');
    }

    #[Test]
    public function a_repeat_offence_after_the_block_ended_is_blocked_for_twice_as_long(): void
    {
        $this->unknownAccountAttempts(self::IP, ['ghost-one', 'ghost-two', 'ghost-three']);
        app(LoginAbuseDetector::class)->evaluate(self::IP);
        $this->travel(25)->hours();
        $this->unknownAccountAttempts(self::IP, ['ghost-four', 'ghost-five', 'ghost-six']);

        $created = app(LoginAbuseDetector::class)->evaluate(self::IP);

        $this->assertCount(1, $created);
        $this->assertSame(2, $created[0]->block_count);
        $this->assertSame(48, (int) now()->diffInHours($created[0]->expires_at));
    }

    #[Test]
    public function one_more_mistake_after_a_block_was_lifted_does_not_block_the_address_again(): void
    {
        $this->unknownAccountAttempts(self::IP, ['ghost-one', 'ghost-two', 'ghost-three'], ['device_fingerprint' => self::DEVICE]);
        app(LoginAbuseDetector::class)->evaluate(self::IP, self::DEVICE);
        $this->travel(10)->minutes();
        app(IpBlockService::class)->lift(self::IP);
        $this->travel(10)->minutes();
        $this->unknownAccountAttempts(self::IP, ['ghost-four'], ['device_fingerprint' => self::DEVICE]);

        $this->assertSame([], app(LoginAbuseDetector::class)->evaluate(self::IP, self::DEVICE));
        $this->assertFalse(app(IpBlockService::class)->isBlocked(self::IP));
    }

    #[Test]
    public function two_failed_sign_ins_to_unknown_accounts_block_the_address_as_they_are_recorded(): void
    {
        $this->app['request']->server->set('REMOTE_ADDR', self::IP);

        event(new Failed('web', null, ['username' => 'ghost-one', 'password' => 'wrong']));
        $this->assertFalse(app(IpBlockService::class)->isBlocked(self::IP));

        event(new Failed('web', null, ['email' => 'ghost@example.test', 'password' => 'wrong']));

        $this->assertTrue(app(IpBlockService::class)->isBlocked(self::IP));
    }

    #[Test]
    public function recorded_attempts_block_nobody_while_automatic_blocking_is_switched_off(): void
    {
        config(['nntmux.ip_blocking.auto_block' => false]);
        $this->app['request']->server->set('REMOTE_ADDR', self::IP);

        foreach (['ghost-one', 'ghost-two', 'ghost-three'] as $username) {
            event(new Failed('web', null, ['username' => $username, 'password' => 'wrong']));
        }

        $this->assertSame(3, LoginAttempt::query()->count());
        $this->assertFalse(app(IpBlockService::class)->isBlocked(self::IP));
    }

    #[Test]
    public function the_scan_dry_run_lists_offenders_without_blocking_them(): void
    {
        $this->unknownAccountAttempts(self::IP, ['ghost-one', 'ghost-two', 'ghost-three']);
        $this->unknownAccountAttempts(self::SECOND_IP, ['typo@example.test']);

        $this->artisan('ip-block:scan', ['--dry-run' => true])
            ->expectsTable(
                ['IP address', 'Non-existent accounts tried', 'Result'],
                [[self::IP, 3, 'would be blocked']],
            )
            ->assertSuccessful();

        $this->assertSame(0, BlockedIp::query()->count());
    }

    #[Test]
    public function the_scan_blocks_offenders_found_in_recorded_attempts(): void
    {
        $this->unknownAccountAttempts(self::IP, ['ghost-one', 'ghost-two', 'ghost-three']);
        $this->unknownAccountAttempts(self::SECOND_IP, ['typo@example.test']);

        $this->artisan('ip-block:scan')
            ->expectsTable(
                ['IP address', 'Non-existent accounts tried', 'Result'],
                [[self::IP, 3, 'blocked']],
            )
            ->assertSuccessful();

        $this->assertSame([self::IP], BlockedIp::query()->pluck('ip_address')->all());
    }

    private function assertNotBlocked(string $ip): void
    {
        $this->assertSame([], app(LoginAbuseDetector::class)->evaluate($ip));
        $this->assertFalse(app(IpBlockService::class)->isBlocked($ip));
    }

    /**
     * @param  list<string>  $identifiers
     * @param  array<string, mixed>  $attributes
     */
    private function unknownAccountAttempts(string $ip, array $identifiers, array $attributes = []): void
    {
        foreach ($identifiers as $identifier) {
            $this->recordAttempt(['ip_address' => $ip, 'identifier' => $identifier] + $attributes);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function successfulSignIn(array $attributes): void
    {
        $this->recordAttempt($attributes + [
            'successful' => true,
            'authenticatable_type' => 'App\\Models\\User',
            'authenticatable_id' => self::EXISTING_ACCOUNT_ID,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function recordAttempt(array $attributes): LoginAttempt
    {
        return LoginAttempt::query()->create($attributes + [
            'guard' => 'web',
            'successful' => false,
            'blocked' => false,
            'ip_address' => self::IP,
            'created_at' => now(),
        ]);
    }

    private function createSchema(): void
    {
        (require database_path('migrations/2026_11_19_171205_create_login_attempts_table.php'))->up();
        (require database_path('migrations/2026_11_21_110719_add_created_at_index_to_login_attempts_table.php'))->up();
        (require database_path('migrations/2026_11_21_165723_create_blocked_ips_table.php'))->up();

        Schema::create('users', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('username');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
            $table->softDeletes();
        });
    }
}
