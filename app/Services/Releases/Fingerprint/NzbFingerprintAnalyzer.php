<?php

declare(strict_types=1);

namespace App\Services\Releases\Fingerprint;

use App\Services\Yenc\ArticleFrame;
use App\Services\YencService;
use RuntimeException;

final class NzbFingerprintAnalyzer
{
    public function __construct(private readonly YencService $yenc) {}

    /** @return array<string, mixed> */
    public function analyzeManifest(string $xml): array
    {
        if (strlen($xml) > 33_554_432 || str_contains(strtoupper($xml), '<!ENTITY')) {
            throw new RuntimeException('NZB exceeds the metadata parser budget or contains an entity declaration.');
        }
        $xml = preg_replace(
            '/<!DOCTYPE\s+nzb\s+PUBLIC\s+["\']-\/\/newzBin\/\/DTD NZB 1\.1\/\/EN["\']\s+["\']http:\/\/www\.newzbin\.com\/DTD\/nzb\/nzb-1\.1\.dtd["\']\s*>/i',
            '',
            $xml,
        ) ?? $xml;
        if (str_contains(strtoupper($xml), '<!DOCTYPE')) {
            throw new RuntimeException('NZB contains an unsupported DTD.');
        }

        $previous = libxml_use_internal_errors(true);
        try {
            $document = simplexml_load_string($xml, options: LIBXML_NONET | LIBXML_NOBLANKS);
            if ($document === false || $document->getName() !== 'nzb') {
                throw new RuntimeException('Invalid NZB XML.');
            }

            $meta = [];
            $metaElements = $document->xpath('/*[local-name()="nzb"]/*[local-name()="head"]/*[local-name()="meta"]') ?: [];
            foreach ($metaElements as $element) {
                $type = trim((string) $element['type']);
                if ($type !== '') {
                    $meta[$type][] = trim((string) $element);
                }
            }

            $files = [];
            $totals = [
                'rar' => ['file_count' => 0, 'segment_count' => 0, 'advertised_bytes' => 0],
                'par2' => ['file_count' => 0, 'segment_count' => 0, 'advertised_bytes' => 0],
                'other' => ['file_count' => 0, 'segment_count' => 0, 'advertised_bytes' => 0],
            ];
            $fileElements = $document->xpath('/*[local-name()="nzb"]/*[local-name()="file"]') ?: [];
            foreach ($fileElements as $fileIndex => $element) {
                $subject = trim((string) $element['subject']);
                $kind = self::fileKind($subject);
                $segments = [];
                $segmentElements = $element->xpath('./*[local-name()="segments"]/*[local-name()="segment"]') ?: [];
                $invalidSegmentCount = 0;
                $invalidSegmentMetadataCount = 0;
                foreach ($segmentElements as $segment) {
                    $messageId = trim((string) $segment);
                    if ($messageId === '' || preg_match('/\A[^\s<>\x00-\x1f\x7f]+\z/D', trim($messageId, '<>')) !== 1) {
                        $invalidSegmentCount++;
                        continue;
                    }
                    $segmentNumber = trim((string) $segment['number']);
                    $segmentBytes = trim((string) $segment['bytes']);
                    if (preg_match('/\A[0-9]+\z/D', $segmentNumber) !== 1 || (int) $segmentNumber < 1
                        || preg_match('/\A[0-9]+\z/D', $segmentBytes) !== 1) {
                        $invalidSegmentMetadataCount++;
                    }
                    $segments[] = [
                        'number' => max(0, (int) $segmentNumber),
                        'bytes' => max(0, (int) $segmentBytes),
                        'messageid' => trim($messageId, '<>'),
                    ];
                }
                usort($segments, static fn (array $left, array $right): int => $left['number'] <=> $right['number']);
                $segmentNumbers = array_column($segments, 'number');
                $messageIds = array_column($segments, 'messageid');
                $numberSequenceValid = $segmentNumbers !== [] && $segmentNumbers === range(1, count($segmentNumbers));
                $advertisedBytes = array_sum(array_column($segments, 'bytes'));
                $groups = [];
                $groupElements = $element->xpath('./*[local-name()="groups"]/*[local-name()="group"]') ?: [];
                foreach ($groupElements as $group) {
                    $groups[] = trim((string) $group);
                }
                $files[] = [
                    'index' => (int) $fileIndex,
                    'kind' => $kind,
                    'format_hint' => self::formatHint($subject),
                    'subject' => $subject,
                    'poster' => (string) $element['poster'],
                    'date' => (int) $element['date'] ?: null,
                    'groups' => $groups,
                    'segment_count' => count($segments),
                    'source_segment_record_count' => count($segmentElements),
                    'invalid_segment_record_count' => $invalidSegmentCount,
                    'invalid_segment_metadata_count' => $invalidSegmentMetadataCount,
                    'duplicate_message_id_count' => count($messageIds) - count(array_unique($messageIds)),
                    'duplicate_segment_number_count' => count($segmentNumbers) - count(array_unique($segmentNumbers)),
                    'segment_number_sequence_valid' => $numberSequenceValid,
                    'advertised_bytes' => $advertisedBytes,
                    'segments' => $segments,
                ];
                $totals[$kind]['file_count']++;
                $totals[$kind]['segment_count'] += count($segments);
                $totals[$kind]['advertised_bytes'] += $advertisedBytes;
            }
            if ($files === []) {
                throw new RuntimeException('NZB contains no files.');
            }

            $par2ToRarPercent = $totals['rar']['advertised_bytes'] > 0
                ? round($totals['par2']['advertised_bytes'] / $totals['rar']['advertised_bytes'] * 100, 3)
                : null;

            return [
                'file_count' => count($files),
                'files' => $files,
                'totals' => $totals,
                'nzb_meta_values' => $meta,
                'par2_to_rar_advertised_percent' => $par2ToRarPercent,
                'par2_to_rar_percent_basis' => 'NZB advertised segment bytes; includes article and yEnc overhead and is not the PAR2 recovery percentage.',
            ];
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /** @param list<array<string, mixed>> $files
     *  @return list<array{file_index: int, kind: string, subject: string, segment_number: int, messageid: string, segment_bytes: int, sample_role: string}>
     */
    public function selectArticleSamples(array $files): array
    {
        $samples = [];
        $seen = [];
        foreach ($files as $file) {
            $segments = $file['segments'] ?? [];
            if (! is_array($segments) || $segments === []) {
                continue;
            }
            if (($file['kind'] ?? null) === 'par2') {
                $selected = array_map(static fn (array $segment): array => [$segment, 'all_par2_segments'], $segments);
            } else {
                $last = count($segments) - 1;
                $positions = $last === 0 ? [[0, 'first']] : [[0, 'first'], [$last, 'last']];
                if ($last > 1) {
                    array_splice($positions, 1, 0, [[intdiv($last, 2), 'middle']]);
                }
                $selected = array_map(static fn (array $sample): array => [$segments[$sample[0]], $sample[1]], $positions);
            }
            foreach ($selected as [$segment, $sampleRole]) {
                $messageId = (string) ($segment['messageid'] ?? '');
                $sampleKey = (int) $file['index']."\0".$messageId;
                if ($messageId === '' || isset($seen[$sampleKey])) {
                    continue;
                }
                $samples[] = [
                    'file_index' => (int) $file['index'],
                    'kind' => (string) $file['kind'],
                    'subject' => (string) $file['subject'],
                    'segment_number' => (int) $segment['number'],
                    'messageid' => $messageId,
                    'segment_bytes' => (int) ($segment['bytes'] ?? 0),
                    'sample_role' => $sampleRole,
                ];
                $seen[$sampleKey] = true;
            }
        }

        return $samples;
    }

    /** @param list<string> $rawHeaders
     *  @return array<string, mixed>
     */
    public function analyzeArticle(array $rawHeaders, string $rawBody): array
    {
        $values = [];
        $orderedNames = [];
        $previousName = null;
        foreach ($rawHeaders as $line) {
            $content = rtrim($line, "\r\n");
            if (($content[0] ?? '') === ' ' || ($content[0] ?? '') === "\t") {
                if ($previousName !== null) {
                    $values[$previousName] = rtrim((string) ($values[$previousName] ?? '')).' '.trim($content);
                }
                continue;
            }
            if (! str_contains($content, ':')) {
                continue;
            }
            [$name, $value] = explode(':', $content, 2);
            $normalizedName = strtolower(trim($name));
            if ($normalizedName === '') {
                continue;
            }
            $orderedNames[] = trim($name);
            $previousName = $normalizedName;
            $values[$normalizedName] = isset($values[$normalizedName])
                ? $values[$normalizedName].', '.trim($value)
                : trim($value);
        }

        $frame = ArticleFrame::parse($rawBody);
        $rawHeaderBytes = array_sum(array_map(static fn (string $line): int => str_ends_with($line, "\n") ? strlen($line) : strlen($line) + 2, $rawHeaders));
        $yenc = null;
        $decodedHash = null;
        $decodedSize = null;
        $payloadHash = null;
        $payloadLineLengths = [];
        $decodedPrefix = null;
        $formatMetadata = null;
        if ($frame !== null) {
            $decoded = $this->yenc->decodeWithFrame($rawBody);
            $payload = substr($rawBody, $frame->payloadOffset, $frame->payloadLength);
            $payloadHash = hash('sha256', $payload);
            $decodedHash = hash('sha256', $decoded[0]);
            $decodedSize = strlen($decoded[0]);
            $decodedPrefix = substr($decoded[0], 0, 65_536);
            $formatMetadata = (new NativeMetadataParser)->parseFormats($decoded[0]) ?? [
                'parser_engine' => 'php-signature-fallback',
                'signatures' => self::detectFormatSignatures($decodedPrefix),
                'headers' => [],
            ];
            if (extension_loaded('fileinfo')) {
                $fileInfo = new \finfo(FILEINFO_NONE);
                $formatMetadata['magic_file_description'] = $fileInfo->buffer($decodedPrefix) ?: null;
                $formatMetadata['mime_type'] = (new \finfo(FILEINFO_MIME_TYPE))->buffer($decodedPrefix) ?: null;
                $formatMetadata['magic_basis'] = 'first 65536 decoded bytes; heuristic identification only';
            }
            foreach (preg_split('/\r?\n/', rtrim($payload, "\r\n")) ?: [] as $line) {
                $payloadLineLengths[] = strlen($line);
            }
            $rangeStart = isset($frame->part['begin']) && ctype_digit($frame->part['begin']) ? (int) $frame->part['begin'] : null;
            $rangeEnd = isset($frame->part['end']) && ctype_digit($frame->part['end']) ? (int) $frame->part['end'] : null;
            $hasRangeField = isset($frame->part['begin']) || isset($frame->part['end']);
            $rangeIsValid = ! $hasRangeField ? null : ($rangeStart !== null && $rangeEnd !== null
                && $rangeStart >= 1 && $rangeEnd >= $rangeStart
                && (! isset($frame->header['size']) || ! ctype_digit($frame->header['size']) || $rangeEnd <= (int) $frame->header['size']));
            $ypartExpectedDecodedSize = $rangeIsValid === true ? $rangeEnd - $rangeStart + 1 : null;
            $trailerExpectedDecodedSize = isset($frame->trailer['size']) && ctype_digit($frame->trailer['size'])
                ? (int) $frame->trailer['size'] : null;
            $trailerSizeIsDecimal = ! isset($frame->trailer['size']) || ctype_digit($frame->trailer['size']);
            $headerNumericFieldsValid = true;
            foreach (['line', 'size', 'part', 'total'] as $numericField) {
                if (isset($frame->header[$numericField])
                    && (! ctype_digit($frame->header[$numericField]) || (int) $frame->header[$numericField] < 1)) {
                    $headerNumericFieldsValid = false;
                }
            }
            if ((isset($frame->header['part']) && $frame->part === null)
                || (isset($frame->header['part'], $frame->header['total'])
                    && (int) $frame->header['part'] > (int) $frame->header['total'])) {
                $headerNumericFieldsValid = false;
            }
            $multipart = $frame->part !== null;
            $headerExpectedDecodedSize = isset($frame->header['size']) && ctype_digit($frame->header['size'])
                ? (int) $frame->header['size'] : null;
            $expectedDecodedSize = $multipart ? $ypartExpectedDecodedSize : $headerExpectedDecodedSize;
            $expectedCrc = $multipart
                ? ($frame->trailer['pcrc32'] ?? null)
                : ($frame->trailer['crc32'] ?? $frame->trailer['pcrc32'] ?? null);
            $observedCrc = hash('crc32b', $decoded[0]);
            $knownHeaderFields = ['line', 'size', 'part', 'total', 'name'];
            $knownPartFields = ['begin', 'end'];
            $knownTrailerFields = ['size', 'part', 'total', 'pcrc32', 'crc32'];
            $controlLines = [];
            preg_match_all('/^(?:=ybegin|=ypart|=yend)[^\r\n]*/mi', $rawBody, $controlMatches);
            $controlLines = $controlMatches[0] ?? [];
            $yenc = [
                'name' => $frame->header['name'] ?? null,
                'file_size' => isset($frame->header['size']) ? (int) $frame->header['size'] : null,
                'line_length' => isset($frame->header['line']) ? (int) $frame->header['line'] : null,
                'part' => isset($frame->header['part']) ? (int) $frame->header['part'] : null,
                'multipart' => $multipart,
                'total_parts' => isset($frame->header['total']) ? (int) $frame->header['total'] : null,
                'header_numeric_fields_valid' => $headerNumericFieldsValid,
                'begin' => isset($frame->part['begin']) ? (int) $frame->part['begin'] : null,
                'end' => isset($frame->part['end']) ? (int) $frame->part['end'] : null,
                'ypart_range_valid' => $rangeIsValid,
                'ypart_range_decoded_size' => $ypartExpectedDecodedSize,
                'trailer_size' => isset($frame->trailer['size']) ? (int) $frame->trailer['size'] : null,
                'header_size' => $headerExpectedDecodedSize,
                'decoded_size_matches_header_size' => $headerExpectedDecodedSize === null ? null
                    : ($multipart ? ($rangeEnd !== null && $rangeEnd <= $headerExpectedDecodedSize) : $decodedSize === $headerExpectedDecodedSize),
                'trailer_size_is_decimal' => $trailerSizeIsDecimal,
                'pcrc32' => $frame->trailer['pcrc32'] ?? null,
                'crc32' => $frame->trailer['crc32'] ?? null,
                'header_fields' => $frame->header,
                'part_fields' => $frame->part,
                'trailer_fields' => $frame->trailer,
                'decoded_segment_size' => $decodedSize,
                'decoded_segment_sha256' => $decodedHash,
                'par2_first_16k_md5_candidate' => $decodedSize !== null && $decodedSize >= 16_384
                    && (! $multipart || $rangeIsValid === true && $rangeStart === 1)
                    ? hash('md5', substr($decoded[0], 0, 16_384)) : null,
                'encoded_payload_sha256' => $payloadHash,
                'payload_line_count' => count($payloadLineLengths),
                'payload_line_length_min' => $payloadLineLengths === [] ? null : min($payloadLineLengths),
                'payload_line_length_max' => $payloadLineLengths === [] ? null : max($payloadLineLengths),
                'decoded_prefix_format_signatures' => $formatMetadata['signatures'] ?? [],
                'raw_control_lines' => $controlLines,
                'header_field_order' => array_keys($frame->header),
                'part_field_order' => array_keys($frame->part ?? []),
                'trailer_field_order' => array_keys($frame->trailer),
                'unknown_header_fields' => array_diff_key($frame->header, array_flip($knownHeaderFields)),
                'unknown_part_fields' => array_diff_key($frame->part ?? [], array_flip($knownPartFields)),
                'unknown_trailer_fields' => array_diff_key($frame->trailer, array_flip($knownTrailerFields)),
                'expected_decoded_segment_size' => $expectedDecodedSize,
                'decoded_size_matches_yenc' => $expectedDecodedSize === null ? null : $expectedDecodedSize === $decodedSize,
                'decoded_size_matches_ypart_range' => $ypartExpectedDecodedSize === null ? null : $ypartExpectedDecodedSize === $decodedSize,
                'decoded_size_matches_yend_size' => $trailerExpectedDecodedSize === null ? null : $trailerExpectedDecodedSize === $decodedSize,
                'observed_decoded_crc32' => strtoupper($observedCrc),
                'segment_crc32_basis' => $multipart ? ($expectedCrc === null ? 'missing-pcrc32' : 'pcrc32') : ($expectedCrc === null ? 'missing-crc32' : 'crc32'),
                'declared_crc32_is_hex' => is_string($expectedCrc) ? preg_match('/\A[0-9a-f]{1,8}\z/i', $expectedCrc) === 1 : null,
                'declared_crc32_matches' => is_string($expectedCrc) && preg_match('/\A[0-9a-f]{1,8}\z/i', $expectedCrc) === 1
                    ? strcasecmp(str_pad($expectedCrc, 8, '0', STR_PAD_LEFT), $observedCrc) === 0 : null,
                'escape_sequence_count' => substr_count($payload, '='),
            ];
        }

        $software = [];
        foreach (['user-agent', 'x-newsreader', 'x-poster'] as $headerName) {
            $value = $values[$headerName] ?? '';
            foreach (['Nyuu', 'ngPost', 'Postie'] as $candidate) {
                if (stripos($value, $candidate) !== false) {
                    $software[$candidate] = ['header' => $headerName, 'value' => $value];
                }
            }
        }

        return [
            'raw_headers' => array_values($rawHeaders),
            'headers' => $values,
            'header_profile' => ['ordered_names' => $orderedNames, 'ordered_names_sha256' => hash('sha256', implode("\n", $orderedNames))],
            'retrieval_measurements' => [
                'raw_header_line_count' => count($rawHeaders),
                'raw_header_bytes_including_crlf' => $rawHeaderBytes,
                'body_bytes_after_nntp_dot_unstuffing' => strlen($rawBody),
                'body_line_count' => substr_count(rtrim($rawBody, "\r\n"), "\n") + ($rawBody === '' ? 0 : 1),
                'article_bytes_after_dot_unstuffing' => $rawHeaderBytes + 2 + strlen($rawBody),
                'measurement_basis' => 'Collector-side bytes after NNTP dot-unstuffing; excludes TCP/TLS, NNTP status lines, and any wire-level compression.',
            ],
            'yenc' => $yenc,
            'decoded_format_metadata' => $formatMetadata,
            'article_body_sha256' => hash('sha256', $rawBody),
            'uploader_software_candidates' => $software,
        ];
    }

    public static function fileKind(string $subject): string
    {
        if (preg_match('/\.par2\b/i', $subject) === 1) {
            return 'par2';
        }
        if (preg_match('/\.(?:part\d+\.rar|rar|r\d{2,})\b/i', $subject) === 1) {
            return 'rar';
        }

        return 'other';
    }

    public static function formatHint(string $subject): string
    {
        if (preg_match('/\.([a-z0-9]{1,8})\b/i', $subject, $matches) !== 1) {
            return 'unknown';
        }

        return strtolower($matches[1]);
    }

    /** @return list<array{name: string, offset: int, basis: string}> */
    private static function detectFormatSignatures(string $bytes): array
    {
        $signatures = [];
        foreach ([
            ['name' => 'RAR 5', 'signature' => "Rar!\x1A\x07\x01\x00", 'offset' => 0],
            ['name' => 'RAR 4', 'signature' => "Rar!\x1A\x07\x00", 'offset' => 0],
            ['name' => '7-Zip', 'signature' => "7z\xBC\xAF\x27\x1C", 'offset' => 0],
            ['name' => 'ZIP', 'signature' => "PK\x03\x04", 'offset' => 0],
            ['name' => 'gzip', 'signature' => "\x1F\x8B\x08", 'offset' => 0],
            ['name' => 'XZ', 'signature' => "\xFD7zXZ\x00", 'offset' => 0],
            ['name' => 'ISO 9660 descriptor', 'signature' => "CD001", 'offset' => 32_769],
        ] as $candidate) {
            $offset = $candidate['offset'];
            if (strlen($bytes) >= $offset + strlen($candidate['signature'])
                && substr($bytes, $offset, strlen($candidate['signature'])) === $candidate['signature']) {
                $signatures[] = ['name' => $candidate['name'], 'offset' => $offset, 'basis' => 'decoded first-segment prefix signature'];
            }
        }

        return $signatures;
    }
}
