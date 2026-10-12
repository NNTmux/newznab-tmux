<?php

declare(strict_types=1);

namespace App\Services\Releases\Fingerprint;

use FFI;
use Throwable;

final class NativeMetadataParser
{
    /** @var array<string, FFI> */
    private static array $libraries = [];

    /** @var array<string, FFI> */
    private static array $identityLibraries = [];

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
    public function resolveSevenZipRanges(string $packedRanges): ?array
    {
        return $this->parseJson('nntmux_seven_zip_ranges_json', $packedRanges);
    }

    /** @return array<string, mixed>|null */
    public function resolvePar2SourceRanges(string $packedRanges): ?array
    {
        return $this->parseJson('nntmux_par2_source_ranges_json', $packedRanges);
    }

    /** @param list<string> $rawHeaders
     *  @return array<string, mixed>|null
     */
    public function parseArticleIdentity(string $requestedMessageId, array $rawHeaders, ?string $overviewRow, ?int $overviewResponseCode, bool $overviewAttempted): ?array
    {
        $headerBlock = implode('', $rawHeaders);
        $packed = 'AI01'.self::packMetadataField($requestedMessageId)
            .self::packMetadataField($headerBlock)
            .($overviewRow === null ? "\xff\xff\xff\xff" : self::packMetadataField($overviewRow));

        if (strlen($packed) > 131_072) {
            return null;
        }

        return $this->parseJson('nntmux_article_identity_json', $packed, $overviewResponseCode ?? -1, (int) $overviewAttempted);
    }

    /** @return array<string, mixed>|null */
    private function parseJson(string $function, string $bytes, ?int $integerArgument = null, ?int $secondIntegerArgument = null): ?array
    {
        $identityParser = $function === 'nntmux_article_identity_json';
        $library = $this->library($identityParser);
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
            $pointer = match (true) {
                $secondIntegerArgument !== null => $library->{$function}($buffer, $length, $integerArgument, $secondIntegerArgument), // @phpstan-ignore method.notFound
                $integerArgument !== null => $library->{$function}($buffer, $length, $integerArgument), // @phpstan-ignore method.notFound
                default => $library->{$function}($buffer, $length), // @phpstan-ignore method.notFound
            };
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

    private static function packMetadataField(string $value): string
    {
        return pack('V', strlen($value)).$value;
    }

    private function library(bool $articleIdentity = false): ?FFI
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
        $libraries = $articleIdentity ? self::$identityLibraries : self::$libraries;
        if (isset($libraries[$key])) {
            return $libraries[$key];
        }

        try {
            if ($articleIdentity) {
                $definitions = <<<'C'
                char* nntmux_article_identity_json(const unsigned char* data, size_t length, int overview_response_code, int overview_attempted);
                void nntmux_metadata_string_free(char* value);
                C;
            } else {
                $definitions = <<<'C'
                char* nntmux_par2_metadata_json(const unsigned char* data, size_t length);
                char* nntmux_format_metadata_json(const unsigned char* data, size_t length);
                char* nntmux_seven_zip_ranges_json(const unsigned char* data, size_t length);
                char* nntmux_par2_source_ranges_json(const unsigned char* data, size_t length);
                void nntmux_metadata_string_free(char* value);
                C;
            }
            $library = FFI::cdef($definitions, $resolved);
            if ($articleIdentity) {
                self::$identityLibraries[$key] = $library;
            } else {
                self::$libraries[$key] = $library;
            }

            return $library;
        } catch (Throwable) {
            return null;
        }
    }
}
