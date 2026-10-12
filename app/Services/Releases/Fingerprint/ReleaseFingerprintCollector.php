<?php

declare(strict_types=1);

namespace App\Services\Releases\Fingerprint;

use App\Models\Release;
use App\Services\NNTP\NNTPService;
use App\Services\Nzb\NzbService;
use App\Services\YencService;
use Throwable;

final class ReleaseFingerprintCollector
{
    private const MAX_ARTICLE_BODY_BYTES = 2_000_000;

    private const MAX_PAR2_SET_BYTES = 67_108_864;

    public function __construct(private readonly NzbService $nzb, private readonly NzbFingerprintAnalyzer $analyzer,
        private readonly Par2MetadataParser $par2, private readonly YencService $yenc) {}

    /** @return array{status: string, data: array<string, mixed>} */
    public function collect(Release $release): array
    {
        $xml = $this->nzb->readNzbContents((string) $release->guid);
        if (! is_string($xml) || $xml === '') {
            return ['status' => 'unavailable', 'data' => ['reason' => 'The release NZB is not available locally.']];
        }

        $manifest = $this->analyzer->analyzeManifest($xml);
        $samples = $this->analyzer->selectArticleSamples($manifest['files']);
        $servers = $this->configuredServers();
        $nntp = new NNTPService;
        $observations = [];
        $errors = [];
        $serverProfiles = [];
        $serverProfileMap = [];
        $decodedPar2 = [];
        $decodedPar2Bytes = 0;
        $par2BudgetExceeded = false;
        foreach ($servers as $server) {
            try {
                $profile = $nntp->getFingerprintServerProfile($server['alternate']);
                $profileRow = $server + ['profile' => $profile];
            } catch (Throwable $exception) {
                $profile = [];
                $profileRow = $server + ['profile_error_type' => class_basename($exception)];
            }
            $serverProfiles[] = $profileRow;
            $serverKey = ($server['alternate'] ? 'alternate ' : 'primary ').$server['name'];
            $serverProfileMap[$serverKey] = $profile;
        }
        foreach ($samples as $sample) {
            foreach ($servers as $server) {
                try {
                    $queryStart = hrtime(true);
                    $serverKey = ($server['alternate'] ? 'alternate ' : 'primary ').$server['name'];
                    $capabilities = $serverProfileMap[$serverKey]['capabilities_raw'] ?? [];
                    $supportsMessageIdOverview = count(array_filter(
                        $capabilities,
                        static fn (string $line): bool => preg_match('/\AOVER\s+MSGID\b/i', $line) === 1,
                    )) > 0;
                    $article = $nntp->getFingerprintArticleByMessageID(
                        $sample['messageid'],
                        self::MAX_ARTICLE_BODY_BYTES,
                        65_536,
                        $server['alternate'],
                        $supportsMessageIdOverview,
                    );
                    $retrievalDurationMs = round((hrtime(true) - $queryStart) / 1_000_000, 3);
                    $metadata = $this->analyzer->analyzeArticle($article['headers'], $article['body']);
                    $yenc = $metadata['yenc'] ?? null;
                    $metadata['yenc_nzb_part_number_matches'] = is_array($yenc) && is_int($yenc['part'] ?? null)
                        ? $yenc['part'] === $sample['segment_number'] : null;
                    $metadata['yenc_nzb_total_parts_matches'] = is_array($yenc) && is_int($yenc['total_parts'] ?? null)
                        ? $yenc['total_parts'] === count($manifest['files'][$sample['file_index']]['segments'] ?? []) : null;
                    $overviewFormat = $serverProfileMap[$serverKey]['overview_format_raw'] ?? [];
                    $overviewFields = $this->parseOverview($article['overview_raw'], $overviewFormat);
                    $observation = [
                        'file_index' => $sample['file_index'],
                        'nzb_subject' => $sample['subject'],
                        'kind' => $sample['kind'],
                        'message_id' => $sample['messageid'],
                        'segment_number' => $sample['segment_number'],
                        'segment_bytes_from_nzb' => $sample['segment_bytes'],
                        'sample_role' => $sample['sample_role'],
                        'server' => $server['name'],
                        'provider_label' => $server['provider_label'],
                        'provider_label_basis' => $server['provider_label_basis'],
                        'server_role' => $server['alternate'] ? 'alternate' : 'primary',
                        'server_port' => $server['port'],
                        'server_tls' => $server['tls'],
                        'queried_at_utc' => now()->utc()->toIso8601String(),
                        'retrieval_duration_ms' => $retrievalDurationMs,
                        'overview_response_code' => $article['overview_response_code'],
                        'overview_raw' => $article['overview_raw'],
                        'overview_fields' => $overviewFields,
                        'overview_nzb_segment_bytes_difference' => isset($overviewFields[':bytes']) && is_numeric($overviewFields[':bytes'])
                            ? (int) $overviewFields[':bytes'] - (int) $sample['segment_bytes'] : null,
                        'command_results' => $article['command_results'],
                        'metadata' => $metadata,
                    ];
                    $observations[] = $observation;
                    if ($sample['kind'] === 'par2' && ! $server['alternate']) {
                        $decoded = $this->yenc->decodeWithFrame($article['body']);
                        $yencIntegrity = $metadata['yenc'] ?? null;
                        $sizeValid = is_array($yencIntegrity)
                            && ($yencIntegrity['header_numeric_fields_valid'] ?? false) === true
                            && ($yencIntegrity['decoded_size_matches_header_size'] ?? false) === true
                            && ($yencIntegrity['decoded_size_matches_yenc'] ?? false) === true
                            && (($yencIntegrity['multipart'] ?? false) === true
                                ? ($yencIntegrity['ypart_range_valid'] ?? false) === true
                                    && ($yencIntegrity['decoded_size_matches_yend_size'] ?? false) === true
                                    && isset($yencIntegrity['trailer_fields']['part'])
                                    && (int) $yencIntegrity['trailer_fields']['part'] === ($yencIntegrity['part'] ?? null)
                                : ($yencIntegrity['decoded_size_matches_yend_size'] ?? false) === true)
                            && ($yencIntegrity['trailer_size_is_decimal'] ?? false) === true
                            && (($yencIntegrity['multipart'] ?? false) === true
                                ? ($metadata['yenc_nzb_part_number_matches'] ?? false) === true
                                : ($yencIntegrity['part'] ?? null) === null)
                            && (($metadata['yenc_nzb_total_parts_matches'] ?? null) !== false);
                        $crcValid = is_array($yencIntegrity)
                            && (($yencIntegrity['multipart'] ?? false) === true
                                ? ($yencIntegrity['pcrc32'] ?? null) !== null
                                    && ($yencIntegrity['declared_crc32_is_hex'] ?? false) === true
                                    && ($yencIntegrity['declared_crc32_matches'] ?? false) === true
                                : (($yencIntegrity['crc32'] ?? null) === null
                                    || (($yencIntegrity['declared_crc32_is_hex'] ?? false) === true
                                        && ($yencIntegrity['declared_crc32_matches'] ?? false) === true)));
                        if ($decoded[1] !== null && $sizeValid && $crcValid) {
                            $decodedSize = strlen($decoded[0]);
                            if ($decodedPar2Bytes + $decodedSize <= self::MAX_PAR2_SET_BYTES) {
                                $decodedPar2[$sample['file_index']][$sample['segment_number']] = $decoded[0];
                                $decodedPar2Bytes += $decodedSize;
                            } else {
                                $par2BudgetExceeded = true;
                                $errors[] = [
                                    'file_index' => $sample['file_index'],
                                    'message_id' => $sample['messageid'],
                                    'server' => $server['name'],
                                    'error_type' => 'Par2MetadataMemoryBudgetExceeded',
                                ];
                            }
                        } else {
                            $errors[] = [
                                'file_index' => $sample['file_index'],
                                'message_id' => $sample['messageid'],
                                'server' => $server['name'],
                                'error_type' => 'Par2YencIntegrityCheckFailed',
                            ];
                        }
                    }
                } catch (Throwable $exception) {
                    $errors[] = [
                        'file_index' => $sample['file_index'],
                        'message_id' => $sample['messageid'],
                        'server' => $server['name'],
                        'error_type' => class_basename($exception),
                    ];
                }
            }
        }

        $fileRows = [];
        $yencSizes = ['rar' => 0, 'par2' => 0, 'other' => 0];
        $yencSizeCounts = ['rar' => 0, 'par2' => 0, 'other' => 0];
        $yencSizeConflictCounts = ['rar' => 0, 'par2' => 0, 'other' => 0];
        foreach ($manifest['files'] as $file) {
            $primary = $this->primaryObservation((int) $file['index'], $observations);
            $fileObservations = array_values(array_filter($observations, fn (array $observation): bool => $observation['file_index'] === (int) $file['index'] && $observation['server_role'] === 'primary'));
            $yenc = $primary['metadata']['yenc'] ?? null;
            $yencSamples = array_values(array_filter(array_map(
                static fn (array $observation): mixed => $observation['metadata']['yenc'] ?? null,
                $fileObservations,
            ), 'is_array'));
            $yencConsistency = [];
            foreach (['name', 'file_size', 'line_length', 'total_parts', 'crc32'] as $field) {
                $fieldValues = array_column($yencSamples, $field);
                $uniqueFieldValues = array_values(array_unique($fieldValues, SORT_REGULAR));
                $yencConsistency[$field] = [
                    'consistent' => $fieldValues !== [] && count($uniqueFieldValues) === 1,
                    'observed_values' => $uniqueFieldValues,
                    'sample_count' => count($fieldValues),
                ];
            }
            $ranges = [];
            foreach ($fileObservations as $observation) {
                $sampleYenc = $observation['metadata']['yenc'] ?? null;
                if (is_array($sampleYenc) && is_int($sampleYenc['begin'] ?? null) && is_int($sampleYenc['end'] ?? null)) {
                    $ranges[] = [
                        'nzb_segment_number' => $observation['segment_number'],
                        'yenc_part_number' => $sampleYenc['part'],
                        'begin' => $sampleYenc['begin'],
                        'end' => $sampleYenc['end'],
                        'range_valid' => $sampleYenc['ypart_range_valid'] ?? null,
                    ];
                }
            }
            usort($ranges, static fn (array $left, array $right): int => $left['begin'] <=> $right['begin']);
            $allSegmentsSampled = count($fileObservations) === count($file['segments']) && count($file['segments']) > 0;
            $rangesContiguous = $ranges !== []
                && $ranges[0]['begin'] === 1
                && count(array_filter($ranges, static fn (array $range): bool => $range['range_valid'] !== true)) === 0;
            for ($rangeIndex = 1; $rangeIndex < count($ranges); $rangeIndex++) {
                $rangesContiguous = $rangesContiguous && $ranges[$rangeIndex]['begin'] === $ranges[$rangeIndex - 1]['end'] + 1;
            }
            $declaredSizes = $yencConsistency['file_size']['observed_values'];
            $declaredFileSize = count($declaredSizes) === 1 && is_int($declaredSizes[0]) ? $declaredSizes[0] : null;
            $singlePartCoversFile = $allSegmentsSampled
                && count($file['segments']) === 1
                && count($yencSamples) === 1
                && $declaredFileSize !== null
                && ($yencSamples[0]['part'] ?? null) === null
                && ($yencSamples[0]['decoded_segment_size'] ?? null) === $declaredFileSize;
            $allMultipartNumbersMatch = count(array_filter(
                $fileObservations,
                static fn (array $observation): bool => ($observation['metadata']['yenc_nzb_part_number_matches'] ?? false) === true
                    && ($observation['metadata']['yenc_nzb_total_parts_matches'] ?? null) !== false,
            )) === count($fileObservations);
            $rangesCoverFile = ! $allSegmentsSampled ? null : ($singlePartCoversFile || ($allMultipartNumbersMatch && $rangesContiguous
                && $declaredFileSize !== null
                && $ranges[count($ranges) - 1]['end'] === $declaredFileSize));
            if (($yencConsistency['file_size']['sample_count'] ?? 0) > 0 && ! $yencConsistency['file_size']['consistent']) {
                $yencSizeConflictCounts[$file['kind']]++;
            } elseif (($yencConsistency['file_size']['consistent'] ?? false)
                && is_array($yenc) && is_int($yenc['file_size'] ?? null)) {
                $yencSizes[$file['kind']] += $yenc['file_size'];
                $yencSizeCounts[$file['kind']]++;
            }
            $fileRows[] = [
                'index' => $file['index'],
                'kind' => $file['kind'],
                'format_hint' => $file['format_hint'],
                'subject' => $file['subject'],
                'poster' => $file['poster'],
                'date' => $file['date'],
                'groups' => $file['groups'],
                'segment_count' => $file['segment_count'],
                'nzb_advertised_bytes' => $file['advertised_bytes'],
                'segments' => array_map(static fn (array $segment): array => [
                    'number' => $segment['number'],
                    'message_id' => $segment['messageid'],
                    'advertised_bytes' => $segment['bytes'],
                ], $file['segments']),
                'sampled_article_count' => count($fileObservations),
                'sampled_segment_number' => $primary['segment_number'] ?? null,
                'yenc' => $yenc,
                'yenc_consistency' => [
                    'sample_count' => count($yencSamples),
                    'fields' => $yencConsistency,
                    'sampled_ranges' => $ranges,
                    'sampled_ranges_contiguous' => $rangesContiguous,
                    'all_nzb_segments_sampled' => $allSegmentsSampled,
                    'ranges_cover_declared_file' => $rangesCoverFile,
                ],
                'yenc_samples' => array_map(static fn (array $observation): array => [
                    'segment_number' => $observation['segment_number'],
                    'role' => $observation['sample_role'],
                    'metadata' => $observation['metadata']['yenc'] ?? null,
                    'part_number_matches_nzb' => $observation['metadata']['yenc_nzb_part_number_matches'] ?? null,
                    'total_parts_matches_nzb' => $observation['metadata']['yenc_nzb_total_parts_matches'] ?? null,
                ], $fileObservations),
                'nzb_segment_integrity' => [
                    'source_segment_record_count' => $file['source_segment_record_count'],
                    'invalid_segment_record_count' => $file['invalid_segment_record_count'],
                    'invalid_segment_metadata_count' => $file['invalid_segment_metadata_count'],
                    'duplicate_message_id_count' => $file['duplicate_message_id_count'],
                    'duplicate_segment_number_count' => $file['duplicate_segment_number_count'],
                    'segment_number_sequence_valid' => $file['segment_number_sequence_valid'],
                ],
            ];
        }

        $assembledPar2 = [];
        $par2Complete = true;
        foreach ($manifest['files'] as $file) {
            if ($file['kind'] !== 'par2') {
                continue;
            }
            $parts = $decodedPar2[$file['index']] ?? [];
            ksort($parts);
            $segmentNumbers = array_column($file['segments'], 'number');
            $expectedNumbers = $segmentNumbers === [] ? [] : range(1, count($segmentNumbers));
            $complete = $segmentNumbers !== []
                && $segmentNumbers === $expectedNumbers
                && count($parts) === count($file['segments'])
                && ! $par2BudgetExceeded;
            $bytes = array_sum(array_map('strlen', $parts));
            if (! $complete || $bytes > self::MAX_PAR2_SET_BYTES) {
                $par2Complete = false;
                $assembledPar2[] = [
                    'file_index' => $file['index'],
                    'subject' => $file['subject'],
                    'all_segments_retrieved' => $complete,
                    'packet_checksums_valid' => false,
                    'packet_set_consistent' => false,
                    'main_source_membership_valid' => false,
                    'whole_file_crc32_expected_values' => [],
                    'whole_file_crc32_observed' => null,
                    'whole_file_crc32_matches' => null,
                    'metadata_valid' => false,
                    'decoded_bytes' => $bytes,
                    'metadata' => null,
                ];
                continue;
            }
            $assembled = implode('', $parts);
            $metadata = $this->par2->parse($assembled);
            $fileObservations = array_values(array_filter($observations, static fn (array $observation): bool =>
                $observation['file_index'] === (int) $file['index'] && $observation['server_role'] === 'primary'));
            $yencNames = array_values(array_unique(array_filter(array_map(
                static fn (array $observation): ?string => $observation['metadata']['yenc']['name'] ?? null,
                $fileObservations,
            ), 'is_string')));
            $par2YencSizes = array_values(array_unique(array_filter(array_map(
                static fn (array $observation): ?int => $observation['metadata']['yenc']['file_size'] ?? null,
                $fileObservations,
            ), 'is_int')));
            $hasMultipartYenc = count(array_filter($fileObservations, static fn (array $observation): bool =>
                ($observation['metadata']['yenc']['multipart'] ?? false) === true)) > 0;
            $yencStructureValid = count($fileObservations) === count($file['segments'])
                && count($yencNames) === 1 && count($par2YencSizes) === 1
                && ($hasMultipartYenc || count($fileObservations) === 1)
                && count(array_filter($fileObservations, static fn (array $observation): bool =>
                    ((($observation['metadata']['yenc']['multipart'] ?? false) === true)
                        ? ($observation['metadata']['yenc_nzb_part_number_matches'] ?? false) === true
                        : ($observation['metadata']['yenc']['part'] ?? null) === null)
                    && ($observation['metadata']['yenc_nzb_total_parts_matches'] ?? null) !== false
                    && ($observation['metadata']['yenc']['decoded_size_matches_header_size'] ?? false) === true
                    && ($observation['metadata']['yenc']['decoded_size_matches_yend_size'] ?? false) === true
                    && (($observation['metadata']['yenc']['multipart'] ?? false) === true
                        ? ($observation['metadata']['yenc']['ypart_range_valid'] ?? false) === true
                            && ($observation['metadata']['yenc']['pcrc32'] ?? null) !== null
                            && ($observation['metadata']['yenc']['declared_crc32_matches'] ?? false) === true
                            && isset($observation['metadata']['yenc']['trailer_fields']['part'])
                            && (int) $observation['metadata']['yenc']['trailer_fields']['part'] === ($observation['metadata']['yenc']['part'] ?? null)
                        : true),
                )) === count($fileObservations);
            if ($yencStructureValid && $hasMultipartYenc) {
                $ranges = array_map(static fn (array $observation): array => [
                    (int) ($observation['metadata']['yenc']['begin'] ?? 0),
                    (int) ($observation['metadata']['yenc']['end'] ?? 0),
                ], $fileObservations);
                usort($ranges, static fn (array $left, array $right): int => $left[0] <=> $right[0]);
                $nextByte = 1;
                foreach ($ranges as [$begin, $end]) {
                    if ($begin !== $nextByte || $end < $begin) {
                        $yencStructureValid = false;
                        break;
                    }
                    $nextByte = $end + 1;
                }
                $yencStructureValid = $yencStructureValid && $nextByte - 1 === $par2YencSizes[0];
            }
            $wholeFileCrcValues = [];
            foreach ($observations as $observation) {
                if ($observation['file_index'] === (int) $file['index']
                    && $observation['server_role'] === 'primary'
                    && is_string($observation['metadata']['yenc']['crc32'] ?? null)) {
                    $wholeFileCrcValues[] = strtoupper($observation['metadata']['yenc']['crc32']);
                }
            }
            $wholeFileCrcValues = array_values(array_unique($wholeFileCrcValues));
            $observedWholeFileCrc = strtoupper(hash('crc32b', $assembled));
            $wholeFileCrcMatches = $wholeFileCrcValues === [] ? null
                : (count($wholeFileCrcValues) === 1
                    && preg_match('/\A[0-9A-F]{1,8}\z/', $wholeFileCrcValues[0]) === 1
                    && str_pad($wholeFileCrcValues[0], 8, '0', STR_PAD_LEFT) === $observedWholeFileCrc);
            $packets = $metadata['packet_summaries'] ?? [];
            $packetChecksumsValid = $packets !== []
                && (int) ($metadata['invalid_packet_count'] ?? 0) === 0
                && count(array_filter($packets, static fn (array $packet): bool => ($packet['checksum_valid'] ?? false) !== true)) === 0;
            $validSetIds = array_values(array_unique(array_column(array_filter(
                $packets,
                static fn (array $packet): bool => ($packet['checksum_valid'] ?? false) === true,
            ), 'set_id')));
            $packetSetConsistent = count($validSetIds) === 1;
            $mainPacket = $metadata['main_packet'] ?? null;
            $mainSourceMembershipValid = is_array($mainPacket)
                ? (int) ($mainPacket['recoverable_file_count'] ?? -1) === count($mainPacket['recoverable_file_ids'] ?? [])
                : null;
            $metadataValid = $packetChecksumsValid
                && ! (bool) ($metadata['packet_limit_reached'] ?? false)
                && (int) ($metadata['trailing_unparsed_bytes'] ?? 0) === 0
                && (int) ($metadata['unparsed_bytes'] ?? 0) === 0
                && is_string($metadata['creator'] ?? null)
                && trim($metadata['creator']) !== ''
                && $yencStructureValid
                && $packetSetConsistent
                && $wholeFileCrcMatches !== false
                && (int) ($metadata['packet_count'] ?? 0) > 0;
            if (! $metadataValid) {
                $par2Complete = false;
            }
            $assembledPar2[] = [
                'file_index' => $file['index'],
                'subject' => $file['subject'],
                'all_segments_retrieved' => true,
                'yenc_structure_valid' => $yencStructureValid,
                'packet_checksums_valid' => $packetChecksumsValid,
                'packet_set_consistent' => $packetSetConsistent,
                'main_source_membership_valid' => $mainSourceMembershipValid,
                'whole_file_crc32_expected_values' => $wholeFileCrcValues,
                'whole_file_crc32_observed' => $observedWholeFileCrc,
                'whole_file_crc32_matches' => $wholeFileCrcMatches,
                'metadata_valid' => $metadataValid,
                'decoded_bytes' => strlen($assembled),
                'metadata' => $metadata,
            ];
        }

        $par2Sources = [];
        $par2RecoveryExponentsBySet = [];
        $par2SetIds = [];
        $par2SetMainPackets = [];
        $par2SetSources = [];
        $par2SetBlockSizes = [];
        $par2RecoverySlicePayloadHashesBySet = [];
        $par2RecoverySliceConflicts = [];
        $par2PackedLayout = false;
        $par2FileCount = count(array_filter($manifest['files'], static fn (array $file): bool => $file['kind'] === 'par2'));
        foreach ($assembledPar2 as $par2File) {
            if (($par2File['metadata_valid'] ?? false) !== true || ! is_array($par2File['metadata'] ?? null)) {
                continue;
            }
            $metadata = $par2File['metadata'];
            $fileSetIds = array_values(array_unique(array_column(array_filter(
                $metadata['packet_summaries'] ?? [],
                static fn (array $packet): bool => ($packet['checksum_valid'] ?? false) === true,
            ), 'set_id')));
            if (count($fileSetIds) !== 1) {
                $par2Complete = false;
                continue;
            }
            $setId = $fileSetIds[0];
            $par2SetIds[$setId] = true;
            if (is_array($metadata['main_packet'] ?? null)) {
                $main = $metadata['main_packet'];
                if (isset($par2SetMainPackets[$setId]) && $par2SetMainPackets[$setId] !== $main) {
                    $par2Complete = false;
                } else {
                    $par2SetMainPackets[$setId] = $main;
                }
            }
            if (is_int($metadata['source_block_size'] ?? null)) {
                $candidateBlockSize = $metadata['source_block_size'];
                if (isset($par2SetBlockSizes[$setId]) && $par2SetBlockSizes[$setId] !== $candidateBlockSize) {
                    $par2Complete = false;
                } else {
                    $par2SetBlockSizes[$setId] = $candidateBlockSize;
                }
            }
            foreach ($metadata['source_files'] ?? [] as $sourceFile) {
                $fileId = (string) ($sourceFile['file_id'] ?? '');
                if ($fileId === '') {
                    continue;
                }
                if (isset($par2SetSources[$setId][$fileId]) && $par2SetSources[$setId][$fileId] !== $sourceFile) {
                    $par2Complete = false;
                } else {
                    $par2SetSources[$setId][$fileId] = $sourceFile;
                }
            }
            foreach ($par2File['metadata']['packet_summaries'] ?? [] as $packet) {
                if (($packet['checksum_valid'] ?? false) === true && is_string($packet['set_id'] ?? null)) {
                    $par2SetIds[$packet['set_id']] = true;
                }
                if (($packet['checksum_valid'] ?? false) !== true
                    || ($packet['packet_type'] ?? null) !== 'PAR 2.0\\0RecvSlic'
                    || ! is_string($packet['set_id'] ?? null)
                    || ! is_int($packet['recovery_exponent'] ?? null)
                    || ! is_string($packet['recovery_data_sha256'] ?? null)) {
                    continue;
                }
                $setId = $packet['set_id'];
                $exponent = $packet['recovery_exponent'] === 65535 ? 0 : $packet['recovery_exponent'];
                if (isset($par2RecoverySlicePayloadHashesBySet[$setId][$exponent])
                    && $par2RecoverySlicePayloadHashesBySet[$setId][$exponent] !== $packet['recovery_data_sha256']) {
                    $par2RecoverySliceConflicts[$setId.':'.$exponent] = [
                        'recovery_set_id' => $setId,
                        'exponent' => $exponent,
                    ];
                    $par2Complete = false;
                } else {
                    $par2RecoverySlicePayloadHashesBySet[$setId][$exponent] = $packet['recovery_data_sha256'];
                }
            }
            foreach ($par2File['metadata']['recovery_exponents'] ?? [] as $exponent) {
                $par2RecoveryExponentsBySet[$setId][$exponent] = true;
            }
            $par2PackedLayout = $par2PackedLayout || (bool) ($par2File['metadata']['packed_layout_present'] ?? false);
        }
        foreach (array_keys($par2SetIds) as $setId) {
            $main = $par2SetMainPackets[$setId] ?? null;
            $sourceFiles = $par2SetSources[$setId] ?? [];
            if (! is_array($main)) {
                $par2Complete = false;
                continue;
            }
            $mainFileIds = [...($main['recoverable_file_ids'] ?? []), ...($main['non_recoverable_file_ids'] ?? [])];
            $describedFileIds = array_keys($sourceFiles);
            sort($mainFileIds);
            sort($describedFileIds);
            if ($mainFileIds !== $describedFileIds) {
                $par2Complete = false;
            }
            $blockSize = (int) ($par2SetBlockSizes[$setId] ?? 0);
            foreach ($assembledPar2 as $par2File) {
                foreach ($par2File['metadata']['packet_summaries'] ?? [] as $packet) {
                    if (($packet['set_id'] ?? null) === $setId
                        && ($packet['packet_type'] ?? null) === 'PAR 2.0\\0RecvSlic'
                        && ($blockSize < 1 || (int) ($packet['payload_bytes'] ?? 0) !== $blockSize + 4)) {
                        $par2Complete = false;
                    }
                }
            }
            foreach ($sourceFiles as $fileId => $sourceFile) {
                $par2Sources[$setId.':'.$fileId] = $sourceFile;
            }
        }
        $multiplePar2Sets = count($par2SetIds) > 1;
        $par2RecoveryExponents = $multiplePar2Sets ? [] : ($par2RecoveryExponentsBySet[array_key_first($par2SetIds)] ?? []);
        $recoveryCount = count($par2RecoveryExponents);
        $parsedRecoveryAcrossSets = array_sum(array_map('count', $par2RecoveryExponentsBySet));
        $par2SourceBlocks = 0;
        if (! $multiplePar2Sets && $par2SetIds !== []) {
            $singleSetId = array_key_first($par2SetIds);
            $main = $par2SetMainPackets[$singleSetId] ?? [];
            $recoverableIds = $main['recoverable_file_ids'] ?? [];
            $blockSize = (int) ($par2SetBlockSizes[$singleSetId] ?? 0);
            if ($blockSize > 0) {
                foreach ($recoverableIds as $fileId) {
                    $fileSize = (int) ($par2SetSources[$singleSetId][$fileId]['file_length'] ?? 0);
                    if ($fileSize > 0) {
                        $par2SourceBlocks += (int) ceil($fileSize / $blockSize);
                    }
                }
            }
        }
        $par2SourceFiles = array_values($par2Sources);
        $par2SourceRarFiles = array_values(array_filter($par2SourceFiles, static fn (array $sourceFile): bool => preg_match(
            '/(?:\.part\d+\.rar|\.rar|\.r\d{2,})\z/i',
            (string) ($sourceFile['filename'] ?? ''),
        ) === 1));
        $par2RecoveryPercent = $par2Complete && $par2RecoverySliceConflicts === [] && ! $multiplePar2Sets && $par2SourceBlocks > 0 && ! $par2PackedLayout
            ? round($recoveryCount / $par2SourceBlocks * 100, 3)
            : null;
        $yencFileLengthPercent = $yencSizeCounts['rar'] > 0 && $yencSizeCounts['par2'] > 0 && $yencSizes['rar'] > 0
            ? round($yencSizes['par2'] / $yencSizes['rar'] * 100, 3)
            : null;

        $comparisons = $this->compareProviders($observations);
        $timing = $this->estimatePostingTimeline(
            $manifest['files'],
            $observations,
            $servers,
            count(array_unique(array_column($samples, 'messageid'))),
        );
        $completePrimarySamples = count(array_filter($samples, fn (array $sample): bool => $this->primaryObservation($sample['file_index'], $observations, $sample['messageid']) !== null));
        $allFilesHaveSegments = count(array_filter(
            $manifest['files'],
            static fn (array $file): bool => ($file['segments'] ?? []) !== [],
        )) === count($manifest['files']);
        $manifestIntegrityValid = count(array_filter(
            $manifest['files'],
            static fn (array $file): bool => ($file['segment_number_sequence_valid'] ?? false) === true
                && ($file['invalid_segment_record_count'] ?? 0) === 0
                && ($file['invalid_segment_metadata_count'] ?? 0) === 0
                && ($file['duplicate_message_id_count'] ?? 0) === 0
                && ($file['duplicate_segment_number_count'] ?? 0) === 0,
        )) === count($manifest['files']);
        $allSamplesAvailable = $samples !== []
            && $completePrimarySamples === count($samples)
            && $allFilesHaveSegments
            && $manifestIntegrityValid;
        $providerCoverageComplete = count($servers) < 2 || count($comparisons) >= count(array_unique(array_column($samples, 'messageid')));
        $profileCoverageComplete = count($serverProfiles) === count($servers)
            && count(array_filter($serverProfiles, static fn (array $profile): bool => ! isset($profile['profile_error_type']))) === count($servers);
        $status = $allSamplesAvailable && $par2Complete && $providerCoverageComplete && $profileCoverageComplete ? 'complete' : 'partial';

        return [
            'status' => $status,
            'data' => [
                'schema_version' => 1,
                'collection_policy' => [
                    'all_nzb_files_listed' => true,
                    'non_par2_article_samples_per_file' => 'first, middle, and last segment; repeated Message-IDs are deduplicated within each NZB file',
                    'par2_segments_requested' => 'all',
                    'article_body_limit_bytes' => self::MAX_ARTICLE_BODY_BYTES,
                    'providers' => array_column($servers, 'name'),
                    'queried_server_endpoints' => $servers,
                    'server_profiles' => $serverProfiles,
                    'coverage_note' => 'First, middle, and last RAR/other article bodies are sampled per NZB file; all PAR2 segments are requested. Provider-specific raw headers are retained for every collected article.',
                ],
                'nzb' => [
                    'file_count' => $manifest['file_count'],
                    'meta' => $manifest['nzb_meta_values'],
                    'totals' => $manifest['totals'],
                    'par2_to_rar_advertised_percent' => $manifest['par2_to_rar_advertised_percent'],
                    'par2_to_rar_percent_basis' => $manifest['par2_to_rar_percent_basis'],
                ],
                'files' => $fileRows,
                'article_observations' => $observations,
                'provider_comparisons' => $comparisons,
                'posting_timeline_estimate' => $timing,
                'par2' => [
                    'present' => $par2FileCount > 0,
                    'files' => $assembledPar2,
                    'set_ids' => array_keys($par2SetIds),
                    'multiple_recovery_sets' => $multiplePar2Sets,
                    'source_files' => $par2SourceFiles,
                    'source_file_total_bytes' => array_sum(array_column($par2SourceFiles, 'file_length')),
                    'source_rar_files' => $par2SourceRarFiles,
                    'source_rar_total_bytes' => array_sum(array_column($par2SourceRarFiles, 'file_length')),
                    'unique_recovery_slices' => $multiplePar2Sets ? null : $recoveryCount,
                    'parsed_recovery_exponents_across_sets' => $multiplePar2Sets ? $parsedRecoveryAcrossSets : null,
                    'recovery_slice_conflicts' => array_values($par2RecoverySliceConflicts),
                    'source_block_count' => $multiplePar2Sets ? null : ($par2SourceBlocks ?: null),
                    'recovery_percent' => $par2RecoveryPercent,
                    'packed_layout_present' => $par2PackedLayout,
                    'complete' => $par2FileCount > 0 ? $par2Complete : null,
                    'warning' => $par2FileCount === 0
                        ? 'The NZB lists no PAR2 files.'
                        : ($multiplePar2Sets
                            ? 'Multiple PAR2 recovery sets were found; a single aggregate recovery percentage is unavailable.'
                            : ($par2Complete ? null : 'PAR2 packets are incomplete; recovery percentage is unavailable.')),
                ],
                'yenc_file_lengths' => [
                    'rar_bytes' => $yencSizeCounts['rar'] > 0 ? $yencSizes['rar'] : null,
                    'par2_bytes' => $yencSizeCounts['par2'] > 0 ? $yencSizes['par2'] : null,
                    'par2_to_rar_percent' => $yencFileLengthPercent,
                    'excluded_file_size_conflict_counts' => $yencSizeConflictCounts,
                    'basis' => 'One complete-file size per NZB file, included only when the sampled yEnc file_size values agree; totals are partial when files have no yEnc sample or conflicting sizes.',
                ],
                'uploader_software_candidates' => $this->softwareCandidates($observations),
                'errors' => $errors,
                'not_collected' => [
                    'Encrypted RAR header contents and payload filenames cannot be read without a password. File signature detection uses only decoded sampled prefixes.',
                    'ExifTool and TrID are not invoked; current format identification uses bounded Rust headers plus the local PHP fileinfo magic database when available.',
                    'NFO/SFV/SRR sidecars can be listed as NZB files but are not separately decoded as text or release records; local downloader history is outside the NZB.',
                    'Candidate posting software is a fingerprint clue, not confirmed uploader identity.',
                ],
            ],
        ];
    }

