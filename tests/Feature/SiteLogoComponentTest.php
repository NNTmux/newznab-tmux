<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SiteLogoComponentTest extends TestCase
{
    #[Test]
    public function it_renders_the_nntmux_brand_mark_when_no_logo_is_uploaded(): void
    {
        $markup = Blade::render('<x-site-logo :url="null" />');

        $this->assertStringContainsString('<svg', $markup);
        $this->assertStringContainsString('class="h-[70%] w-[70%]"', $markup);
        $this->assertStringContainsString('aria-hidden="true"', $markup);
        $this->assertStringNotContainsString('<img', $markup);
    }

    #[Test]
    public function it_renders_the_uploaded_logo_instead_of_the_brand_mark(): void
    {
        $markup = Blade::render('<x-site-logo url="/storage/site-logos/custom.png" />');

        $this->assertStringContainsString('<img src="/storage/site-logos/custom.png"', $markup);
        $this->assertStringNotContainsString('<svg', $markup);
    }
}
