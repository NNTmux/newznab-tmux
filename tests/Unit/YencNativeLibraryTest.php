<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Yenc\DecoderFactory;
use App\Services\Yenc\NativePayloadDecoder;
use App\Services\Yenc\PhpPayloadDecoder;
use App\Services\YencService;
use FFI\Exception as FfiException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\Fixtures\YencArticles;

final class YencNativeLibraryTest extends TestCase
{
    private ?string $directory = null;

    private string $compiler;

    protected function setUp(): void
    {
        parent::setUp();
        if (PHP_OS_FAMILY !== 'Linux' || PHP_SAPI !== 'cli' || PHP_ZTS || ! extension_loaded('FFI')
            || in_array(strtolower((string) ini_get('ffi.enable')), ['', '0', 'false', 'off'], true)) {
            $this->markTestSkipped('Native ABI regressions require Linux CLI, NTS PHP and FFI.');
        }
        $compiler = (new ExecutableFinder)->find('cc');
        if ($compiler === null) {
            $this->markTestSkipped('Native ABI regressions require a C compiler.');
        }
        $this->compiler = $compiler;
        $this->directory = sys_get_temp_dir().'/nntmux-yenc-abi-'.bin2hex(random_bytes(8));
        mkdir($this->directory, 0700);
    }

    protected function tearDown(): void
    {
        if ($this->directory !== null) {
            $library = $this->directory.'/library.so';
            if (is_file($library)) {
                unlink($library);
            }
            rmdir($this->directory);
        }
        parent::tearDown();
    }

    /** @return iterable<string, array{bool}> */
    public static function crcBuilds(): iterable
    {
        yield 'decode-only library' => [false];
        yield 'CRC-enabled library' => [true];
    }

    #[DataProvider('crcBuilds')]
    public function test_optional_crc_preserves_native_selection_and_strict_checksums(bool $withCrc): void
    {
        $path = $this->compile($withCrc ? ['WITH_CRC'] : []);
        $data = YencArticles::bytes();
        foreach (['auto', 'native'] as $mode) {
            $decoder = (new DecoderFactory(new NullLogger))->make($mode, $path);
            $this->assertInstanceOf(NativePayloadDecoder::class, $decoder);
            $this->assertSame('1.1.1', $decoder->version());
            $this->assertSame($withCrc ? hash('crc32b', $data) : null, $decoder->crc32($data));
            $article = YencArticles::article($data);
            $this->assertSame($data, (new YencService($decoder))->decode($article));
        }

        $article = str_replace(hash('crc32b', $data), 'deadbeef', YencArticles::article($data));
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('CRC32 checksums do not match');
        (new YencService(new NativePayloadDecoder($path)))->decode($article);
    }

    public function test_bad_crc_self_check_is_not_treated_as_an_absent_capability(): void
    {
        $path = $this->compile(['WITH_CRC', 'BAD_CRC']);
        $factory = new DecoderFactory(new NullLogger);
        $this->assertInstanceOf(PhpPayloadDecoder::class, $factory->make('auto', $path));
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('RapidYenc failed its CRC32 known-answer check.');
        $factory->make('native', $path);
    }

    public function test_missing_required_decoder_symbol_is_not_swallowed(): void
    {
        $path = $this->compile(['OMIT_DECODER']);
        $this->expectException(FfiException::class);
        $this->expectExceptionMessage("Failed resolving C function 'rapidyenc_decode_ex'");
        new NativePayloadDecoder($path);
    }

    /** @param list<string> $defines */
    private function compile(array $defines): string
    {
        $path = $this->directory.'/library.so';
        $command = [$this->compiler, '-shared', '-fPIC', '-O2'];
        foreach ($defines as $define) {
            $command[] = '-D'.$define;
        }
        (new Process([...$command, __DIR__.'/../Fixtures/rapidyenc-abi.c', '-o', $path]))->mustRun();

        return $path;
    }
}
