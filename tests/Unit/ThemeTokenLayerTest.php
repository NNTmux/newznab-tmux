<?php

namespace Tests\Unit;

use App\Enums\ColorScheme;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ThemeTokenLayerTest extends TestCase
{
    /**
     * Semantic token => per-scheme source variable (light; the dark source adds "-dark").
     *
     * @var array<string, string>
     */
    private const DUAL_TOKENS = [
        'canvas' => 'surface-body',
        'surface' => 'surface-card',
        'surface-sunken' => 'surface-panel-alt',
        'surface-hover' => 'surface-hover',
        'popover' => 'surface-dropdown',
        'sidebar' => 'surface-sidebar',
        'header' => 'surface-header',
        'footer' => 'surface-footer',
        'chrome-border' => 'surface-chrome-border',
        'border' => 'border-default',
        'fg-muted' => 'text-muted',
    ];

    #[Test]
    public function every_semantic_token_switches_between_its_light_and_dark_source(): void
    {
        $root = $this->block(':root {');
        $dark = $this->block(':root.dark {');

        foreach (self::DUAL_TOKENS as $token => $source) {
            $this->assertStringContainsString("--ui-{$token}: var(--{$source});", $root, $token);
            $this->assertStringContainsString("--ui-{$token}: var(--{$source}-dark);", $dark, $token);
        }
    }

    #[Test]
    public function component_rules_use_semantic_tokens_instead_of_dark_twins(): void
    {
        foreach (['app.css', 'csp-safe.css'] as $file) {
            $stylesheet = $this->stylesheet($file);

            if ($file === 'app.css') {
                // Only the scheme blocks and the :root.dark mapping may name the raw -dark inputs.
                $stylesheet = substr($stylesheet, strpos($stylesheet, '/* Theme customization'));
            }

            $this->assertDoesNotMatchRegularExpression('/var\(--(surface|border-default|text-muted)[a-z-]*\)/', $stylesheet, $file);
        }
    }

    #[Test]
    public function theme_exposes_scheme_aware_utilities_and_literal_primary_fallbacks(): void
    {
        $stylesheet = $this->stylesheet('app.css');

        $this->assertDoesNotMatchRegularExpression('/--color-primary-(\d+): var\(--color-primary-\1\)/', $stylesheet, 'Primary tokens must not reference themselves.');
        $this->assertStringContainsString('--color-primary-500: #3b82f6;', $this->block('@theme {'));

        $inline = $this->block('@theme inline {');
        foreach (['canvas', 'surface', 'surface-sunken', 'line', 'fg', 'fg-muted', 'accent', 'accent-hover', 'on-accent', 'link'] as $color) {
            $this->assertMatchesRegularExpression("/--color-{$color}: var\\(--ui-[a-z-]+\\);/", $inline, $color);
        }

        foreach (['success', 'warning', 'danger', 'info'] as $status) {
            $this->assertStringContainsString("--color-{$status}-600:", $this->block('@theme {'), $status);
        }

        foreach (ColorScheme::cases() as $scheme) {
            preg_match('/\[data-color-scheme="'.$scheme->value.'"\]\s*\{(?<tokens>.*?)\}/s', $stylesheet, $matches);
            $this->assertMatchesRegularExpression('/--scheme-accent: var\(--color-primary-\d+\);/', $matches['tokens'] ?? '', $scheme->value);
        }
    }

    private function block(string $opening): string
    {
        $stylesheet = $this->stylesheet('app.css');
        $start = strpos($stylesheet, "\n".$opening);
        $this->assertNotFalse($start, "Missing block {$opening}");

        return substr($stylesheet, $start, strpos($stylesheet, "\n}", $start) - $start);
    }

    private function stylesheet(string $file): string
    {
        return (string) file_get_contents(__DIR__.'/../../resources/css/'.$file);
    }
}
