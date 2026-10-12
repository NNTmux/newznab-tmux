<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminReleaseNameViewTest extends TestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function unresolvedSubjects(): iterable
    {
        yield 'rar' => ['[2/3] "1b0ObvkpLy4cDgLV0fW3GoPaxUHKj.rar"', '1b0ObvkpLy4cDgLV0fW3GoPaxUHKj.rar'];
        yield 'enc' => ['[01/10] - "Patritcy_A.enc"', 'Patritcy_A.enc'];
        yield 'mkv' => ['[1/9] - "039vphxklmk0hwouhsfx.mkv"', '039vphxklmk0hwouhsfx.mkv'];
        yield 'other rar' => ['[2/10] "Gd71jJg4qdgsblviMRr2Wg.rar"', 'Gd71jJg4qdgsblviMRr2Wg.rar'];
        yield 'encoded quotes' => ['[1/2] - #34;Unknown.File.enc#34;', 'Unknown.File.enc'];
        yield 'spaced counters' => ['[ 01 / 10 ]-"Unknown.File.enc"', 'Unknown.File.enc'];
        yield 'HTML named quotes' => ['[1/2] - &quot;Unknown.File.enc&quot;', 'Unknown.File.enc'];
        yield 'HTML numeric quotes' => ['[1/2] - &#34;Unknown.File.enc&#34;', 'Unknown.File.enc'];
        yield 'numeric filename' => ['[1/2] - "0"', '0'];
    }

    #[DataProvider('unresolvedSubjects')]
    public function test_unresolved_names_display_the_filename_without_mutating_recovery_inputs(string $subject, string $filename): void
    {
        $release = (object) ['searchname' => $subject, 'name' => $subject.' yEnc'];
        $html = $this->renderName($release);

        $this->assertSame($filename, trim(strip_tags($html)));
        $this->assertStringContainsString('title="'.$filename.'"', $html);
        $this->assertStringNotContainsString('text-xs', $html);
        $this->assertSame($subject, $release->searchname);
        $this->assertSame($subject.' yEnc', $release->name);
    }

    public function test_a_recovered_title_keeps_a_distinct_original_filename(): void
    {
        $html = $this->renderName((object) ['searchname' => 'Some.Movie.2020.1080p-GRP', 'name' => '[1/2] - "Unknown.File.rar" yEnc (1/20)']);

        $this->assertStringContainsString('Some.Movie.2020.1080p-GRP', $html);
        $this->assertStringContainsString('Unknown.File.rar', $html);
        $this->assertStringContainsString('text-xs', $html);
        $this->assertStringNotContainsString('[1/2]', $html);
        $this->assertStringNotContainsString('yEnc', $html);
    }

    public function test_filename_html_is_escaped(): void
    {
        $html = $this->renderName((object) ['searchname' => '[1/2] - "<img src=x onerror=alert(1)>.enc"', 'name' => null]);

        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;.enc', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('[1/2]', $html);
    }

    /** @return iterable<string, array{string}> */
    public static function otherNames(): iterable
    {
        yield 'clean title' => ['Some.Movie.2020.1080p-GRP'];
        yield 'meaningful brackets' => ['[Director Cut] Some.Movie.2020'];
        yield 'unmatched quote' => ['[1/2] - "Unknown.File.enc'];
        yield 'empty file' => ['[1/2] - "" yEnc'];
        yield 'meaningful suffix' => ['[1/2] - "Unknown.File.enc" extra title'];
        yield 'not a numeric counter' => ['[a/b] - "Unknown.File.enc"'];
        yield 'mismatched quotes' => ['[1/2] - "Unknown.File.enc#34;'];
        yield 'blank file' => ['[1/2] - "   " yEnc'];
        yield 'extra quoted title' => ['[1/2] "Unknown.File.enc" "extra title"'];
        yield 'extra encoded title' => ['[1/2] #34;Unknown.File.enc#34; #34;extra title#34;'];
    }

    #[DataProvider('otherNames')]
    public function test_other_names_are_preserved(string $name): void
    {
        $html = $this->renderName((object) ['searchname' => $name, 'name' => null]);

        $this->assertSame($name, html_entity_decode(trim(strip_tags($html)), ENT_QUOTES));
    }

    private function renderName(object $release): string
    {
        return Blade::render('<x-admin.release-name :release="$release" />', ['release' => $release]);
    }
}
