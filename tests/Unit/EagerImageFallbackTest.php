<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EagerImageFallbackTest extends TestCase
{
    #[Test]
    public function the_broken_image_fallback_loads_on_every_page(): void
    {
        $entry = (string) file_get_contents(__DIR__.'/../../resources/js/alpine/index.js');
        $lazyLoader = (string) file_get_contents(__DIR__.'/../../resources/js/alpine/lazy-loader.js');

        $this->assertStringContainsString("import './components/image-fallback.js';", $entry, 'Cover pages have no x-data that would lazy-load it');
        $this->assertStringNotContainsString('image-fallback', $lazyLoader);
    }

    #[Test]
    public function the_fallback_listens_in_the_capture_phase_because_image_errors_do_not_bubble(): void
    {
        $module = (string) file_get_contents(__DIR__.'/../../resources/js/alpine/components/image-fallback.js');

        $this->assertMatchesRegularExpression("/document\\.addEventListener\\('error',.*\\}, true\\);/s", $module);
        $this->assertStringContainsString('data-fallback-src', $module);
        $this->assertStringContainsString('data-hide-on-error', $module);
    }
}