    /** @param list<array<string, mixed>> $observations */
    private function primaryObservation(int $fileIndex, array $observations, ?string $messageId = null): ?array
    {
        $fallback = null;
        foreach ($observations as $observation) {
            if ($observation['file_index'] !== $fileIndex || $observation['server_role'] !== 'primary') {
                continue;
            }
            if ($messageId !== null) {
                if ($observation['message_id'] === $messageId) {
                    return $observation;
                }

                continue;
            }
            $fallback ??= $observation;
            if (is_array($observation['metadata']['yenc'] ?? null)) {
                return $observation;
            }
        }

        return $fallback;
    }

    /** @return list<array{name: string, alternate: bool, port: string, tls: bool, provider_label: string, provider_label_basis: string}> */
    private function configuredServers(): array
    {
        $primaryHost = (string) config('nntmux_nntp.server', 'primary');
        [$primaryProvider, $primaryLabelBasis] = self::providerLabel($primaryHost);
        $servers = [[
            'name' => $primaryHost,
            'alternate' => false,
            'port' => (string) config('nntmux_nntp.port', ''),
            'tls' => (bool) config('nntmux_nntp.ssl', false),
            'provider_label' => $primaryProvider,
            'provider_label_basis' => $primaryLabelBasis,
        ]];
        if (trim((string) config('nntmux_nntp.alternate_server', '')) !== '') {
            $alternateHost = (string) config('nntmux_nntp.alternate_server');
            [$alternateProvider, $alternateLabelBasis] = self::providerLabel($alternateHost);
            $servers[] = [
                'name' => $alternateHost,
                'alternate' => true,
                'port' => (string) config('nntmux_nntp.alternate_server_port', ''),
                'tls' => (bool) config('nntmux_nntp.alternate_server_ssl', false),
                'provider_label' => $alternateProvider,
                'provider_label_basis' => $alternateLabelBasis,
            ];
        }

        return $servers;
    }

