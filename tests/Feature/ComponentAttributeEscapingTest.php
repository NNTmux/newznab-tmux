<?php

declare(strict_types=1);

namespace Tests\Feature;

use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * A value written as attr="{{ ... }}" on a component tag is escaped before the component
 * receives it. When that attribute is a declared prop the component escapes it again, so
 * "&" reaches the browser as "&amp;amp;" and query strings break.
 */
class ComponentAttributeEscapingTest extends TestCase
{
    private const string SEARCH_URL = 'http://localhost/search?search=aeroplane&search_type=adv';

    public function test_button_link_escapes_a_query_string_once(): void
    {
        $this->blade('<x-button-link :href="$url">Advanced Search</x-button-link>', ['url' => self::SEARCH_URL])
            ->assertSee('href="http://localhost/search?search=aeroplane&amp;search_type=adv"', false)
            ->assertDontSee('&amp;amp;', false);
    }

    public function test_icon_button_escapes_its_label_once(): void
    {
        $this->blade('<x-icon-button icon="fa-solid fa-trash" :label="$label" />', ['label' => "Remove Don't Look Up & more"])
            ->assertSee('aria-label="Remove Don&#039;t Look Up &amp; more"', false)
            ->assertDontSee('&amp;#039;', false);
    }

    public function test_no_view_passes_an_escaped_echo_into_a_declared_component_prop(): void
    {
        $props = $this->declaredProps();
        $offenders = [];

        foreach (Finder::create()->files()->in(resource_path('views'))->exclude('vendor')->name('*.blade.php') as $file) {
            foreach ($this->componentTags($file->getContents()) as [$component, $attributes, $line]) {
                foreach ($this->echoedAttributes($attributes) as $attribute) {
                    if (in_array($attribute, $props[$component] ?? [], true)) {
                        $offenders[] = sprintf('%s:%d <x-%s %s="{{ … }}">', $file->getRelativePathname(), $line, $component, $attribute);
                    }
                }
            }
        }

        $this->assertSame([], $offenders, 'Bind these props with :prop="…" so they are escaped once.');
    }

    /**
     * @return array<string, list<string>> component name => prop names in kebab-case
     */
    private function declaredProps(): array
    {
        $props = [];
        $root = resource_path('views/components');

        foreach (Finder::create()->files()->in($root)->name('*.blade.php') as $file) {
            $name = str_replace(['/', '.blade.php'], ['.', ''], $file->getRelativePathname());
            $props[$name] = [];

            if (preg_match('/@props\(\[(.*?)\]\)/s', $file->getContents(), $block) === 1) {
                preg_match_all("/^\s*'([A-Za-z]+)'\s*(?:=>|,|$)/m", $block[1], $names);
                $props[$name] = array_map(
                    static fn (string $prop): string => strtolower((string) preg_replace('/(?<!^)[A-Z]/', '-$0', $prop)),
                    $names[1]
                );
            }
        }

        return $props;
    }

    /**
     * @return list<array{0: string, 1: string, 2: int}> component name, raw attribute text, line number
     */
    private function componentTags(string $source): array
    {
        $tags = [];
        preg_match_all('/<x-([\w.\-]+)/', $source, $matches, PREG_OFFSET_CAPTURE);

        foreach ($matches[1] as [$component, $offset]) {
            $start = $offset + strlen($component);
            $quote = null;
            $length = strlen($source);

            for ($end = $start; $end < $length; $end++) {
                $char = $source[$end];
                if ($quote !== null) {
                    $quote = $char === $quote ? null : $quote;
                } elseif ($char === '"' || $char === "'") {
                    $quote = $char;
                } elseif ($char === '>') {
                    break;
                }
            }

            $tags[] = [$component, substr($source, $start, $end - $start), substr_count($source, "\n", 0, $offset) + 1];
        }

        return $tags;
    }

    /**
     * @return list<string> names of unbound attributes whose value contains a Blade echo
     */
    private function echoedAttributes(string $attributes): array
    {
        preg_match_all('/(?<![:\w\-])([\w\-]+)="([^"]*)"/', $attributes, $matches, PREG_SET_ORDER);

        return array_values(array_map(
            static fn (array $match): string => $match[1],
            array_filter($matches, static fn (array $match): bool => str_contains($match[2], '{{'))
        ));
    }
}
