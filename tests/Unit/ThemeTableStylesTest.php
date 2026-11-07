<?php

namespace Tests\Unit;

use App\Enums\ColorScheme;
use App\Http\Requests\UpdateThemeRequest;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use PHPUnit\Framework\TestCase;

class ThemeTableStylesTest extends TestCase
{
    /**
     * @var list<string>
     */
    private const COLOR_SCHEMES = ['blue', 'indigo', 'cyan', 'teal', 'emerald', 'violet', 'pink', 'rose', 'red', 'orange', 'amber'];

    public function test_light_color_schemes_use_pale_table_hover_surfaces(): void
    {
        $stylesheet = (string) file_get_contents(__DIR__.'/../../resources/css/app.css');

        $this->assertStringContainsString('--surface-hover: #f1f5f9;', $stylesheet);
        $this->assertStringContainsString('--surface-hover: #ecfdf5;', $stylesheet);
        $this->assertStringContainsString('--surface-hover: #f5f3ff;', $stylesheet);
        $this->assertStringContainsString('--surface-hover: #fff1f2;', $stylesheet);
        $this->assertStringContainsString('--surface-hover: #fffbeb;', $stylesheet);
        $this->assertStringContainsString('--surface-hover: #ecfeff;', $stylesheet);
        $this->assertStringContainsString('--surface-hover: #eef2ff;', $stylesheet);
        $this->assertStringContainsString('--surface-hover: #f0fdfa;', $stylesheet);
        $this->assertStringContainsString('--surface-hover: #fff7ed;', $stylesheet);
        $this->assertStringContainsString('--surface-hover: #fef2f2;', $stylesheet);
        $this->assertStringContainsString('--surface-hover: #fdf2f8;', $stylesheet);
        $this->assertMatchesRegularExpression(
            '/main tbody tr:hover\s*\{\s*background-color: var\(--ui-surface-hover\) !important;\s*\}/',
            $stylesheet,
        );
        $this->assertMatchesRegularExpression('/:root \{[^}]*--ui-surface-hover: var\(--surface-hover\);/s', $stylesheet);
    }

    public function test_dark_table_hover_styles_remain_unchanged(): void
    {
        $stylesheet = (string) file_get_contents(__DIR__.'/../../resources/css/app.css');

        $this->assertStringContainsString('--surface-hover-dark: #1e293b;', $stylesheet);
        $this->assertStringContainsString('--surface-hover-dark: #14532d;', $stylesheet);
        $this->assertStringContainsString('--surface-hover-dark: #3b0764;', $stylesheet);
        $this->assertStringContainsString('--surface-hover-dark: #4c0519;', $stylesheet);
        $this->assertStringContainsString('--surface-hover-dark: #78350f;', $stylesheet);
        $this->assertStringContainsString('--surface-hover-dark: #164e63;', $stylesheet);
        $this->assertStringContainsString('--surface-hover-dark: #312e81;', $stylesheet);
        $this->assertStringContainsString('--surface-hover-dark: #134e4a;', $stylesheet);
        $this->assertStringContainsString('--surface-hover-dark: #7c2d12;', $stylesheet);
        $this->assertStringContainsString('--surface-hover-dark: #7f1d1d;', $stylesheet);
        $this->assertStringContainsString('--surface-hover-dark: #831843;', $stylesheet);
        // Dark mode reuses the single hover rule through the semantic token.
        $this->assertMatchesRegularExpression('/:root\.dark \{[^}]*--ui-surface-hover: var\(--surface-hover-dark\);/s', $stylesheet);
        $this->assertStringNotContainsString('.dark main tbody tr:hover', $stylesheet);
    }

    public function test_every_color_scheme_defines_complete_surface_and_accent_tokens(): void
    {
        $stylesheet = (string) file_get_contents(__DIR__.'/../../resources/css/app.css');

        foreach (self::COLOR_SCHEMES as $scheme) {
            $matched = preg_match(
                '/\[data-color-scheme="'.preg_quote($scheme, '/').'"\]\s*\{(?<tokens>.*?)\}/s',
                $stylesheet,
                $matches,
            );

            $this->assertSame(1, $matched, "{$scheme} must define a color-scheme token block.");

            foreach (['--surface-body:', '--surface-body-dark:', '--surface-hover:', '--surface-hover-dark:', '--color-primary-50:', '--color-primary-500:', '--color-primary-950:'] as $token) {
                $this->assertStringContainsString($token, $matches['tokens'], "{$scheme} must define {$token}");
            }
        }
    }

    public function test_color_scheme_options_stay_in_sync_across_the_ui_and_persistence_layers(): void
    {
        $source = static fn (string $path): string => (string) file_get_contents(__DIR__.'/../../'.$path);

        $this->assertSame(self::COLOR_SCHEMES, ColorScheme::values());

        // The Alpine store keeps a literal list (it is unit-tested without a DOM), so pin it to the enum.
        preg_match('/const colorSchemes = \[(?<list>[^\]]*)\]/', $source('resources/js/alpine/stores/theme.js'), $matches);
        $storeSchemes = array_map(static fn (string $value): string => trim($value, " '\""), explode(',', $matches['list'] ?? ''));
        $this->assertSame(ColorScheme::values(), $storeSchemes);

        foreach (['app/Http/Requests/UpdateProfileRequest.php', 'app/Http/Requests/UpdateThemeRequest.php'] as $request) {
            $this->assertStringContainsString('Rule::in(ColorScheme::values())', $source($request));
        }

        $this->assertStringContainsString('ColorScheme::tryFrom(', $source('app/Http/Controllers/ProfileController.php'));

        foreach (['resources/views/partials/scheme-switcher.blade.php', 'resources/views/profile/edit.blade.php', 'resources/views/partials/theme-init.blade.php'] as $view) {
            $this->assertStringContainsString('\\App\\Enums\\ColorScheme::cases()', $source($view), $view);
            $this->assertStringNotContainsString("'value' => 'indigo'", $source($view), $view);
        }
    }

    public function test_theme_update_validation_accepts_each_supported_scheme_and_rejects_unknown_values(): void
    {
        $validator = new Factory(new Translator(new ArrayLoader, 'en'));
        $rules = (new UpdateThemeRequest)->rules();

        foreach (self::COLOR_SCHEMES as $scheme) {
            $this->assertFalse(
                $validator->make(['color_scheme' => $scheme], $rules)->fails(),
                "{$scheme} should be accepted as a color scheme.",
            );
        }

        $this->assertTrue($validator->make(['color_scheme' => 'unknown'], $rules)->fails());
    }
}
