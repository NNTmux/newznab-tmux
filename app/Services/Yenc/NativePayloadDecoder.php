<?php

declare(strict_types=1);

namespace App\Services\Yenc;

use FFI;
use FFI\Exception as FfiException;
use RuntimeException;

final class NativePayloadDecoder implements RawPayloadDecoder
{
    /** @var array<string, array{decoder: FFI, crc: FFI|null}> */
    private static array $libraries = [];

    private readonly FFI $library;

    private readonly ?FFI $crcLibrary;

    public function __construct(string $path)
    {
        if (PHP_OS_FAMILY !== 'Linux' || PHP_SAPI !== 'cli' || PHP_ZTS || ! extension_loaded('FFI')) {
            throw new RuntimeException('Native yEnc requires Linux CLI, non-threaded PHP and FFI.');
        }
        $resolved = realpath($path);
        if ($path === '' || $resolved === false || ! is_file($resolved)) {
            throw new RuntimeException('YENC_NATIVE_LIBRARY must point to a readable RapidYenc shared library.');
        }
        $key = getmypid().':'.$resolved;
        if (isset(self::$libraries[$key])) {
            $this->library = self::$libraries[$key]['decoder'];
            $this->crcLibrary = self::$libraries[$key]['crc'];

            return;
        }
        $this->library = FFI::cdef(<<<'C'
            void rapidyenc_decode_init(void);
            size_t rapidyenc_decode_ex(int is_raw, const void* src, void* dest, size_t src_length, int* state);
            int rapidyenc_version(void);
            C, $resolved);
        // FFI exposes symbols from the pinned C header as dynamic methods.
        $this->library->rapidyenc_decode_init(); // @phpstan-ignore method.notFound
        if ($this->decode("klm=@=J=M=}..\x0b==") !== "ABC\xd6\xe0\xe3\x13\x04\x04\xe1\xd3") {
            throw new RuntimeException('RapidYenc failed its known-answer check.');
        }
        $this->crcLibrary = $this->loadCrcLibrary($resolved);
        if ($this->crcLibrary !== null && $this->crc32('ABC') !== 'a3830348') {
            throw new RuntimeException('RapidYenc failed its CRC32 known-answer check.');
        }
        self::$libraries[$key] = ['decoder' => $this->library, 'crc' => $this->crcLibrary];
    }

    public function decode(string $payload): string
    {
        return $this->decodeRaw($payload);
    }

    public function decodeRaw(string $payload): string
    {
        $length = strlen($payload);
        if ($length === 0) {
            return '';
        }
        $output = $this->library->new("char[{$length}]");
        /** @var int $written */
        $written = $this->library->rapidyenc_decode_ex(0, $payload, $output, $length, null); // @phpstan-ignore method.notFound
        if ($written < 0 || $written > $length) {
            throw new RuntimeException('RapidYenc returned an invalid decoded length.');
        }

        return FFI::string($output, $written);
    }

    /**
     * Hardware-accelerated CRC32 (identical to PHP's crc32b).
     *
     * @return string|null Lowercase eight-character hex digest, or null when the loaded library was built without CRC support.
     */
    public function crc32(string $data): ?string
    {
        if ($this->crcLibrary === null) {
            return null;
        }
        /** @var int $crc */
        $crc = $this->crcLibrary->rapidyenc_crc($data, strlen($data), 0); // @phpstan-ignore method.notFound

        return str_pad(dechex($crc), 8, '0', STR_PAD_LEFT);
    }

    /** RapidYenc version string (for example "1.1.1"). */
    public function version(): string
    {
        /** @var int $version */
        $version = $this->library->rapidyenc_version(); // @phpstan-ignore method.notFound

        return sprintf('%d.%d.%d', ($version >> 16) & 0xFF, ($version >> 8) & 0xFF, $version & 0xFF);
    }

    private function loadCrcLibrary(string $path): ?FFI
    {
        try {
            $library = FFI::cdef(<<<'C'
                void rapidyenc_crc_init(void);
                uint32_t rapidyenc_crc(const void* src, size_t src_length, uint32_t init_crc);
                C, $path);
        } catch (FfiException $exception) {
            // CRC-disabled builds omit these exports; FFI resolves them during cdef, not the call.
            if (! in_array($exception->getMessage(), [
                "Failed resolving C function 'rapidyenc_crc_init'",
                "Failed resolving C function 'rapidyenc_crc'",
            ], true)) {
                throw $exception;
            }

            return null;
        }
        $library->rapidyenc_crc_init(); // @phpstan-ignore method.notFound

        return $library;
    }
}
