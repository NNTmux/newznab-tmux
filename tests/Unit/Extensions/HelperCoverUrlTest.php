<?php

declare(strict_types=1);

namespace Tests\Unit\Extensions;

use Tests\TestCase;

class HelperCoverUrlTest extends TestCase
{
    public function test_unzip_gzip_file_returns_uncompressed_contents(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'nntmux-gzip-');
        $this->assertIsString($path);

        file_put_contents($path, gzencode('gzip fixture contents'));

        try {
            $this->assertSame('gzip fixture contents', unzipGzipFile($path));
        } finally {
            @unlink($path);
        }
    }

    public function test_get_release_cover_returns_placeholder_for_negative_bookinfo_id(): void
    {
        $url = getReleaseCover((object) ['bookinfo_id' => -2]);

        $this->assertStringContainsString('assets/images/no-cover.png', $url);
    }

    public function test_get_release_cover_returns_placeholder_for_negative_music_id(): void
    {
        $url = getReleaseCover((object) ['musicinfo_id' => -2]);

        $this->assertStringContainsString('assets/images/no-cover.png', $url);
    }
}
