<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Guards against the browser "Issues" warnings that showed up on release pages:
 * quirks mode from markup printed outside a layout, lazy images without dimensions,
 * and the Fenced Frame deprecation triggered by Alpine's global scan.
 */
class BrowserConsoleHygieneTest extends TestCase
{
    #[Test]
    public function every_section_is_closed_so_nothing_is_printed_before_the_doctype(): void
    {
        $unbalanced = [];

        foreach ($this->views() as $path => $source) {
            $source = (string) preg_replace('/\{\{--.*?--\}\}/s', '', $source);
            $opened = 0;

            preg_match_all('/@section\s*\((.*?)\)\s*$/m', $source, $sections);
            foreach ($sections[1] as $arguments) {
                // @section('name', 'value') is self-closing; only the block form needs @endsection.
                if (! $this->hasTopLevelComma($arguments)) {
                    $opened++;
                }
            }

            $closed = preg_match_all('/@(endsection|stop|show|overwrite|append)\b/', $source);
            if ($opened !== $closed) {
                $unbalanced[] = "{$path} ({$opened} opened, {$closed} closed)";
            }
        }

        $this->assertSame([], $unbalanced, implode("\n", $unbalanced));

        $details = (string) file_get_contents(__DIR__.'/../../resources/views/details/index.blade.php');
        $this->assertMatchesRegularExpression("/@push\\('modals'\\).*@include\\('details\\.partials\\.image-modal'\\).*@endpush/s", $details);
    }

    #[Test]
    public function lazy_images_reserve_their_size(): void
    {
        $missing = [];

        foreach ($this->views() as $path => $source) {
            preg_match_all('/<img\b(?:[^>"\']|"[^"]*"|\'[^\']*\')*>/', $source, $images, PREG_OFFSET_CAPTURE);

            foreach ($images[0] as [$tag, $offset]) {
                if (! str_contains($tag, 'loading="lazy"')) {
                    continue;
                }

                // CSS width/height "auto" overrides the attributes, so the browser treats the image as unsized
                // unless an aspect ratio is set.
                $class = preg_match('/class="([^"]*)"/', $tag, $match) === 1 ? $match[1] : '';
                $autoSized = preg_match('/(?<![\w:-])(?:w|h)-auto\b/', $class) === 1 && ! str_contains($class, 'aspect-');

                if (! str_contains($tag, 'width=') || ! str_contains($tag, 'height=') || $autoSized) {
                    $missing[] = $path.':'.(substr_count($source, "\n", 0, $offset) + 1);
                }
            }
        }

        $this->assertSame([], $missing, "Lazy images need width and height:\n".implode("\n", $missing));
    }

    #[Test]
    public function the_alpine_global_scan_skips_the_deprecated_fenced_frame_api(): void
    {
        $config = (string) file_get_contents(__DIR__.'/../../vite.config.js');
        $this->assertStringContainsString('skipDeprecatedGlobalsInAlpineCsp()', $config);
        $this->assertStringContainsString("'fence'", $config);

        $alpine = __DIR__.'/../../node_modules/@alpinejs/csp/dist/module.esm.js';
        if (! is_file($alpine)) {
            $this->markTestSkipped('node_modules is not installed.');
        }

        preg_match("/const ALPINE_CSP_GLOBAL_SCAN = '([^']+)';/", $config, $scan);
        $this->assertStringContainsString($scan[1] ?? '', (string) file_get_contents($alpine), 'Alpine changed its global scan; update the Vite transform in vite.config.js.');
    }

    private function hasTopLevelComma(string $arguments): bool
    {
        $depth = 0;
        foreach (str_split($arguments) as $character) {
            $depth += match ($character) {
                '(', '[' => 1,
                ')', ']' => -1,
                default => 0,
            };
            if ($character === ',' && $depth === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return iterable<string, string>
     */
    private function views(): iterable
    {
        $root = realpath(__DIR__.'/../../resources/views');
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
            if (str_ends_with($file->getFilename(), '.blade.php')) {
                yield substr($file->getPathname(), strlen($root) + 1) => (string) file_get_contents($file->getPathname());
            }
        }
    }
}
