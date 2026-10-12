<?php

declare(strict_types=1);

namespace App\Services\Releases\Fingerprint;

use FFI;
use Throwable;

final class NativeMetadataParser
{
    /** @var array<string, FFI> */
    private static array $libraries = [];

    /** @return array<string, mixed>|null */
    public function parsePar2(string $bytes): ?array
    {
        return $this->parseJson('nntmux_par2_metadata_json', $bytes);
    }

    /** @return array<string, mixed>|null */
    public function parseFormats(string $bytes): ?array
    {
        return $this->parseJson('nntmux_format_metadata_json', $bytes);
    }

    /** @return array<string, mixed>|null */
    private function parseJson(string $function, string $bytes): ?array
    {
        $library = $this->library();
        if ($library === null || strlen($bytes) > 67_108_864) {
            return null;
        }

        $encoded = null;
        try {
            $length = strlen($bytes);
            $buffer = $length > 0 ? FFI::new('unsigned char['.$length.']') : FFI::new('unsigned char[1]');
            if ($length > 0) {
                FFI::memcpy($buffer, $bytes, $length);
            }
            $pointer = $library->{$function}($buffer, $length); // @phpstan-ignore method.notFound
            if (FFI::isNull($pointer)) {
                return null;
            }
            try {
                $encoded = FFI::string($pointer);
            } finally {
                $library->nntmux_metadata_string_free($pointer); // @phpstan-ignore method.notFound
            }
        } catch (Throwable) {
            return null;
        }

        // Preserve exact Rust u64 values that exceed PHP_INT_MAX instead of rounding them to floats.
        $decoded = json_decode($encoded, true, 512, JSON_BIGINT_AS_STRING);

        return is_array($decoded) ? $decoded : null;
    }

    private function library(): ?FFI
    {
        if (PHP_OS_FAMILY !== 'Linux' || PHP_SAPI !== 'cli' || ! extension_loaded('FFI')) {
            return null;
        }
        $path = (string) config('yenc.native_library', '');
        $resolved = realpath($path);
        if ($resolved === false || ! is_file($resolved) || ! is_readable($resolved)) {
            return null;
        }
        $key = getmypid().':'.$resolved;
        if (isset(self::$libraries[$key])) {
            return self::$libraries[$key];
        }

        try {
            self::$libraries[$key] = FFI::cdef(<<<'C'
                char* nntmux_par2_metadata_json(const unsigned char* data, size_t length);
                char* nntmux_format_metadata_json(const unsigned char* data, size_t length);
                void nntmux_metadata_string_free(char* value);
                C, $resolved);
        } catch (Throwable) {
            return null;
        }

        return self::$libraries[$key];
    }
}
