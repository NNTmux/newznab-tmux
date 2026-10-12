<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class VerificationTemporaryArtifactFailureTest extends TestCase
{
    #[DataProvider('failures')]
    public function test_failed_atomic_writes_leave_no_unreferenced_temporary_file(string $writer, string $fault): void
    {
        $root = sys_get_temp_dir().'/verification-write-'.bin2hex(random_bytes(8));
        mkdir($root, 0700);
        try {
            $process = new Process([PHP_BINARY, base_path('tests/Fixtures/verification/temporary-write-failure.php'), $writer, $fault, $root], base_path());
            $process->mustRun();
            $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            $this->assertNotNull($result['failure']);
            $this->assertSame(0, $result['temporary_files']);
            $this->assertTrue($result['previous_nzb_preserved']);
            $this->assertSame(0, $result['final_manifests']);
        } finally {
            File::deleteDirectory($root);
        }
    }

    /** @return array<string, array{string, string}> */
    public static function failures(): array
    {
        $cases = [];
        foreach (['manifest', 'nzb'] as $writer) {
            foreach (['write', 'flush', 'sync', 'rename'] as $fault) {
                $cases[$writer.' '.$fault] = [$writer, $fault];
            }
        }

        return $cases;
    }
}
