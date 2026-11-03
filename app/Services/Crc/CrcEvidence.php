<?php

declare(strict_types=1);

namespace App\Services\Crc;

use DateTimeImmutable;
use DateTimeZone;

final readonly class CrcEvidence
{
    public const string WHOLE_FILE = 'whole-file';

    public const string ARCHIVE_VOLUME = 'archive-volume';

    public const string PARTIAL = 'partial';

    public const string UNKNOWN = 'unknown';

    private function __construct(
        public string $crcHash,
        public string $osoHash,
        public int $fileSize,
        public ?string $fileDate,
        public string $name,
        public string $scope,
    ) {}

    /** @param array<string, mixed> $file */
    public static function fromArray(array $file): ?self
    {
        $crc = self::normalizeCrc($file['crc32'] ?? $file['crchash'] ?? null);
        $oso = self::normalizeOso($file['oso_info']['hash'] ?? $file['oso'] ?? $file['osohash'] ?? null);
        $size = self::normalizeSize($file['size'] ?? $file['filesize'] ?? null);
        $name = is_string($file['name'] ?? null) ? $file['name'] : '';
        $scope = $file['checksum_scope'] ?? self::WHOLE_FILE;

        if (! is_string($scope)) {
            return null;
        }

        $scope = match ($scope) {
            'whole', 'file', 'whole_file' => self::WHOLE_FILE,
            'archive_volume' => self::ARCHIVE_VOLUME,
            default => $scope,
        };

        // Only the strict header reader can prove that a final split volume reports
        // the whole uncompressed member's CRC rather than a packed-volume checksum.
        $verifiedFinalHeader = ($file['source'] ?? null) === 'rar_header'
            && ($file['header_crc_verified'] ?? false) === true
            && ($file['archive_format'] ?? null) === 'rar4'
            && isset($file['checksum_scope']) && $scope === self::WHOLE_FILE && $crc !== ''
            && ! empty($file['split_before']) && empty($file['split_after'])
            && empty($file['encrypted']);

        if ($size === null || ($crc === '' && $oso === '') || ! self::isEligibleName($name) || ! empty($file['is_dir'])
            || ! empty($file['directory'])
            || ((! empty($file['split']) || ! empty($file['split_before'])) && ! $verifiedFinalHeader)
            || ! empty($file['split_after']) || ! empty($file['partial']) || ! empty($file['packed_crc'])
            || $scope !== self::WHOLE_FILE) {
            return null;
        }

        return new self($crc, $oso, $size, self::normalizeDate($file['date'] ?? $file['filedate'] ?? null), $name, $scope);
    }

    public static function isEligibleName(string $name): bool
    {
        return preg_match('/\.(nfo|sfv|diz|txt|url|htm|html|jpg|jpeg|png|gif|par2?|srr|srs|nzb)$/i', $name) !== 1
            && preg_match('~(?:^|[/\\\\._ -])sample(?:[/\\\\._ -]|$)~i', $name) !== 1
            && preg_match('~(?:^|[/\\\\])(?:proof|subs)(?:[/\\\\._ -]|$)~i', $name) !== 1;
    }

    public static function normalizeCrc(mixed $value): string
    {
        // Integer zero is the legacy "not available" sentinel; a textual zero is a reported CRC.
        if (! is_string($value)) {
            return '';
        }

        $value = trim($value);

        return preg_match('/^[a-fA-F0-9]{1,8}$/D', $value) === 1
            ? strtoupper(str_pad($value, 8, '0', STR_PAD_LEFT)) : '';
    }

    public static function normalizeOso(mixed $value): string
    {
        return is_string($value) && preg_match('/^[a-fA-F0-9]{16}$/D', trim($value)) === 1
            ? strtolower(trim($value)) : '';
    }

    public static function normalizeSize(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return 0;
        }
        if (is_int($value)) {
            return $value >= 0 ? $value : null;
        }
        if (is_float($value)) {
            return is_finite($value) && $value >= 0 && $value < PHP_INT_MAX && floor($value) === $value
                ? (int) $value : null;
        }
        if (! is_string($value) || preg_match('/^\d+$/D', $value) !== 1) {
            return null;
        }
        $digits = ltrim($value, '0') ?: '0';
        $max = (string) PHP_INT_MAX;

        return strlen($digits) < strlen($max) || (strlen($digits) === strlen($max) && strcmp($digits, $max) <= 0)
            ? (int) $digits : null;
    }

    public static function normalizeDate(mixed $value): ?string
    {
        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            $timestamp = self::normalizeSize($value);

            return $timestamp !== null && $timestamp > 0 && $timestamp <= 253402300799
                ? gmdate('Y-m-d', $timestamp) : null;
        }
        if (! is_string($value) || preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:[ T]\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})?)?$/D', $value, $parts) !== 1
            || ! checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
            return null;
        }
        try {
            $date = new DateTimeImmutable($value, new DateTimeZone('UTC'));
            $errors = DateTimeImmutable::getLastErrors();

            return $errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0)
                ? $date->format('Y-m-d') : null;
        } catch (\Exception) {
            return null;
        }
    }
}
