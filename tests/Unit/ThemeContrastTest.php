<?php

namespace Tests\Unit;

use App\Enums\ColorScheme;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * WCAG AA (4.5:1) checks for every color scheme, computed from the hex values in app.css.
 */
class ThemeContrastTest extends TestCase
{
    private const AA = 4.5;

    #[Test]
    public function white_text_on_the_solid_accent_passes_aa(): void
    {
        foreach (ColorScheme::cases() as $scheme) {
            $tokens = $this->tokens($scheme);
            preg_match('/^var\(--(color-primary-\d+)\)$/', $tokens['scheme-accent'], $reference);
            $accent = $tokens[$reference[1]];

            $this->assertGreaterThanOrEqual(self::AA, $this->ratio('#ffffff', $accent), "{$scheme->value} accent {$accent}");
        }
    }

    #[Test]
    public function muted_text_passes_aa_on_every_content_surface_in_both_modes(): void
    {
        foreach (ColorScheme::cases() as $scheme) {
            $tokens = $this->tokens($scheme);

            foreach (['', '-dark'] as $mode) {
                foreach (['surface-card', 'surface-body', 'surface-panel-alt'] as $surface) {
                    $ratio = $this->ratio($tokens["text-muted{$mode}"], $tokens["{$surface}{$mode}"]);
                    $this->assertGreaterThanOrEqual(self::AA, $ratio, "{$scheme->value}: --text-muted{$mode} on --{$surface}{$mode}");
                }
            }
        }
    }

    /**
     * @return array<string, string>
     */
    private function tokens(ColorScheme $scheme): array
    {
        $stylesheet = (string) file_get_contents(__DIR__.'/../../resources/css/app.css');
        preg_match('/\[data-color-scheme="'.$scheme->value.'"\]\s*\{(?<tokens>.*?)\}/s', $stylesheet, $block);
        preg_match_all('/--([a-z0-9-]+):\s*([^;]+);/', $block['tokens'] ?? '', $pairs, PREG_SET_ORDER);

        $tokens = [];
        foreach ($pairs as [, $name, $value]) {
            $tokens[$name] = trim($value);
        }

        return $tokens;
    }

    private function ratio(string $first, string $second): float
    {
        $lighter = max($this->luminance($first), $this->luminance($second));
        $darker = min($this->luminance($first), $this->luminance($second));

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    private function luminance(string $hex): float
    {
        $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/i', $hex);
        $channels = array_map(static function (string $pair): float {
            $value = hexdec($pair) / 255;

            return $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        }, str_split(substr($hex, 1), 2));

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }
}
