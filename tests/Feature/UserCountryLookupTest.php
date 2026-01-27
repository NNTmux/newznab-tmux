<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class UserCountryLookupTest extends TestCase
{
    public function test_user_with_no_host_returns_null_country(): void
    {
        $user = new User;
        $user->host = null;

        $this->assertNull($user->country_code);
        $this->assertNull($user->country_name);
    }

    public function test_user_with_private_ip_returns_null_country(): void
    {
        $user = new User;
        $user->host = '192.168.1.1';

        $this->assertNull($user->country_code);
        $this->assertNull($user->country_name);
    }

    public function test_user_with_localhost_returns_null_country(): void
    {
        $user = new User;
        $user->host = '127.0.0.1';

        $this->assertNull($user->country_code);
        $this->assertNull($user->country_name);
    }

    public function test_country_lookup_is_cached(): void
    {
        // Set up a cache entry
        $ip = '8.8.8.8';
        $cacheKey = 'ip_country_lookup_'.md5($ip);

        Cache::put($cacheKey, [
            'country' => 'United States',
            'countryCode' => 'US',
        ], 86400);

        $user = new User;
        $user->host = $ip;

        $this->assertEquals('US', $user->country_code);
        $this->assertEquals('United States', $user->country_name);

        Cache::forget($cacheKey);
    }

    public function test_country_lookup_handles_api_response(): void
    {
        Http::fake([
            'ip-api.com/*' => Http::response([
                'status' => 'success',
                'country' => 'Germany',
                'countryCode' => 'DE',
            ], 200),
        ]);

        // Clear any existing cache
        $ip = '1.2.3.4';
        Cache::forget('ip_country_lookup_'.md5($ip));

        $user = new User;
        $user->host = $ip;

        $this->assertEquals('DE', $user->country_code);
        $this->assertEquals('Germany', $user->country_name);

        // Clean up
        Cache::forget('ip_country_lookup_'.md5($ip));
    }

    public function test_country_lookup_handles_failed_api_response(): void
    {
        Http::fake([
            'ip-api.com/*' => Http::response([
                'status' => 'fail',
            ], 200),
        ]);

        // Clear any existing cache
        $ip = '5.6.7.8';
        Cache::forget('ip_country_lookup_'.md5($ip));

        $user = new User;
        $user->host = $ip;

        $this->assertNull($user->country_code);
        $this->assertNull($user->country_name);

        // Clean up
        Cache::forget('ip_country_lookup_'.md5($ip));
    }
}
