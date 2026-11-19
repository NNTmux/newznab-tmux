<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\ReleaseFile;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReleaseFileTest extends TestCase
{
    #[DataProvider('storableNames')]
    public function test_real_file_names_are_storable(string $name): void
    {
        $this->assertTrue(ReleaseFile::isStorableName($name));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function storableNames(): array
    {
        return [
            'ascii' => ['Example.Movie.2026.1080p.mkv'],
            'path with spaces' => ['Sample/Example Movie (2026).mkv'],
            'utf-8' => ['Åland.久.mkv'],
            'latin-1 bytes' => ["Fran\xE7ais.mkv"],
            'c1 mojibake' => ["It\xC2\x92s.A.Show.mkv"],
        ];
    }

    #[DataProvider('unstorableNames')]
    public function test_empty_names_and_names_with_control_characters_are_not_storable(string $name): void
    {
        $this->assertFalse(ReleaseFile::isStorableName($name));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unstorableNames(): array
    {
        return [
            'empty' => [''],
            'nul' => ["Example\x00.mkv"],
            'tab' => ["Example\t.mkv"],
            'newline' => ["Example\n.mkv"],
            'unit separator' => ["Example\x1F.mkv"],
            'delete' => ["Example\x7F.mkv"],
            'unreadable archive header' => ["Om\x1C?3?jn??\x07p|\tQ?I\x17\x13?\x15.?\xED\xA5\x9EUm?|M?\x1A\x1A{?"],
        ];
    }

    public function test_an_unstorable_name_is_not_added_to_a_release(): void
    {
        $this->assertSame(0, ReleaseFile::addReleaseFiles(1, "Example\x01.mkv", 1024, 0, 0));
    }
}
