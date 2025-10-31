<?php

namespace Tests\Unit;

use App\Console\Commands\NntmuxResetPostProcessing;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class NntmuxResetPostProcessingTest extends TestCase
{
    private function callNormalize(array $raw): array
    {
        $cmd = new NntmuxResetPostProcessing;
        $ref = new ReflectionClass($cmd);
        $method = $ref->getMethod('normalizeCategories');
        $method->setAccessible(true);
        /** @var array $res */
        $res = $method->invoke($cmd, $raw);

        return $res;
    }

    private function callInvalid(array $normalized): array
    {
        $cmd = new NntmuxResetPostProcessing;
        $ref = new ReflectionClass($cmd);
        $method = $ref->getMethod('invalidCategories');
        $method->setAccessible(true);
        /** @var array $res */
        $res = $method->invoke($cmd, $normalized);

        return $res;
    }

    public function test_normalize_handles_basic_and_commas_and_repeats_and_case_and_plural(): void
    {
        $this->assertSame([], $this->callNormalize([]));
        $this->assertSame(['movie'], $this->callNormalize(['movie']));
        $this->assertSame(['movie'], $this->callNormalize(['Movies']));
        $this->assertSame(['movie', 'tv'], $this->callNormalize(['movie,tv']));
        $this->assertSame(['movie', 'tv', 'music'], $this->callNormalize(['movie', 'TV', 'musics']));
    }

    public function test_normalize_strips_value_from_equals_tokens(): void
    {
        // Simulate Symfony short option mapping: -category=misc => -c ategory=misc => option('category') contains [ 'ategory=misc' ]
        $this->assertSame(['misc'], $this->callNormalize(['ategory=misc']));
        // Also support explicit key=value inputs like category=music
        $this->assertSame(['music'], $this->callNormalize(['category=music']));
    }

    public function test_invalid_detection_and_all_passthrough(): void
    {
        $this->assertSame(['foo'], $this->callInvalid(['foo']));
        $this->assertSame([], $this->callInvalid(['movie', 'tv']));
        $this->assertSame([], $this->callInvalid(['all']));
        $this->assertSame(['foo'], $this->callInvalid(['all', 'foo']));
    }
}
