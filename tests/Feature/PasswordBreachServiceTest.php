<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\PasswordBreachService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Feature tests for PasswordBreachService - tests password breach detection via Have I Been Pwned API.
 */
class PasswordBreachServiceTest extends TestCase
{
    /**
     * Test that a known breached password is detected.
     * "password" is one of the most common breached passwords.
     */
    public function test_detects_breached_password(): void
    {
        // The SHA-1 hash of "password" is 5BAA61E4C9B93F3F0682250B6CF8331B7EE68FD8
        // So the prefix is 5BAA6 and suffix is 1E4C9B93F3F0682250B6CF8331B7EE68FD8
        $mockResponse = "1D72CD07550416C216D8AD296BF5C0AE8E0:10\n".
            "1E4C9B93F3F0682250B6CF8331B7EE68FD8:9659365\n".
            '1E4D5B6920BCBB55B13D2E2E2FAC59AB68E:2';
        Http::fake([
            'api.pwnedpasswords.com/range/5BAA6' => Http::response($mockResponse, 200),
        ]);
        $service = new PasswordBreachService;
        $result = $service->isPasswordBreached('password');
        $this->assertTrue($result);
    }

    /**
     * Test that a unique password is not detected as breached.
     */
    public function test_unique_password_not_breached(): void
    {
        // Mock a response that doesn't contain the password's hash suffix
        Http::fake([
            'api.pwnedpasswords.com/range/*' => Http::response("0D6BE69E61E2D58CCB0A52B2A6B62BD6AE6:5\n0D6BE69E61E2D58CCB0A52B2A6B62BD6AE7:3", 200),
        ]);
        $service = new PasswordBreachService;
        $result = $service->isPasswordBreached('MyV3ry$ecur3P@ssw0rd!2024_unique');
        $this->assertFalse($result);
    }

    /**
     * Test that the service fails open when API is unavailable.
     */
    public function test_fails_open_when_api_unavailable(): void
    {
        Http::fake([
            'api.pwnedpasswords.com/range/*' => Http::response('Service Unavailable', 503),
        ]);
        $service = new PasswordBreachService;
        $result = $service->isPasswordBreached('anypassword');
        // Should return false (not breached) when API is unavailable to not block login
        $this->assertFalse($result);
    }

    /**
     * Test that threshold parameter works correctly.
     */
    public function test_threshold_parameter(): void
    {
        $mockResponse = '1E4C9B93F3F0682250B6CF8331B7EE68FD8:5';
        Http::fake([
            'api.pwnedpasswords.com/range/5BAA6' => Http::response($mockResponse, 200),
        ]);
        // With threshold of 1 (default), password should be considered breached
        $service1 = new PasswordBreachService(1);
        $this->assertTrue($service1->isPasswordBreached('password'));
        // With threshold of 10, password with 5 appearances should NOT be considered breached
        $service10 = new PasswordBreachService(10);
        $this->assertFalse($service10->isPasswordBreached('password'));
    }
}
