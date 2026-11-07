<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Guards the semantic color + icon conventions for first-party views and Alpine code.
 * Use primary/accent/link (scheme-aware), info/success/warning/danger (status) or amber (decorative)
 * instead of hard-coded blue/green/yellow, which ignore the user's color scheme.
 */
class ThemeColorUsageTest extends TestCase
{
    /**
     * Not part of the app theme: separate builds, vendor dashboards and e-mail markup.
     *
     * @var list<string>
     */
    private const EXCLUDED = [
        'views/forum/',
        'views/components/forum/',
        'views/vendor/pulse/',
        'views/vendor/mail/',
        'views/emails/',
        'views/components/mail/',
    ];

    private const UTILITY = '(?:bg|text|border(?:-[trblxy])?|ring|ring-offset|from|via|to|fill|stroke|divide|outline|decoration|accent|caret|placeholder|shadow)';

    #[Test]
    public function views_and_scripts_use_semantic_colors_instead_of_blue_green_or_yellow(): void
    {
        $this->assertNoMatches('/(?<![\w\/-])(?:[\w\[\]&*>~+.:@-]+:)*'.self::UTILITY.'-(?:blue|green|yellow)-(?:50|[1-9]00|950)\b/');
    }

    #[Test]
    public function icons_use_current_font_awesome_names_and_style_prefixes(): void
    {
        $this->assertNoMatches('/(?<![\w-])fa fa-/');
        $this->assertNoMatches('/\bfa-[a-z-]+-o\b/');
    }

    #[Test]
    public function decorative_icons_are_hidden_from_assistive_technology(): void
    {
        $violations = [];

        foreach ($this->files() as $path => $source) {
            preg_match_all('/<i\s+[\w:@.-]+=[\\\\]?["\']/', $source, $matches, PREG_OFFSET_CAPTURE);

            foreach ($matches[0] as [, $offset]) {
                $tag = $this->tagAt($source, $offset);

                if (preg_match('/\bfa[srb]?\b|fa-/', $tag) && ! preg_match('/aria-hidden|aria-label|title=/', $tag)) {
                    $violations[] = $path.':'.(substr_count($source, "\n", 0, $offset) + 1);
                }
            }
        }

        $this->assertSame([], $violations, "Icons need aria-hidden=\"true\" (or an accessible label):\n".implode("\n", $violations));
    }

    #[Test]
    public function every_literal_icon_name_exists_in_font_awesome(): void
    {
        $css = '';
        foreach (['fontawesome.min.css', 'brands.min.css'] as $file) {
            $path = __DIR__.'/../../node_modules/@fortawesome/fontawesome-free/css/'.$file;
            if (! is_file($path)) {
                $this->markTestSkipped('Font Awesome is not installed (run npm install).');
            }
            $css .= (string) file_get_contents($path);
        }

        preg_match_all('/\.(fa-[a-z0-9-]+)/', $css, $defined);
        $defined = array_flip($defined[1]);
        $missing = [];

        foreach ($this->files() as $path => $source) {
            // Skip names that are completed at runtime (fa-sort-{{ $dir }}, fa-${icon}, 'fa-' + name).
            preg_match_all('/\bfa-[a-z0-9]+(?:-[a-z0-9]+)*(?![a-z0-9{$-])/', $source, $used);

            foreach (array_unique($used[0]) as $name) {
                if (! isset($defined[$name])) {
                    $missing[] = "{$path}: {$name}";
                }
            }
        }

        $this->assertSame([], $missing, "Unknown Font Awesome icons render blank:\n".implode("\n", $missing));
    }

    private function assertNoMatches(string $pattern): void
    {
        $violations = [];

        foreach ($this->files() as $path => $source) {
            foreach (explode("\n", $source) as $index => $line) {
                if (preg_match($pattern, $line, $match)) {
                    $violations[] = $path.':'.($index + 1).' '.$match[0];
                }
            }
        }

        $this->assertSame([], $violations, implode("\n", $violations));
    }

    /**
     * Read an opening tag, skipping ">" inside Blade echoes such as {{ $item->icon }}.
     */
    private function tagAt(string $source, int $offset): string
    {
        $depth = 0;
        for ($i = $offset, $length = strlen($source); $i < $length; $i++) {
            if (substr($source, $i, 2) === '{{') {
                $depth++;
                $i++;
            } elseif (substr($source, $i, 2) === '}}') {
                $depth--;
                $i++;
            } elseif ($source[$i] === '>' && $depth <= 0) {
                break;
            }
        }

        return substr($source, $offset, $i - $offset);
    }

    /**
     * @return iterable<string, string>
     */
    private function files(): iterable
    {
        $root = realpath(__DIR__.'/../../resources');

        foreach (['views' => '.blade.php', 'js' => '.js'] as $directory => $suffix) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$directory));

            foreach ($iterator as $file) {
                $relative = substr($file->getPathname(), strlen($root) + 1);

                if (! str_ends_with($relative, $suffix) || array_any(self::EXCLUDED, static fn (string $prefix): bool => str_starts_with($relative, $prefix))) {
                    continue;
                }

                yield $relative => (string) file_get_contents($file->getPathname());
            }
        }
    }
}
