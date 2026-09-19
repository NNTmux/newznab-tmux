<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Yenc\NativePayloadDecoder;
use App\Services\YencService;
use Tests\Fixtures\YencArticles;

final class YencNativeDecoderTest extends YencDecoderTest
{
    protected function setUp(): void
    {
        parent::setUp();
        if (! getenv('YENC_NATIVE_LIBRARY') || ! extension_loaded('FFI') || in_array(strtolower((string) ini_get('ffi.enable')), ['', '0', 'false', 'off'], true)) {
            $this->markTestSkipped('Set YENC_NATIVE_LIBRARY and enable CLI FFI to run native parity tests.');
        }
    }

    protected function service(): YencService
    {
        return new YencService(new NativePayloadDecoder((string) getenv('YENC_NATIVE_LIBRARY')));
    }

    public function test_reports_rapidyenc_decoder(): void
    {
        $this->assertSame('RapidYenc', $this->service()->decoderName());
    }

    public function test_crc32_matches_php_hash(): void
    {
        $decoder = new NativePayloadDecoder((string) getenv('YENC_NATIVE_LIBRARY'));
        $data = YencArticles::randomBytes(65536);
        $crc = $decoder->crc32($data);
        $this->assertNotNull($crc, 'Rebuild the native parity library with CRC enabled.');
        $this->assertSame(hash('crc32b', $data), $crc);
        $this->assertSame('00000000', $decoder->crc32(''));
    }

    public function test_reports_library_version(): void
    {
        $decoder = new NativePayloadDecoder((string) getenv('YENC_NATIVE_LIBRARY'));
        $version = $decoder->version();
        $this->assertMatchesRegularExpression('/\A\d+\.\d+/', $version);
        $this->assertSame($version, $this->service()->decoderVersion());
    }
}