    /** @return array{string, string} */
    private static function providerLabel(string $hostname): array
    {
        $host = strtolower($hostname);
        foreach ([
            'eweka' => 'Eweka',
            'newshosting' => 'Newshosting',
            'newsgroupdirect' => 'NewsgroupDirect',
            'frugalusenet' => 'Frugal Usenet',
            'easynews' => 'Easynews',
            'astraweb' => 'Astraweb',
            'usenetserver' => 'UsenetServer',
            'hitnews' => 'Hitnews',
        ] as $needle => $label) {
            if (str_contains($host, $needle)) {
                return [$label, 'inferred from configured hostname'];
            }
        }

        return [$hostname, 'configured hostname'];
    }

    /** @param list<array<string, mixed>> $files
     *  @return array<string, mixed>
     */
    private function estimatePostingTimeline(array $files, array $observations, array $servers, int $expectedSamples): array
    {
        $dates = [];
        $bytesByMinute = [];
        foreach ($files as $file) {
            $timestamp = is_int($file['date'] ?? null) ? $file['date'] : null;
            if ($timestamp === null || $timestamp < 1) {
                continue;
            }
            $dates[] = $timestamp;
            $minute = intdiv($timestamp, 60) * 60;
            $bytesByMinute[$minute] = ($bytesByMinute[$minute] ?? 0) + (int) ($file['advertised_bytes'] ?? 0);
        }
        if ($dates === []) {
            $nzbTimestampSpan = [
                'available' => false,
                'reason' => 'NZB file-level timestamps are absent.',
                'measured_uploader_bandwidth' => false,
            ];
        } else {
            sort($dates);
            ksort($bytesByMinute);
            $start = $dates[0];
            $end = $dates[count($dates) - 1];
            $duration = max(0, $end - $start);
            $datedFiles = array_values(array_filter($files, static fn (array $file): bool => is_int($file['date'] ?? null) && $file['date'] > 0));
            $advertisedBytes = array_sum(array_map(static fn (array $file): int => (int) ($file['advertised_bytes'] ?? 0), $datedFiles));
            $minuteRates = array_map(static fn (int $bytes): float => round($bytes / 60, 3), array_values($bytesByMinute));
            $nzbTimestampSpan = [
                'available' => true,
                'source' => 'NZB file-level date attributes and advertised segment bytes',
                'start_utc' => gmdate(DATE_ATOM, $start),
                'end_utc' => gmdate(DATE_ATOM, $end),
                'timestamp_span_seconds' => $duration,
                'advertised_bytes' => $advertisedBytes,
                'dated_file_count' => count($datedFiles),
                'undated_file_count_excluded' => count($files) - count($datedFiles),
                'whole_span_average_advertised_bytes_per_second' => $duration > 0 ? round($advertisedBytes / $duration, 3) : null,
                'observed_nonempty_minute_bucket_rates_bytes_per_second' => [
                    'minimum_nonempty_bucket' => $minuteRates === [] ? null : min($minuteRates),
                    'maximum_nonempty_bucket' => $minuteRates === [] ? null : max($minuteRates),
                    'bucket_count' => count($minuteRates),
                    'bucket_seconds' => 60,
                    'empty_buckets_included' => false,
                    'basis' => 'NZB advertised bytes grouped by file-level timestamps; bucket rates are not measured transfer rates.',
                ],
                'measured_uploader_bandwidth' => false,
                'interpretation' => 'Coarse NZB timestamp-based posting-volume estimates, not measured network transfer rates. File-level timestamps can be overridden or normalized; each timestamp may describe one segment rather than completion of every segment. Empty minutes are omitted.',
            ];
        }
        $articleSpans = [];
        $injectionSpans = [];
        $deltas = [];
        foreach ($servers as $server) {
            $serverObservations = array_values(array_filter($observations, static fn (array $observation): bool => $observation['server'] === $server['name'] && $observation['server_role'] === ($server['alternate'] ? 'alternate' : 'primary')));
            $articleTimes = [];
            $injectionTimes = [];
            foreach ($serverObservations as $observation) {
                $headers = $observation['metadata']['headers'] ?? [];
                $date = is_string($headers['date'] ?? null) ? strtotime($headers['date']) : false;
                $injectionDate = is_string($headers['injection-date'] ?? null) ? strtotime($headers['injection-date']) : false;
                if ($date !== false) {
                    $articleTimes[$observation['message_id']] = $date;
                }
                if ($injectionDate !== false) {
                    $injectionTimes[$observation['message_id']] = $injectionDate;
                }
                if ($date !== false && $injectionDate !== false) {
                    $deltas[] = ['server' => $server['name'], 'message_id' => $observation['message_id'], 'seconds' => $injectionDate - $date];
                }
            }
            $serverKey = ($server['alternate'] ? 'alternate ' : 'primary ').$server['name'];
            $uniqueServerSamples = count(array_unique(array_column($serverObservations, 'message_id')));
            $articleSpans[$serverKey] = self::timestampSummary($articleTimes, $uniqueServerSamples, $expectedSamples);
            $injectionSpans[$serverKey] = self::timestampSummary($injectionTimes, $uniqueServerSamples, $expectedSamples);
        }
        $deltaValues = array_column($deltas, 'seconds');
        sort($deltaValues);
        $observationCounts = [];
        foreach ($servers as $server) {
            $serverKey = ($server['alternate'] ? 'alternate ' : 'primary ').$server['name'];
            $observationCounts[$serverKey] = count(array_filter($observations, static fn (array $observation): bool => $observation['server'] === $server['name'] && $observation['server_role'] === ($server['alternate'] ? 'alternate' : 'primary')));
        }

        return [
            'nzb_file_timestamp_span' => $nzbTimestampSpan,
            'observed_article_date_span_by_server' => $articleSpans,
            'observed_injection_date_span_by_server' => $injectionSpans,
            'composition_to_injection_delta_seconds' => [
                'min' => $deltaValues === [] ? null : min($deltaValues),
                'median' => self::percentile($deltaValues, 0.5),
                'p95' => self::percentile($deltaValues, 0.95),
                'max' => $deltaValues === [] ? null : max($deltaValues),
                'negative_count' => count(array_filter($deltaValues, static fn (int $value): bool => $value < 0)),
                'paired_samples' => count($deltaValues),
                'source' => 'NNTP Date and Injection-Date headers; these timestamps may use different clocks.',
            ],
            'posting_rate_estimates' => [
                'available' => false,
                'unavailable_reason' => 'Collected article timestamps cover sampled articles only; this cannot establish release-wide peak, minimum, or actual uploader bandwidth.',
                'actual_uploader_bandwidth_measured' => false,
            ],
            'timestamp_coverage' => [
                'nzb_segment_reference_count' => array_sum(array_map(static fn (array $file): int => count($file['segments']), $files)),
                'expected_unique_article_ids_in_nzb' => count(array_unique(array_merge(...array_map(
                    static fn (array $file): array => array_column($file['segments'], 'messageid'),
                    $files,
                )))),
                'queried_sampled_article_ids' => count(array_unique(array_column($observations, 'message_id'))),
                'expected_samples_per_server' => $expectedSamples,
                'actual_observations_per_server' => $observationCounts,
                'coverage_complete' => false,
            ],
            'interpretation' => 'Date is the article composition time; Injection-Date is an observed injection timestamp. Both can be overridden or normalized, and sampled spans do not equal the full upload duration. Actual uploader bandwidth requires uploader-side telemetry.',
        ];
    }

