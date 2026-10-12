<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Releases\Verification\Prerequisites;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ReleaseVerificationPrerequisitesTest extends TestCase
{
    public function test_enabled_gate_rejects_missing_par2(): void
    {
        $this->expectException(RuntimeException::class);
        (new Prerequisites)->check(true, 'nntmux-missing-par2-executable');
    }

    public function test_disabled_gate_does_not_require_par2(): void
    {
        (new Prerequisites)->check(false, 'nntmux-missing-par2-executable');
        $this->addToAssertionCount(1);
    }

    public function test_enabled_gate_accepts_configured_executable(): void
    {
        $binary = tempnam(sys_get_temp_dir(), 'par2-preflight-');
        $this->assertNotFalse($binary);
        try {
            file_put_contents($binary, "#!/bin/sh\nexit 0\n");
            chmod($binary, 0700);
            (new Prerequisites)->check(true, $binary);
            $this->addToAssertionCount(1);
            chmod($binary, 0600);
            $this->expectException(RuntimeException::class);
            (new Prerequisites)->check(true, $binary);
        } finally {
            unlink($binary);
        }
    }
}
