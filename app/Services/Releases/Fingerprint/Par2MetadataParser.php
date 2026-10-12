<?php

declare(strict_types=1);

namespace App\Services\Releases\Fingerprint;

final class Par2MetadataParser
{
    /** @return array<string, mixed> */
    public function parse(string $data): array
    {
        $native = (new NativeMetadataParser)->parsePar2($data);
        if ($native !== null) {
            return $native;
        }

        $offset = 0;
        $blockSize = null;
        $creator = null;
        $sources = [];
        $sourcesBySet = [];
        $blockSizesBySet = [];
        $mainsBySet = [];
        $ifscBySetAndFile = [];
        $main = null;
        $recoveryExponents = [];
        $recoveryExponentsBySet = [];
        $recoveryPacketLengths = [];
        $optionalPackets = [];
        $packetTypeCounts = [];
        $packetSummaries = [];
        $packetDefinitions = [];
        $packetCount = 0;
        $invalidPacketCount = 0;
        $unparsedBytes = 0;
        $length = strlen($data);
        $maximumPackets = 20_000;

        while ($offset + 64 <= $length && $packetCount < $maximumPackets) {
            $magic = substr($data, $offset, 8);
            if ($magic !== "PAR2\0PKT") {
                $next = strpos($data, "PAR2\0PKT", $offset + 1);
                if ($next === false) {
                    $unparsedBytes += $length - $offset;
                    break;
                }
                $unparsedBytes += $next - $offset;
                $offset = $next;
                continue;
            }
            $unpackedLength = unpack('Vlow/Vhigh', substr($data, $offset + 8, 8));
            $packetLength = (int) ($unpackedLength['low'] ?? 0) + ((int) ($unpackedLength['high'] ?? 0) * 4_294_967_296);
            if ($packetLength < 64 || $packetLength % 4 !== 0 || $packetLength > $length - $offset) {
                $invalidPacketCount++;
                break;
            }
            $packet = substr($data, $offset, (int) $packetLength);
            $packetCount++;
            $type = rtrim(substr($packet, 48, 16), "\0");
            $payload = substr($packet, 64);
            $checksumValid = hash_equals(substr($packet, 16, 16), md5(substr($packet, 32), true));
            $packetTypeCounts[$type] = ($packetTypeCounts[$type] ?? 0) + 1;
            $packetSummaries[] = [
                'offset' => $offset,
                'packet_bytes' => (int) $packetLength,
                'packet_type' => str_replace("\0", '\\0', $type),
                'set_id' => bin2hex(substr($packet, 32, 16)),
                'packet_md5' => bin2hex(substr($packet, 16, 16)),
                'checksum_valid' => $checksumValid,
                'payload_bytes' => strlen($payload),
                'payload_sha256' => hash('sha256', $payload),
                'payload_prefix_hex' => bin2hex(substr($payload, 0, 256)),
                'payload_prefix_truncated' => strlen($payload) > 256,
            ];
            if (! $checksumValid) {
                $invalidPacketCount++;
                $offset += (int) $packetLength;
                continue;
            }
            if ($type === "PAR 2.0\0Main" || $type === "PAR 2.0\0FileDesc") {
                $definitionKey = $type === "PAR 2.0\0Main"
                    ? bin2hex(substr($packet, 32, 16)).':main'
                    : bin2hex(substr($packet, 32, 16)).':file:'.bin2hex(substr($payload, 0, 16));
                $definitionHash = hash('sha256', $payload);
                if (isset($packetDefinitions[$definitionKey]) && $packetDefinitions[$definitionKey] !== $definitionHash) {
                    $invalidPacketCount++;
                } else {
                    $packetDefinitions[$definitionKey] = $definitionHash;
                }
            }
            if ($type === "PAR 2.0\0Main" && strlen($payload) >= 12) {
                $candidateBlockSize = self::unsigned64(substr($payload, 0, 8));
                $recoverableFileCount = (int) (unpack('V', substr($payload, 8, 4))[1] ?? 0);
                $fileIdsBytes = substr($payload, 12);
                $fileIds = strlen($fileIdsBytes) % 16 === 0 ? array_map('bin2hex', str_split($fileIdsBytes, 16)) : [];
                if ($candidateBlockSize < 1 || $candidateBlockSize % 4 !== 0 || strlen($fileIdsBytes) % 16 !== 0 || $recoverableFileCount > count($fileIds)) {
                    $invalidPacketCount++;
                    $offset += (int) $packetLength;

                    continue;
                }
                $blockSize = $candidateBlockSize;
                $setId = bin2hex(substr($packet, 32, 16));
                $blockSizesBySet[$setId] ??= $candidateBlockSize;
                $main = [
                    'recoverable_file_count' => $recoverableFileCount,
                    'recoverable_file_ids' => array_slice($fileIds, 0, $recoverableFileCount),
                    'non_recoverable_file_ids' => array_slice($fileIds, $recoverableFileCount),
                    'all_file_ids' => $fileIds,
                ];
                $mainsBySet[$setId] ??= $main;
            } elseif ($type === "PAR 2.0\0FileDesc" && strlen($payload) >= 57) {
                $setId = bin2hex(substr($packet, 32, 16));
                $fileSize = self::unsigned64(substr($payload, 48, 8));
                $name = strstr(substr($payload, 56), "\0", true);
                $source = [
                    'recovery_set_id' => $setId,
                    'filename' => $name === false ? substr($payload, 56) : $name,
                    'file_length' => $fileSize,
                    'md5' => bin2hex(substr($payload, 16, 16)),
                    'md5_first_16k' => bin2hex(substr($payload, 32, 16)),
                    'file_id' => bin2hex(substr($payload, 0, 16)),
                ];
                $sources[$source['file_id']] = $source;
                $sourcesBySet[$setId][$source['file_id']] = $source;
            } elseif ($type === "PAR 2.0\0IFSC") {
                $setId = bin2hex(substr($packet, 32, 16));
                if (strlen($payload) < 16 || (strlen($payload) - 16) % 20 !== 0) {
                    $invalidPacketCount++;
                } else {
                    $fileId = bin2hex(substr($payload, 0, 16));
                    $records = substr($payload, 16);
                    $preview = substr($records, 0, intdiv(min(strlen($records), 4096), 20) * 20);
                    $candidate = [
                        'slice_count' => intdiv(strlen($records), 20),
                        'checksum_records_sha256' => hash('sha256', $records),
                        'checksum_records_preview_hex' => bin2hex($preview),
                        'preview_slice_count' => intdiv(strlen($preview), 20),
                        'duplicate_conflict' => false,
                    ];
                    if (isset($ifscBySetAndFile[$setId][$fileId])) {
                        if ($ifscBySetAndFile[$setId][$fileId]['checksum_records_sha256'] !== $candidate['checksum_records_sha256']) {
                            $ifscBySetAndFile[$setId][$fileId]['duplicate_conflict'] = true;
                            $invalidPacketCount++;
                        }
                    } else {
                        $ifscBySetAndFile[$setId][$fileId] = $candidate;
                    }
                }
                $optionalPackets[$type][] = [
                    'payload_bytes' => strlen($payload),
                    'payload_sha256' => hash('sha256', $payload),
                    'payload_hex' => bin2hex(substr($payload, 0, 256)),
                ];
            } elseif ($type === "PAR 2.0\0Main" || $type === "PAR 2.0\0FileDesc") {
                $invalidPacketCount++;
            } elseif ($type === "PAR 2.0\0RecvSlic" && strlen($payload) >= 4) {
                $recoveryPacketLengths[] = [bin2hex(substr($packet, 32, 16)), (int) (unpack('V', substr($payload, 0, 4))[1] ?? 0), strlen($payload)];
            } elseif ($type === "PAR 2.0\0Creator") {
                $creator = trim(strtok($payload, "\0") ?: $payload);
            } elseif (in_array($type, ["PAR 2.0\0UniFileN", "PAR 2.0\0CommASCI", "PAR 2.0\0CommUni", "PAR 2.0\0FileSlic", "PAR 2.0\0RFSC", "PAR 2.0\0PkdMain", "PAR 2.0\0PkdRecvS"], true)) {
                $optionalPackets[$type][] = [
                    'payload_bytes' => strlen($payload),
                    'payload_sha256' => hash('sha256', $payload),
                    'payload_hex' => bin2hex(substr($payload, 0, 256)),
                ];
            }
            $offset += (int) $packetLength;
        }

        $recoverySetIds = array_values(array_unique(array_column(array_filter(
            $packetSummaries,
            static fn (array $packet): bool => ($packet['checksum_valid'] ?? false) === true,
        ), 'set_id')));
        $inputFileSliceChecksums = [];
        foreach ($ifscBySetAndFile as $setId => $fileChecksums) {
            foreach ($fileChecksums as $fileId => $ifsc) {
                $source = $sourcesBySet[$setId][$fileId] ?? null;
                $setBlockSize = $blockSizesBySet[$setId] ?? null;
                $expectedSliceCount = is_array($source) && is_int($setBlockSize) && $setBlockSize > 0
                    ? (int) ceil($source['file_length'] / $setBlockSize) : null;
                $inputFileSliceChecksums[] = $ifsc + [
                    'recovery_set_id' => $setId,
                    'file_id' => $fileId,
                    'preview_truncated' => $ifsc['preview_slice_count'] < $ifsc['slice_count'],
                    'source_file_present' => is_array($source),
                    'expected_slice_count_from_file_length' => $expectedSliceCount,
                    'slice_count_matches_file_length' => $expectedSliceCount === null ? null : $expectedSliceCount === $ifsc['slice_count'],
                ];
            }
        }
        foreach ($recoveryPacketLengths as [$setId, $exponent, $payloadLength]) {
            $setBlockSize = $blockSizesBySet[$setId] ?? null;
            if (is_int($setBlockSize) && $payloadLength === $setBlockSize + 4) {
                $recoveryExponentsBySet[$setId][$exponent] = true;
                $recoveryExponents[$exponent] = true;
            } else {
                $invalidPacketCount++;
            }
        }
        $recoverySets = [];
        foreach ($recoverySetIds as $setId) {
            $setSources = $sourcesBySet[$setId] ?? [];
            $setMain = $mainsBySet[$setId] ?? null;
            $setMainFileIds = $setMain['all_file_ids'] ?? [];
            $setDescribedFileIds = array_keys($setSources);
            $setMissingFileIds = array_values(array_diff($setMainFileIds, $setDescribedFileIds));
            $setUnexpectedFileIds = array_values(array_diff($setDescribedFileIds, $setMainFileIds));
            $setBlockSize = $blockSizesBySet[$setId] ?? null;
            $setRecoverableIds = $setMain['recoverable_file_ids'] ?? [];
            $setSourceBlockCount = 0;
            foreach ($setSources as $fileId => $source) {
                if (in_array($fileId, $setRecoverableIds, true) && is_int($setBlockSize) && $setBlockSize > 0) {
                    $setSourceBlockCount += (int) ceil($source['file_length'] / $setBlockSize);
                }
            }
            $setIfsc = array_values(array_filter($inputFileSliceChecksums, static fn (array $ifsc): bool => $ifsc['recovery_set_id'] === $setId));
            $recoverySets[] = [
                'recovery_set_id' => $setId,
                'source_block_size' => $setBlockSize,
                'source_block_count' => $setSourceBlockCount ?: null,
                'main_packet' => $setMain,
                'source_inventory_complete' => $setMain !== null && $setMissingFileIds === [] && $setUnexpectedFileIds === [],
                'missing_file_description_ids' => $setMissingFileIds,
                'unexpected_file_description_ids' => $setUnexpectedFileIds,
                'source_file_count' => count($setSources),
                'source_file_bytes' => array_sum(array_column($setSources, 'file_length')),
                'source_files' => array_values($setSources),
                'input_file_slice_checksums' => $setIfsc,
                'unique_recovery_slice_count' => count($recoveryExponentsBySet[$setId] ?? []),
                'recovery_exponents' => array_map('intval', array_keys($recoveryExponentsBySet[$setId] ?? [])),
            ];
        }
        $singleSet = count($recoverySets) === 1 ? $recoverySets[0] : null;
        $blockSize = $singleSet['source_block_size'] ?? null;
        $main = $singleSet['main_packet'] ?? null;
        $sourceBlockCount = $singleSet['source_block_count'] ?? null;
        $sourceInventoryComplete = ($singleSet['source_inventory_complete'] ?? false) === true;
        $missingFileDescriptionIds = $singleSet['missing_file_description_ids'] ?? [];
        $unexpectedFileDescriptionIds = $singleSet['unexpected_file_description_ids'] ?? [];
        $sourceBytes = array_sum(array_map(static fn (array $set): int => $set['source_file_bytes'], $recoverySets));
        $recoveryCount = count($recoveryExponents);
        $flatSources = array_reduce(
            $recoverySets,
            static fn (array $files, array $set): array => array_merge($files, $set['source_files']),
            [],
        );
        $packedLayoutPresent = isset($optionalPackets["PAR 2.0\0PkdMain"]) || isset($optionalPackets["PAR 2.0\0PkdRecvS"]);

        return [
            'parser_engine' => 'php-fallback',
            'creator' => $creator,
            'packet_count' => $packetCount,
            'packet_limit_reached' => $packetCount >= $maximumPackets && $offset < $length,
            'trailing_unparsed_bytes' => max(0, $length - $offset),
            'unparsed_bytes' => $unparsedBytes,
            'invalid_packet_count' => $invalidPacketCount,
            'source_block_size' => $blockSize,
            'source_block_count' => $sourceBlockCount ?: null,
            'main_packet' => $main,
            'source_inventory_complete' => $sourceInventoryComplete,
            'missing_file_description_ids' => $missingFileDescriptionIds,
            'unexpected_file_description_ids' => $unexpectedFileDescriptionIds,
            'source_file_count' => count($flatSources),
            'source_file_bytes' => $sourceBytes,
            'source_files' => $flatSources,
            'input_file_slice_checksums' => $inputFileSliceChecksums,
            'recovery_set_ids' => $recoverySetIds,
            'recovery_sets' => $recoverySets,
            'unique_recovery_slice_count' => $recoveryCount,
            'recovery_exponents' => array_map('intval', array_keys($recoveryExponents)),
            'recovery_to_source_percent' => is_int($sourceBlockCount) && $sourceBlockCount > 0 && $main !== null && $sourceInventoryComplete && $invalidPacketCount === 0
                && ! ($packetCount >= $maximumPackets && $offset < $length) && ! $packedLayoutPresent
                ? round($recoveryCount / $sourceBlockCount * 100, 3) : null,
            'packed_layout_present' => $packedLayoutPresent,
            'recovery_percentage_basis' => $packedLayoutPresent
                ? 'unavailable-packed-recovery-layout'
                : 'unique-valid-recovery-exponents-over-recoverable-source-blocks',
            'optional_packet_summaries' => $optionalPackets,
            'packet_summaries' => $packetSummaries,
            'packet_type_counts' => array_map('intval', $packetTypeCounts),
        ];
    }

    private static function unsigned64(string $bytes): int
    {
        $parts = unpack('Vlow/Vhigh', $bytes);

        return (int) ($parts['low'] ?? 0) + ((int) ($parts['high'] ?? 0) * 4_294_967_296);
    }
}
