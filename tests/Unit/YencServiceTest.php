<?php

namespace Tests\Unit;

use App\Services\YencService;
use RuntimeException;
use Tests\TestCase;

class YencServiceTest extends TestCase
{
    private YencService $yencService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->yencService = new YencService;
    }

    public function test_enabled_returns_true(): void
    {
        $this->assertTrue($this->yencService->enabled());
    }

    public function test_encode_and_decode_round_trip(): void
    {
        $originalData = 'Hello, World! This is a test of yEnc encoding.';
        $filename = 'test.txt';

        // Encode
        $encoded = $this->yencService->encode($originalData, $filename);

        // Verify encoding contains headers
        $this->assertStringContainsString('=ybegin', $encoded);
        $this->assertStringContainsString('=yend', $encoded);
        $this->assertStringContainsString('name=test.txt', $encoded);
        $this->assertStringContainsString('crc32=', $encoded);

        // Decode
        $decoded = $this->yencService->decode($encoded);

        $this->assertEquals($originalData, $decoded);
    }

    public function test_encode_respects_line_length(): void
    {
        $data = str_repeat('A', 500);
        $filename = 'test.txt';

        $encoded = $this->yencService->encode($data, $filename, 100);

        // Check that no content line exceeds 100 characters
        $lines = explode("\r\n", $encoded);
        foreach ($lines as $line) {
            // Skip header and trailer lines
            if (str_starts_with($line, '=y')) {
                continue;
            }
            $this->assertLessThanOrEqual(100, strlen($line));
        }
    }

    public function test_encode_caps_line_length_at_254(): void
    {
        $data = 'Test data';
        $filename = 'test.txt';

        $encoded = $this->yencService->encode($data, $filename, 300);

        // Should contain line=254 in header
        $this->assertStringContainsString('line=254', $encoded);
    }

    public function test_encode_throws_exception_for_invalid_line_length(): void
    {
        $this->expectException(RuntimeException::class);

        $this->yencService->encode('data', 'test.txt', 0);
    }

    public function test_decode_returns_false_for_non_yenc_data(): void
    {
        $nonYencData = 'This is just plain text.';

        $result = $this->yencService->decode($nonYencData);

        $this->assertFalse($result);
    }

    public function test_decode_ignore_returns_original_for_non_yenc_data(): void
    {
        $nonYencData = 'This is just plain text.';

        $result = $this->yencService->decodeIgnore($nonYencData);

        $this->assertEquals($nonYencData, $result);
    }

    public function test_decode_ignore_decodes_yenc_data(): void
    {
        $originalData = 'Test content for yEnc';
        $encoded = $this->yencService->encode($originalData, 'test.txt');

        $decoded = $this->yencService->decodeIgnore($encoded);

        $this->assertEquals($originalData, $decoded);
    }

    public function test_is_yenc_encoded_returns_true_for_yenc(): void
    {
        $encoded = $this->yencService->encode('test', 'test.txt');

        $this->assertTrue($this->yencService->isYencEncoded($encoded));
    }

    public function test_is_yenc_encoded_returns_false_for_plain_text(): void
    {
        $this->assertFalse($this->yencService->isYencEncoded('plain text'));
    }

    public function test_extract_metadata_returns_metadata(): void
    {
        $data = 'Test content';
        $encoded = $this->yencService->encode($data, 'myfile.dat', 128, true);

        $metadata = $this->yencService->extractMetadata($encoded);

        $this->assertNotNull($metadata);
        $this->assertEquals('myfile.dat', $metadata['name']);
        $this->assertEquals(strlen($data), $metadata['size']);
        $this->assertEquals(128, $metadata['line']);
        $this->assertNotNull($metadata['crc32']);
    }

    public function test_extract_metadata_returns_null_for_non_yenc(): void
    {
        $metadata = $this->yencService->extractMetadata('plain text');

        $this->assertNull($metadata);
    }

    public function test_encode_without_crc32(): void
    {
        $encoded = $this->yencService->encode('test', 'test.txt', 128, false);

        $this->assertStringNotContainsString('crc32=', $encoded);
    }

    public function test_encode_with_special_characters(): void
    {
        // Test with characters that need escaping (NULL, TAB, LF, CR, space, ., =)
        $data = "Hello\x00World\tTest\nNew\rLine . = End";

        $encoded = $this->yencService->encode($data, 'special.bin');
        $decoded = $this->yencService->decode($encoded);

        $this->assertEquals($data, $decoded);
    }

    public function test_encode_with_binary_data(): void
    {
        // Test with all possible byte values
        $data = '';
        for ($i = 0; $i < 256; $i++) {
            $data .= chr($i);
        }

        $encoded = $this->yencService->encode($data, 'binary.bin');
        $decoded = $this->yencService->decode($encoded);

        $this->assertEquals($data, $decoded);
    }

    public function test_service_can_be_resolved_from_container(): void
    {
        $service = app(YencService::class);

        $this->assertInstanceOf(YencService::class, $service);
    }

    public function test_facade_works(): void
    {
        $this->assertTrue(\App\Facades\Yenc::enabled());
    }
}

