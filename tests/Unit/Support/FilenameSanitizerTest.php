<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\FilenameSanitizer;
use PHPUnit\Framework\TestCase;

final class FilenameSanitizerTest extends TestCase
{
    public function test_sanitize_replaces_forbidden_and_unicode_path_characters(): void
    {
        $sanitized = FilenameSanitizer::sanitize(' Show / Name \\ Part ⁄ Alt ∕ Dual ⧸ Full／Back＼ ');

        $this->assertSame('Show_Name_Part_Alt_Dual_Full_Back', $sanitized);
    }

    public function test_sanitize_removes_control_characters_and_reserved_windows_characters(): void
    {
        $sanitized = FilenameSanitizer::sanitize("Bad\0Name:*?\"<>|\x1FTest");

        $this->assertSame('BadName_Test', $sanitized);
    }

    public function test_sanitize_collapses_commas_and_whitespace_to_single_underscores(): void
    {
        $sanitized = FilenameSanitizer::sanitize(' The   File,  Name , Part 2 ');

        $this->assertSame('The_File_Name_Part_2', $sanitized);
    }

    public function test_sanitize_uses_fallback_for_empty_or_dot_only_names(): void
    {
        $this->assertSame('release-123', FilenameSanitizer::sanitize(null, 'release-123'));
        $this->assertSame('release-123', FilenameSanitizer::sanitize('', 'release-123'));
        $this->assertSame('release-123', FilenameSanitizer::sanitize('...___---', 'release-123'));
    }

    public function test_sanitize_truncates_to_safe_length(): void
    {
        $sanitized = FilenameSanitizer::sanitize(str_repeat('a', 300));

        $this->assertSame(200, mb_strlen($sanitized));
        $this->assertSame(str_repeat('a', 200), $sanitized);
    }

    public function test_ascii_fallback_returns_safe_ascii_filename(): void
    {
        $fallback = FilenameSanitizer::asciiFallback('Résumé／Season 1\\Finale');

        $this->assertMatchesRegularExpression('/^[\x20-\x7E]+$/', $fallback);
        $this->assertStringNotContainsString('/', $fallback);
        $this->assertStringNotContainsString('\\', $fallback);
        $this->assertSame($fallback, FilenameSanitizer::asciiFallback($fallback));
    }
}
