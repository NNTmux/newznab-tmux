<?php

namespace Tests\Unit;

use App\Enums\ColorScheme;
use App\Enums\ThemePreference;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ColorSchemeEnumTest extends TestCase
{
    #[Test]
    public function first_paint_colors_match_each_scheme_body_surface_in_the_stylesheet(): void
    {
        $stylesheet = (string) file_get_contents(__DIR__.'/../../resources/css/app.css');

        foreach (ColorScheme::cases() as $scheme) {
            preg_match(
                '/\[data-color-scheme="'.$scheme->value.'"\]\s*\{(?<tokens>.*?)\}/s',
                $stylesheet,
                $matches,
            );

            $this->assertStringContainsString("--surface-body: {$scheme->canvas(false)};", $matches['tokens'] ?? '', $scheme->value);
            $this->assertStringContainsString("--surface-body-dark: {$scheme->canvas(true)};", $matches['tokens'] ?? '', $scheme->value);
        }
    }

    #[Test]
    public function unknown_stored_values_fall_back_to_the_defaults(): void
    {
        $this->assertSame(ColorScheme::Teal, ColorScheme::fromPreference('teal'));
        $this->assertSame(ColorScheme::Blue, ColorScheme::fromPreference('neon'));
        $this->assertSame(ColorScheme::Blue, ColorScheme::fromPreference(null));
        $this->assertSame(ThemePreference::System, ThemePreference::fromPreference('system'));
        $this->assertSame(ThemePreference::Light, ThemePreference::fromPreference('sepia'));
    }

    #[Test]
    public function swatch_classes_are_static_and_the_enum_is_scanned_by_tailwind(): void
    {
        $stylesheet = (string) file_get_contents(__DIR__.'/../../resources/css/app.css');

        $this->assertStringContainsString('@source "../../app/Enums/ColorScheme.php";', $stylesheet);

        foreach (ColorScheme::cases() as $scheme) {
            $this->assertSame("bg-{$scheme->value}-600", $scheme->swatchClass());
        }
    }
}
