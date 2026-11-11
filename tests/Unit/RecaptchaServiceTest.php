<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\RecaptchaService;
use Tests\TestCase;

class RecaptchaServiceTest extends TestCase
{
    public function test_script_tag_carries_the_csp_nonce(): void
    {
        $this->assertStringContainsString('nonce="'.csp_nonce().'"', RecaptchaService::renderJs());
    }
}
