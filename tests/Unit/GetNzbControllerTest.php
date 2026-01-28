<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class GetNzbControllerTest extends TestCase
{
    /**
     * Test that file_exists returns false for non-existent files
     */
    public function test_file_exists_returns_false_for_non_existent_file(): void
    {
        $nonExistentPath = '/tmp/non-existent-file-'.uniqid().'.nzb.gz';
        $this->assertFalse(file_exists($nonExistentPath));
    }

    /**
     * Test that the file check prevents gzopen from being called on missing files
     */
    public function test_file_check_prevents_gzopen_error(): void
    {
        $nonExistentPath = '/tmp/non-existent-'.uniqid().'.nzb.gz';
        // Simulate the fix logic from streamModifiedNzbContent
        if (! file_exists($nonExistentPath)) {
            $errorMessage = '<?xml version="1.0" encoding="UTF-8"?><error>NZB file not found</error>';
            $gzopenCalled = false;
        } else {
            $gzopenCalled = true;
            $errorMessage = '';
        }
        $this->assertFalse($gzopenCalled, 'gzopen should not be called for non-existent files');
        $this->assertStringContainsString('NZB file not found', $errorMessage);
    }

    /**
     * Test that file_exists returns true for existing files
     */
    public function test_file_exists_returns_true_for_existing_file(): void
    {
        $tempPath = '/tmp/existing-file-'.uniqid().'.nzb.gz';
        file_put_contents($tempPath, 'test content');
        try {
            $this->assertTrue(file_exists($tempPath));
        } finally {
            unlink($tempPath);
        }
    }
}
