<?php

declare(strict_types=1);

namespace App\Services\AdditionalProcessing;

use App\Services\AdditionalProcessing\Config\ProcessingConfiguration;
use App\Services\AdditionalProcessing\DTO\AdditionalWorkPlan;
use App\Services\AdditionalProcessing\DTO\ArchiveCandidate;
use Illuminate\Support\Facades\Log;

final readonly class AdditionalWorkPlanner
{
    private const string SEVEN_ZIP_PATTERN = '/([^"\s\/]+)\.7z(?:\.(\d{3}))?($|[ ")]|-)/i';

    private const string ARCHIVE_PATTERN = '/(\.(part\d+|[rz]\d+|rar|7z|0+|0*10?|zipr\d{2,3}|zipx?)("|\s*\.rar)*($|[ ")]|-])|"[a-f0-9]{32}\.[1-9]\d{1,2}".*\(\d+\/\d{2,}\)$)/i';

    public function __construct(private ProcessingConfiguration $config) {}

    /**
     * @param  array<int|string, mixed>  $nzbContents
     */
    public function plan(array $nzbContents, string $groupName): AdditionalWorkPlan
    {
        $sampleMessageIds = [];
        $jpgMessageIds = [];
        $mediaInfoMessageId = '';
        $audioInfoMessageId = '';
        $audioInfoExtension = '';
        $archiveCandidates = [];
        $bookFileCount = 0;
        $duplicateMessageIdCount = 0;
        $seenMessageIds = [];
        $sevenZipTails = [];

        foreach (array_values($nzbContents) as $sourceIndex => $file) {
            if (! is_array($file)) {
                continue;
            }

            try {
                $title = (string) ($file['title'] ?? '');
                $segments = is_array($file['segments'] ?? null) ? $file['segments'] : [];

                if (preg_match($this->config->ignoreBookRegex, $title) === 1) {
                    $bookFileCount++;
                }

                // 7z keeps its file list at the end of the last volume.
                $sevenZip = $this->sevenZipVolume($title);
                if ($sevenZip !== null && $segments !== []
                    && ($sevenZipTails[$sevenZip[0]][0] ?? -1) < $sevenZip[1]
                ) {
                    $sevenZipTails[$sevenZip[0]] = [$sevenZip[1], (string) $segments[array_key_last($segments)]];
                }

                if (preg_match(self::ARCHIVE_PATTERN, $title) === 1) {
                    $archiveMessageIds = $this->extractSegments(
                        $segments,
                        $this->config->maximumRarSegments,
                        $seenMessageIds,
                        $duplicateMessageIdCount,
                    );
                    if ($archiveMessageIds !== []) {
                        $archiveCandidates[] = new ArchiveCandidate(
                            title: $title,
                            messageIds: $archiveMessageIds,
                            likelyFirstVolume: $this->isLikelyFirstVolume($title),
                            sourceIndex: $sourceIndex,
                        );
                    }
                }

                if ($this->isSupportFile($title)) {
                    continue;
                }

                if ($this->config->processThumbnails && $sampleMessageIds === [] && $segments !== []
                    && stripos($title, 'sample') !== false
                    && preg_match('/\.(?:jpe?g|png|webp)$/i', $title) !== 1
                ) {
                    $sampleMessageIds = $this->extractSegments(
                        $segments,
                        $this->config->segmentsToDownload,
                        $seenMessageIds,
                        $duplicateMessageIdCount,
                    );
                }

                if ($this->config->processJPGSample && $jpgMessageIds === [] && $segments !== []
                    && preg_match('/flac|lossless|mp3|music|inner-sanctum|sound/i', $groupName) !== 1
                    && preg_match('/\.(?:jpe?g|png|webp)[. ")\]]/i', $title) === 1
                ) {
                    $jpgMessageIds = $this->extractSegments(
                        $segments,
                        $this->config->segmentsToDownload,
                        $seenMessageIds,
                        $duplicateMessageIdCount,
                    );
                }

                if ($this->config->processMediaInfo && $mediaInfoMessageId === '' && isset($segments[0])
                    && stripos($title, 'sample') !== false
                    && preg_match('/'.$this->config->videoFileRegex.'[. ")\]]/i', $title) === 1
                ) {
                    $mediaInfoMessageId = (string) $segments[0];
                    $this->recordMessageId($mediaInfoMessageId, $seenMessageIds, $duplicateMessageIdCount);
                }

                if ($this->config->processAudioInfo && $audioInfoMessageId === '' && isset($segments[0])
                    && preg_match('/'.$this->config->audioFileRegex.'[. ")\]]/i', $title, $type) === 1
                ) {
                    $audioInfoExtension = (string) ($type[1] ?? '');
                    $audioInfoMessageId = (string) $segments[0];
                    $this->recordMessageId($audioInfoMessageId, $seenMessageIds, $duplicateMessageIdCount);
                }
            } catch (\ErrorException $e) {
                Log::debug($e->getTraceAsString());
            }
        }

        foreach ($archiveCandidates as $index => $candidate) {
            $sevenZip = $this->sevenZipVolume($candidate->title);
            $tail = $sevenZip === null ? null : ($sevenZipTails[$sevenZip[0]][1] ?? null);
            if ($tail !== null && $candidate->likelyFirstVolume && ! in_array($tail, $candidate->messageIds, true)) {
                $archiveCandidates[$index] = new ArchiveCandidate(
                    title: $candidate->title,
                    messageIds: $candidate->messageIds,
                    likelyFirstVolume: true,
                    sourceIndex: $candidate->sourceIndex,
                    tailMessageIds: [$tail],
                );
            }
        }

        // Files without an extension can't be classified by name; sample their first segments.
        // Each entry must stand for exactly one NZB file, not several merged by a stripped subject.
        $probeCandidates = [];
        $probeIsOnlyFile = false;
        if ($archiveCandidates === []) {
            $unnamed = [];
            foreach (array_values($nzbContents) as $sourceIndex => $file) {
                if (is_array($file) && isset($file['segments'][0])
                    && (int) ($file['filecount'] ?? 0) === 1
                    && ! $this->hasFileExtension((string) ($file['title'] ?? ''))
                ) {
                    $unnamed[$sourceIndex] = $file;
                }
            }
            $probeIsOnlyFile = $unnamed !== [] && count($nzbContents) === 1;

            // Archive volumes carry the payload, so the largest files are tried first.
            uasort($unnamed, static fn (array $a, array $b): int => (int) ($b['size'] ?? 0) <=> (int) ($a['size'] ?? 0));
            foreach (array_slice($unnamed, 0, $probeIsOnlyFile ? 1 : $this->config->archiveProbeFiles, true) as $sourceIndex => $file) {
                $messageIds = $this->extractSegments(
                    $file['segments'],
                    max(1, $this->config->maximumRarSegments),
                    $seenMessageIds,
                    $duplicateMessageIdCount,
                );
                if ($messageIds === []) {
                    continue;
                }

                // A 7z found by the probe needs its last segment for the file list.
                $lastSegment = (string) $file['segments'][array_key_last($file['segments'])];
                $probeCandidates[] = new ArchiveCandidate(
                    title: (string) ($file['title'] ?? ''),
                    messageIds: $messageIds,
                    likelyFirstVolume: false,
                    sourceIndex: $sourceIndex,
                    tailMessageIds: $lastSegment !== $messageIds[0] ? [$lastSegment] : [],
                );
            }
        }

        $bookFlood = $bookFileCount > 80 && ($bookFileCount * 2) >= count($nzbContents);
        $unsupportedReasons = [];
        if ($bookFlood) {
            $unsupportedReasons[] = 'book-flood';
        }
        if ($archiveCandidates === []
            && $sampleMessageIds === []
            && $jpgMessageIds === []
            && $mediaInfoMessageId === ''
            && $audioInfoMessageId === ''
            && $probeCandidates === []
        ) {
            $unsupportedReasons[] = 'no-supported-candidates';
        }

        return new AdditionalWorkPlan(
            sampleMessageIds: $sampleMessageIds,
            jpgMessageIds: $jpgMessageIds,
            mediaInfoMessageId: $mediaInfoMessageId,
            audioInfoMessageId: $audioInfoMessageId,
            audioInfoExtension: $audioInfoExtension,
            archiveCandidates: $archiveCandidates,
            bookFileCount: $bookFileCount,
            bookFlood: $bookFlood,
            duplicateMessageIdCount: $duplicateMessageIdCount,
            unsupportedReasons: $unsupportedReasons,
            probeCandidates: $probeCandidates,
            probeIsOnlyFile: $probeIsOnlyFile,
        );
    }

    private function hasFileExtension(string $title): bool
    {
        $name = preg_match('/"([^"]+)"/', $title, $quoted) === 1 ? $quoted[1] : $title;

        return preg_match('/\.[a-z0-9]{2,4}$/i', trim($name)) === 1;
    }

    private function isSupportFile(string $title): bool
    {
        return preg_match(
            '/(?:'.$this->config->supportFileRegex.'|nfo\b|inf\b|ofn\b)($|[ ")]|-])(?!.{20,})/i',
            $title,
        ) === 1;
    }

    private function isLikelyFirstVolume(string $title): bool
    {
        if (preg_match('/\.part0*(\d+)/i', $title, $part) === 1) {
            return (int) $part[1] === 1;
        }

        if (preg_match('/"[a-f0-9]{32}\.[1-9]\d{1,2}".*\((\d+)\/\d{2,}\)$/i', $title, $position) === 1) {
            return (int) $position[1] === 1;
        }

        $sevenZip = $this->sevenZipVolume($title);
        if ($sevenZip !== null) {
            return $sevenZip[1] <= 1;
        }

        return preg_match('/\.(rar|zip)($|[ ")]|-])/i', $title) === 1;
    }

    /**
     * @return array{0: string, 1: int}|null set name and volume number (0 for a single .7z)
     */
    private function sevenZipVolume(string $title): ?array
    {
        if (preg_match('/"([^"\r\n]+)\.7z(?:\.(\d{3}))?"/i', $title, $match) === 1) {
            return [strtolower($match[1]), (int) ($match[2] ?? 0)];
        }

        if (preg_match(self::SEVEN_ZIP_PATTERN, $title, $match) !== 1) {
            return null;
        }

        return [strtolower($match[1]), (int) $match[2]];
    }

    /**
     * @param  array<int|string, mixed>  $segments
     * @param  array<string, true>  $seenMessageIds
     * @return list<string>
     */
    private function extractSegments(
        array $segments,
        int $limit,
        array &$seenMessageIds,
        int &$duplicateMessageIdCount,
    ): array {
        $messageIds = [];
        $requestMessageIds = [];

        foreach (array_slice($segments, 0, max($limit, 0)) as $segment) {
            $messageId = (string) $segment;
            if ($messageId === '' || isset($requestMessageIds[$messageId])) {
                if ($messageId !== '') {
                    $duplicateMessageIdCount++;
                }

                continue;
            }

            $requestMessageIds[$messageId] = true;
            $messageIds[] = $messageId;
            $this->recordMessageId($messageId, $seenMessageIds, $duplicateMessageIdCount);
        }

        return $messageIds;
    }

    /**
     * @param  array<string, true>  $seenMessageIds
     */
    private function recordMessageId(string $messageId, array &$seenMessageIds, int &$duplicateMessageIdCount): void
    {
        if (isset($seenMessageIds[$messageId])) {
            $duplicateMessageIdCount++;

            return;
        }

        $seenMessageIds[$messageId] = true;
    }
}
