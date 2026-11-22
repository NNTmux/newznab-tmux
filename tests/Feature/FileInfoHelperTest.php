<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class FileInfoHelperTest extends TestCase
{
    private ?string $path = null;

    protected function tearDown(): void
    {
        if ($this->path !== null && is_file($this->path)) {
            unlink($this->path);
        }

        parent::tearDown();
    }

    public function test_finfo_fallback_describes_a_text_file_without_triggering_a_deprecation(): void
    {
        $this->path = (string) tempnam(sys_get_temp_dir(), 'nntmux-fileinfo-');
        file_put_contents($this->path, "Release: Some.Movie.2024.1080p\r\nGroup: TEST\r\n");
        config(['nntmux_settings.magic_file_path' => null]);
        $this->withoutDeprecationHandling();

        $description = fileInfo($this->path);

        $this->assertStringContainsString('ASCII text', $description);
    }
}
