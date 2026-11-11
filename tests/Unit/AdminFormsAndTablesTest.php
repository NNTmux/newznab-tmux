<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class AdminFormsAndTablesTest extends TestCase
{
    #[Test]
    public function add_and_edit_forms_show_validation_errors(): void
    {
        $missing = [];

        foreach ($this->views('admin') as $path => $source) {
            if (! preg_match('/(edit|add|bulk)\.blade\.php$/', $path) || ! preg_match('/<form[^>]*method=["\']post["\']/i', $source)) {
                continue;
            }

            if (! preg_match('/<x-form-errors|@error\(|\$errors/', $source)) {
                $missing[] = $path;
            }
        }

        $this->assertSame([], $missing, "Forms must render validation errors:\n".implode("\n", $missing));
    }

    #[Test]
    public function flash_messages_are_left_to_the_layout_toasts(): void
    {
        $duplicates = [];

        foreach ($this->views('admin') as $path => $source) {
            if (preg_match("/session\\('(success|error|warning)'\\)/", $source)) {
                $duplicates[] = $path;
            }
        }

        $this->assertSame([], $duplicates, implode("\n", $duplicates));
    }

    #[Test]
    public function admin_table_headers_use_the_shared_header_cell(): void
    {
        $raw = [];

        foreach ($this->views('admin') as $path => $source) {
            preg_match_all('/<thead\b.*?<\/thead>/s', $source, $heads);

            foreach ($heads[0] as $head) {
                if (preg_match('/<th\b/', $head)) {
                    $raw[] = $path;
                }
            }
        }

        $this->assertSame([], array_values(array_unique($raw)), 'Use <x-admin.th> (scope, aria-sort): '.implode(', ', array_unique($raw)));
    }

    #[Test]
    public function icon_only_controls_and_checkboxes_have_accessible_names(): void
    {
        $unnamed = [];

        foreach ($this->views('') as $path => $source) {
            if (str_starts_with($path, 'forum/') || str_starts_with($path, 'components/') || str_starts_with($path, 'vendor/') || str_starts_with($path, 'emails/')) {
                continue;
            }

            preg_match_all('/<(a|button)\b((?:[^>"\']|"[^"]*"|\'[^\']*\')*)>\s*<i\b[^<]*><\/i>\s*<\/\1>/', $source, $controls, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
            foreach ($controls as $control) {
                if (! str_contains($control[2][0], 'aria-label')) {
                    $unnamed[] = $path.':'.(substr_count($source, "\n", 0, $control[0][1]) + 1);
                }
            }
        }

        $this->assertSame([], $unnamed, "Icon-only controls need aria-label:\n".implode("\n", $unnamed));
    }

    #[Test]
    public function custom_admin_modals_are_announced_as_dialogs(): void
    {
        foreach (['users/index', 'groups/index', 'release-reports/index'] as $view) {
            $source = (string) file_get_contents(__DIR__."/../../resources/views/admin/{$view}.blade.php");

            $handRolledModals = substr_count($source, 'role="dialog" aria-modal="true"');
            $sharedModals = substr_count($source, '<x-modal ');

            $this->assertSame(
                substr_count($source, 'role="dialog"'),
                substr_count($source, 'aria-labelledby="'),
                "{$view}: every dialog needs an accessible name",
            );
            $this->assertSame($sharedModals, substr_count($source, ' labelledby="'), "{$view}: every shared modal needs an accessible name");
            $this->assertGreaterThan(0, $handRolledModals + $sharedModals, $view);
        }

        $sharedModal = (string) file_get_contents(__DIR__.'/../../resources/views/components/modal.blade.php');
        $this->assertStringContainsString('<dialog', $sharedModal, 'The shared modal is a native dialog, so the browser traps focus');
        $this->assertStringContainsString('aria-labelledby="{{ $labelledby }}"', $sharedModal);
    }

    /**
     * @return iterable<string, string>
     */
    private function views(string $directory): iterable
    {
        $root = realpath(__DIR__.'/../../resources/views');
        $base = $directory === '' ? $root : $root.'/'.$directory;
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base));

        foreach ($iterator as $file) {
            if (str_ends_with($file->getFilename(), '.blade.php')) {
                yield substr($file->getPathname(), strlen($root) + 1) => (string) file_get_contents($file->getPathname());
            }
        }
    }
}
