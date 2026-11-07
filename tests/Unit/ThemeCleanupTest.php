<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Keeps retired theme CSS from coming back: Bootstrap-era helpers, the hand-rolled .btn set,
 * the replaced toast/movie-hero styles and the gray-text dark-mode override.
 */
class ThemeCleanupTest extends TestCase
{
    #[Test]
    public function retired_selectors_are_gone_from_the_stylesheets(): void
    {
        $app = (string) file_get_contents(__DIR__.'/../../resources/css/app.css');
        $cspSafe = (string) file_get_contents(__DIR__.'/../../resources/css/csp-safe.css');

        foreach (['.btn-primary', '.btn {', 'table.dataTable', '.page-link', '.form-group', '.table-mobile-cards', '.text-gray-700:not('] as $selector) {
            $this->assertStringNotContainsString($selector, $app, $selector);
        }

        foreach (['.toast-notification', '.movies-hero', '.theme-option', '.admin-theme-selector', '.confirmation-modal-header', '#toast-container'] as $selector) {
            $this->assertStringNotContainsString($selector, $cspSafe, $selector);
        }

        $this->assertSame(1, substr_count($cspSafe, '@keyframes slideIn'), 'Only one slideIn animation may exist');
    }

    #[Test]
    public function the_dark_mode_baseline_leaves_semantic_text_tokens_alone(): void
    {
        $app = (string) file_get_contents(__DIR__.'/../../resources/css/app.css');

        $this->assertStringContainsString('.dark label:not([class*="dark:text-"]):not([class*="text-fg"])', $app);
        $this->assertStringContainsString('.dark h1:not([class*="dark:text-"]):not([class*="text-fg"])', $app);
    }
}
