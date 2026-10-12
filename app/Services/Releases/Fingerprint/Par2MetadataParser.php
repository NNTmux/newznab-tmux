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
        $main = null;
        $recoveryExponents = [];
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
                $main = [
                    'recoverable_file_count' => $recoverableFileCount,
                    'recoverable_file_ids' => array_slice($fileIds, 0, $recoverableFileCount),
                    'non_recoverable_file_ids' => array_slice($fileIds, $recoverableFileCount),
                ];
            } elseif ($type === "PAR 2.0\0FileDesc" && strlen($payload) >= 57) {
                $fileSize = self::unsigned64(substr($payload, 48, 8));
                $name = strstr(substr($payload, 56), "\0", true);
                $sources[bin2hex(substr($payload, 0, 16))] = [
                    'filename' => $name === false ? substr($payload, 56) : $name,
                    'file_length' => $fileSize,
                    'md5' => bin2hex(substr($payload, 16, 16)),
                    'md5_first_16k' => bin2hex(substr($payload, 32, 16)),
                    'file_id' => bin2hex(substr($payload, 0, 16)),
                ];
            } elseif ($type === "PAR 2.0\0Main" || $type === "PAR 2.0\0FileDesc") {
                $invalidPacketCount++;
            } elseif ($type === "PAR 2.0\0RecvSlic" && strlen($payload) >= 4) {
                $recoveryPacketLengths[] = [(int) (unpack('V', substr($payload, 0, 4))[1] ?? 0), strlen($payload)];
            } elseif ($type === "PAR 2.0\0Creator") {
                $creator = trim(strtok($payload, "\0") ?: $payload);
            } elseif (in_array($type, ["PAR 2.0\0UniFileN", "PAR 2.0\0CommASCI", "PAR 2.0\0CommUni", "PAR 2.0\0FileSlic", "PAR 2.0\0IFSC", "PAR 2.0\0RFSC", "PAR 2.0\0PkdMain", "PAR 2.0\0PkdRecvS"], true)) {
                $optionalPackets[$type][] = [
                    'payload_bytes' => strlen($payload),
                    'payload_sha256' => hash('sha256', $payload),
                    'payload_hex' => bin2hex(substr($payload, 0, 256)),
                ];
            }
            $offset += (int) $packetLength;
        }

        $sourceBlockCount = 0;
        foreach ($recoveryPacketLengths as [$exponent, $payloadLength]) {
            if ($blockSize === null || $payloadLength === $blockSize + 4) {
                $recoveryExponents[$exponent] = true;
            } else {
                $invalidPacketCount++;
            }
        }
        $sourceBytes = 0;
        $recoverableIds = $main['recoverable_file_ids'] ?? array_keys($sources);
        foreach ($sources as $fileId => $source) {
            $sourceBytes += $source['file_length'];
            if (in_array($fileId, $recoverableIds, true) && $blockSize !== null && $blockSize > 0 && $source['file_length'] > 0) {
                $sourceBlockCount += (int) ceil($source['file_length'] / $blockSize);
            }
        }
        $recoveryCount = count($recoveryExponents);
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
            'source_file_count' => count($sources),
            'source_file_bytes' => $sourceBytes,
            'source_files' => array_values($sources),
            'unique_recovery_slice_count' => $recoveryCount,
            'recovery_exponents' => array_map('intval', array_keys($recoveryExponents)),
            'recovery_to_source_percent' => $sourceBlockCount > 0 && $main !== null && $invalidPacketCount === 0
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
