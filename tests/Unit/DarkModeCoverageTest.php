<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Light-only colors must come with a dark-mode counterpart in the same class list,
 * so pages stay readable without the global dark-mode override CSS.
 */
class DarkModeCoverageTest extends TestCase
{
    /**
     * @var list<string>
     */
    private const EXCLUDED = ['forum/', 'components/forum/', 'vendor/', 'emails/', 'components/mail/', 'errors/'];

    private const TINTS = 'red|danger|warning|success|info|primary|amber|orange|purple|violet|indigo|pink|emerald|teal|cyan|sky';

    #[Test]
    public function light_only_text_and_surfaces_have_a_dark_variant(): void
    {
        $rules = [
            'dark gray text needs dark:text-*' => ['/(?<![\w:\/-])text-(?:gray|slate)-(?:700|800|900)\b/', 'dark:text-'],
            'bg-white needs dark:bg-*' => ['/(?<![\w:\/-])bg-white\b(?!\/)/', 'dark:bg-'],
            'pale tint needs dark:bg-*' => ['/(?<![\w:\/-])bg-(?:'.self::TINTS.')-(?:50|100)\b/', 'dark:bg-'],
            'tinted text needs dark:text-*' => ['/(?<![\w:\/-])text-(?:'.self::TINTS.')-(?:700|800|900)\b/', 'dark:text-'],
        ];
        $violations = [];

        foreach ($this->views() as $path => $source) {
            preg_match_all('/class="([^"]*)"/', $source, $classLists, PREG_OFFSET_CAPTURE);

            foreach ($classLists[1] as [$classes, $offset]) {
                foreach ($rules as $rule => [$pattern, $darkPrefix]) {
                    if (preg_match($pattern, $classes) && ! str_contains($classes, $darkPrefix)) {
                        $violations[] = $path.':'.(substr_count($source, "\n", 0, $offset) + 1).' '.$rule;
                    }
                }
            }
        }

        $this->assertSame([], $violations, implode("\n", $violations));
    }

    #[Test]
    public function hover_colors_are_not_overridden_by_a_plain_dark_background(): void
    {
        $violations = [];

        foreach ($this->views() as $path => $source) {
            preg_match_all('/class="([^"]*)"/', $source, $classLists);

            foreach ($classLists[1] as $classes) {
                // "hover:bg-x dark:bg-y" with no dark hover color means the dark background replaces the hover state.
                if (preg_match('/(?<![\w:-])hover:bg-gray-\d+ dark:bg-gray-\d+/', $classes, $match) && ! str_contains($classes, 'dark:hover:bg-')) {
                    $violations[] = $path.': '.$match[0];
                }
            }
        }

        $this->assertSame([], $violations, "Use dark:hover:bg-* for hover states:\n".implode("\n", $violations));
    }

    /**
     * @return iterable<string, string>
     */
    private function views(): iterable
    {
        $root = realpath(__DIR__.'/../../resources/views');
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

        foreach ($iterator as $file) {
            $relative = substr($file->getPathname(), strlen($root) + 1);

            if (str_ends_with($relative, '.blade.php') && ! array_any(self::EXCLUDED, static fn (string $prefix): bool => str_starts_with($relative, $prefix))) {
                yield $relative => (string) file_get_contents($file->getPathname());
            }
        }
    }
}
