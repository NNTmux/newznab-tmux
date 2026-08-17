<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ThemeTableStylesTest extends TestCase
{
    public function test_light_color_schemes_use_pale_table_hover_surfaces(): void
    {
        $stylesheet = (string) file_get_contents(__DIR__.'/../../resources/css/app.css');

        $this->assertStringContainsString('--surface-hover: #f1f5f9;', $stylesheet);
        $this->assertStringContainsString('--surface-hover: #ecfdf5;', $stylesheet);
        $this->assertStringContainsString('--surface-hover: #f5f3ff;', $stylesheet);
        $this->assertMatchesRegularExpression(
            '/main tbody tr:hover\s*\{\s*background-color: var\(--surface-hover\) !important;\s*\}/',
            $stylesheet,
        );
    }

    public function test_dark_table_hover_styles_remain_unchanged(): void
    {
        $stylesheet = (string) file_get_contents(__DIR__.'/../../resources/css/app.css');

        $this->assertStringContainsString('--surface-hover-dark: #1e293b;', $stylesheet);
        $this->assertStringContainsString('--surface-hover-dark: #14532d;', $stylesheet);
        $this->assertStringContainsString('--surface-hover-dark: #3b0764;', $stylesheet);
        $this->assertMatchesRegularExpression(
            '/\.dark main tbody tr:hover\s*\{\s*background-color: var\(--surface-hover-dark\) !important;\s*\}/',
            $stylesheet,
        );
    }
}