    /** @param array<string, int> $timestamps
     *  @return array<string, mixed>
     */
    private static function timestampSummary(array $timestamps, int $observations, int $expected): array
    {
        if ($timestamps === []) {
            return ['available' => false, 'valid_timestamp_count' => 0, 'observations' => $observations, 'expected_samples' => $expected];
        }
        $values = array_values($timestamps);
        sort($values);

        return [
            'available' => true,
            'first_utc' => gmdate(DATE_ATOM, $values[0]),
            'last_utc' => gmdate(DATE_ATOM, $values[count($values) - 1]),
            'seconds' => max(0, $values[count($values) - 1] - $values[0]),
            'valid_timestamp_count' => count($values),
            'observations' => $observations,
            'expected_samples' => $expected,
            'complete' => $observations >= $expected && count($values) >= $expected,
        ];
    }

    /** @param list<int> $values */
    private static function percentile(array $values, float $percentile): ?int
    {
        if ($values === []) {
            return null;
        }
        $index = (int) floor((count($values) - 1) * $percentile);

        return $values[$index];
    }

    /** @param list<array<string, mixed>> $observations
     *  @return list<array<string, mixed>>
     */
    private function compareProviders(array $observations): array
    {
        $byId = [];
        foreach ($observations as $observation) {
            $serverKey = $observation['server_role'].' '.$observation['server'].':'.$observation['server_port'];
            $byId[$observation['message_id']][$serverKey] = $observation;
        }
        $comparisons = [];
        foreach ($byId as $messageId => $perServer) {
            if (count($perServer) < 2) {
                continue;
            }
            $bodyHashes = [];
            $headers = [];
            $allHeaderNames = [];
            foreach ($perServer as $server => $observation) {
                $bodyHashes[$server] = $observation['metadata']['article_body_sha256'] ?? null;
                $headers[$server] = $observation['metadata']['headers'] ?? [];
                $allHeaderNames = array_unique(array_merge($allHeaderNames, array_keys($headers[$server])));
            }
            $headerDifferences = [];
            foreach ($allHeaderNames as $headerName) {
                $values = [];
                foreach ($headers as $server => $serverHeaders) {
                    $values[$server] = $serverHeaders[$headerName] ?? null;
                }
                if (count(array_unique(array_map(static fn (mixed $value): string => serialize($value), $values))) > 1) {
                    $headerDifferences[$headerName] = $values;
                }
            }
            $comparisons[] = [
                'message_id' => $messageId,
                'body_hashes_by_server' => $bodyHashes,
                'body_matches' => count(array_unique($bodyHashes)) === 1 && ! in_array(null, $bodyHashes, true),
                'headers_by_server' => $headers,
                'header_differences' => $headerDifferences,
                'header_values_match' => $headerDifferences === [],
                'header_order_match' => count(array_unique(array_map(static function (array $value): string {
                    return hash('sha256', serialize(array_keys($value)));
                }, $headers))) === 1,
            ];
        }

        return $comparisons;
    }

