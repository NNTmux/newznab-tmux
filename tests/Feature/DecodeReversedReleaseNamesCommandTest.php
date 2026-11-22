<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class DecodeReversedReleaseNamesCommandTest extends TestCase
{
    public function test_test_option_prints_both_scores_without_triggering_a_deprecation(): void
    {
        $this->withoutDeprecationHandling();

        $this->artisan('releases:decode-reversed', ['--test' => 'PRG-462x.p0801.4202.eivoM.emoS'])
            ->expectsOutputToContain('Original score:')
            ->expectsOutputToContain('Reversed score:')
            ->assertSuccessful();
    }
}
