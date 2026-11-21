<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Enums\IpBlockSource;
use App\Models\BlockedIp;
use App\Services\Security\IpBlockService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IpBlockServiceTest extends TestCase
{
    private const string IP = '203.0.113.50';

    private IpBlockService $blocks;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'nntmux.ip_blocking.block_hours' => 24,
            'nntmux.ip_blocking.max_block_hours' => 720,
            'nntmux.ip_blocking.allowlist' => [],
            'trustedproxy.cloudflare.enabled' => false,
            'trustedproxy.proxies' => null,
        ]);

        DB::purge();
        DB::reconnect();
        Cache::flush();

        (require database_path('migrations/2026_11_21_165723_create_blocked_ips_table.php'))->up();
        $this->travelTo('2026-11-21 12:00:00');
        $this->blocks = app(IpBlockService::class);
    }

    #[Test]
    public function the_first_automatic_block_lasts_the_configured_hours(): void
    {
        $block = $this->blocks->blockAutomatically(self::IP, 'three unknown accounts', 'fingerprint-a');

        $this->assertNotNull($block);
        $this->assertSame(IpBlockSource::Auto, $block->source);
        $this->assertSame(1, $block->block_count);
        $this->assertSame('2026-11-22 12:00:00', $block->expires_at?->toDateTimeString());
        $this->assertSame('fingerprint-a', $block->device_fingerprint);
        $this->assertTrue($this->blocks->isBlocked(self::IP));
    }

    #[Test]
    public function each_repeat_offence_doubles_the_block_up_to_the_cap(): void
    {
        config(['nntmux.ip_blocking.max_block_hours' => 72]);
        $hours = [];

        foreach (range(1, 4) as $offence) {
            $block = $this->blocks->blockAutomatically(self::IP, 'offence '.$offence);
            $this->assertNotNull($block);
            $hours[] = (int) now()->diffInHours($block->expires_at);
            $this->travelTo($block->expires_at->addMinute());
        }

        $this->assertSame([24, 48, 72, 72], $hours);
        $this->assertSame(1, BlockedIp::query()->count());
    }

    #[Test]
    public function a_block_already_in_force_is_not_extended_by_another_automatic_block(): void
    {
        $first = $this->blocks->blockAutomatically(self::IP, 'first');
        $this->travel(2)->hours();

        $second = $this->blocks->blockAutomatically(self::IP, 'second');

        $this->assertSame(1, $second?->block_count);
        $this->assertSame($first?->expires_at?->toDateTimeString(), $second?->expires_at?->toDateTimeString());
        $this->assertSame('first', $second?->reason);
    }

    #[Test]
    public function a_manual_block_without_an_expiry_is_permanent(): void
    {
        $block = $this->blocks->blockManually(self::IP, null, 'spam', 7);

        $this->assertSame(IpBlockSource::Manual, $block?->source);
        $this->assertNull($block?->expires_at);
        $this->assertSame(7, $block?->created_by);

        $this->travel(5)->years();

        $this->assertTrue($this->blocks->isBlocked(self::IP));
    }

    #[Test]
    public function a_lifted_address_is_no_longer_blocked_and_its_history_is_kept(): void
    {
        $this->blocks->blockAutomatically(self::IP, 'first');

        $this->assertTrue($this->blocks->lift(self::IP));

        $this->assertFalse($this->blocks->isBlocked(self::IP));
        $this->assertFalse($this->blocks->lift(self::IP));
        $this->assertSame(2, $this->blocks->blockAutomatically(self::IP, 'second')?->block_count);
    }

    #[Test]
    public function an_ipv6_address_is_matched_whatever_way_it_is_written(): void
    {
        $this->blocks->blockManually('2A00:8C40:0000:0000:0000:0000:0000:0001');

        $this->assertTrue($this->blocks->isBlocked('2a00:8c40::1'));
        $this->assertSame('2a00:8c40::1', BlockedIp::query()->value('ip_address'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function protectedAddresses(): array
    {
        return [
            'loopback' => ['127.0.0.1'],
            'private network' => ['10.1.2.3'],
            'ipv6 loopback' => ['::1'],
            'allowlisted address' => ['198.51.100.9'],
            'inside an allowlisted range' => ['192.0.2.77'],
            'trusted proxy range' => ['198.18.5.5'],
            'not an ip address' => ['not-an-ip'],
        ];
    }

    #[Test]
    #[DataProvider('protectedAddresses')]
    public function protected_addresses_are_never_blocked(string $ip): void
    {
        config([
            'nntmux.ip_blocking.allowlist' => ['198.51.100.9', '192.0.2.0/24'],
            'trustedproxy.proxies' => ['198.18.0.0/15'],
        ]);

        $this->assertNull($this->blocks->blockAutomatically($ip, 'abuse'));
        $this->assertNull($this->blocks->blockManually($ip));
        $this->assertSame(0, BlockedIp::query()->count());
    }

    #[Test]
    public function the_lift_command_unblocks_an_address(): void
    {
        $this->blocks->blockManually(self::IP);

        $this->artisan('ip-block:lift', ['ip' => self::IP])
            ->expectsOutputToContain('Lifted the block on '.self::IP)
            ->assertSuccessful();

        $this->assertFalse($this->blocks->isBlocked(self::IP));
    }

    #[Test]
    public function the_lift_command_rejects_a_value_that_is_not_an_ip_address(): void
    {
        $this->artisan('ip-block:lift', ['ip' => 'nonsense'])
            ->expectsOutputToContain('not a valid IP address')
            ->assertFailed();
    }

    #[Test]
    public function blocks_that_ended_long_ago_are_pruned_and_recent_or_active_ones_are_kept(): void
    {
        $this->blocks->blockManually('203.0.113.1', now()->addHour());
        $this->blocks->blockManually('203.0.113.2', now()->addHour());
        $this->blocks->blockManually('203.0.113.3');
        $this->travel(BlockedIp::PRUNE_ENDED_AFTER_DAYS)->days();
        $this->blocks->blockManually('203.0.113.2', now()->addHour());
        $this->travel(2)->hours();

        (new BlockedIp)->pruneAll();

        $this->assertSame(['203.0.113.2', '203.0.113.3'], BlockedIp::query()->orderBy('ip_address')->pluck('ip_address')->all());
    }
}