    /** @param list<string> $format
     *  @return array<string, string>
     */
    private function parseOverview(?string $row, array $format): array
    {
        if ($row === null || $format === []) {
            return [];
        }
        $values = explode("\t", $row);
        $parsed = ['article_number' => array_shift($values) ?? ''];
        foreach ($format as $index => $field) {
            if (! array_key_exists($index, $values)) {
                break;
            }
            $field = trim($field);
            if (preg_match('/\A:[a-z0-9-]+\z/i', $field) === 1) {
                $key = strtolower($field);
            } elseif (preg_match('/\A(?:Bytes|Lines):\z/i', $field) === 1) {
                $key = ':'.strtolower(rtrim($field, ':'));
            } elseif (preg_match('/\A([a-z0-9-]+):full\z/i', $field, $matches) === 1) {
                $key = strtolower($matches[1]);
            } else {
                $key = strtolower(rtrim($field, ':'));
            }
            if ($key !== '') {
                $parsed[$key] = $values[$index];
            }
        }

        return $parsed;
    }

    /** @param list<array<string, mixed>> $observations
     *  @return list<array<string, mixed>>
     */
    private function softwareCandidates(array $observations): array
    {
        $candidates = [];
        foreach ($observations as $observation) {
            foreach ($observation['metadata']['uploader_software_candidates'] ?? [] as $name => $evidence) {
                $candidates[$name]['software'] = $name;
                $candidates[$name]['evidence'][] = [
                    'server' => $observation['server'],
                    'message_id' => $observation['message_id'],
                    'header' => $evidence['header'],
                    'value' => $evidence['value'],
                ];
            }
        }
        foreach ($candidates as &$candidate) {
            $candidate['confidence'] = count($candidate['evidence']) > 1 ? 'medium' : 'low';
        }
        unset($candidate);

        return array_values($candidates);
    }
}
