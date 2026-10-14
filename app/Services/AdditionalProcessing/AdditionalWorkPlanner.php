<?php

declare(strict_types=1);

namespace App\Services\AdditionalProcessing;

use App\Services\AdditionalProcessing\Config\ProcessingConfiguration;
use App\Services\AdditionalProcessing\DTO\AdditionalWorkPlan;
use App\Services\AdditionalProcessing\DTO\ArchiveCandidate;
use App\Services\AdditionalProcessing\DTO\ImageCandidate;
use App\Services\AdditionalProcessing\Media\ImageCandidateRanker;
use Illuminate\Support\Facades\Log;

final readonly class AdditionalWorkPlanner
{
    private const string SEVEN_ZIP_PATTERN = '/([^"\s\/]+)\.7z(?:\.(\d{3}))?($|[ ")]|-)/i';

    private const string ARCHIVE_PATTERN = '/(\.(part\d+|[rz]\d+|rar|7z|0+|0*10?|zipr\d{2,3}|zipx?)("|\s*\.rar)*($|[ ")]|-])|"[a-f0-9]{32}\.[1-9]\d{1,2}".*\(\d+\/\d{2,}\)$)/i';

    /** Posted images smaller than this are icons or banners. */
    private const int MIN_IMAGE_POSTED_BYTES = 10240;

    private const int MAX_IMAGE_CANDIDATES = 3;

    private const int TYPICAL_ARTICLE_BYTES = 716800;

    public function __construct(private ProcessingConfiguration $config) {}

    /**
     * @param  array<int|string, mixed>  $nzbContents
     */
    public function plan(array $nzbContents, string $groupName): AdditionalWorkPlan
    {
        $sampleMessageIds = [];
        $jpgMessageIds = [];
        $images = [];
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
                            hasFirstSegment: ($file['firstsegment'] ?? true) !== false,
                            setKey: $this->archiveSetKey($title),
                        );
                    }
                }

                if ($this->isSupportFile($title)) {
                    continue;
                }

                if ($this->config->processThumbnails && $sampleMessageIds === [] && $segments !== []
                    && stripos($title, 'sample') !== false
                    && preg_match('/\.(?:jpe?g|png|webp)$/i', $title) !== 1
                    && preg_match(self::ARCHIVE_PATTERN, $title) !== 1
                ) {
                    $sampleMessageIds = $this->extractSegments(
                        $segments,
                        $this->config->segmentsToDownload,
                        $seenMessageIds,
                        $duplicateMessageIdCount,
                    );
                }

                if ($this->config->processJPGSample && $segments !== []
                    && preg_match('/flac|lossless|mp3|music|inner-sanctum|sound/i', $groupName) !== 1
                    && preg_match('/\.(?:jpe?g|png|webp)(?:[. ")\]]|$)/i', $title) === 1
                ) {
                    $images[] = [$sourceIndex, $title, $segments, (int) ($file['size'] ?? 0)];
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
                    hasFirstSegment: $candidate->hasFirstSegment,
                    setKey: $candidate->setKey,
                );
            }
        }

        $archiveCandidates = $this->preferRarVolumesWithHeaders($archiveCandidates);

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

        $imageCandidates = $this->imageCandidates($images, $seenMessageIds, $duplicateMessageIdCount);
        $jpgMessageIds = $imageCandidates[0]->messageIds ?? [];

        $par2IndexCandidates = $this->config->par2MediaMapping
            ? $this->par2IndexCandidates($nzbContents)
            : [];

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
            par2IndexCandidates: $par2IndexCandidates,
            mainVideoSourceIndexes: $this->mainVideoSourceIndexes($nzbContents),
            imageCandidates: $imageCandidates,
        );
    }

    /**
     * The best few posted images, each with every article it needs so it arrives whole.
     * Icons and images too large to download whole are skipped.
     *
     * @param  list<array{0: int, 1: string, 2: array<int|string, mixed>, 3: int}>  $images  [source index, subject, segments, posted size]
     * @param  array<string, true>  $seenMessageIds
     * @return list<ImageCandidate>
     */
    private function imageCandidates(array $images, array &$seenMessageIds, int &$duplicateMessageIdCount): array
    {
        $candidates = [];
        $ranked = ImageCandidateRanker::sort(
            $images,
            static fn (array $image): string => $image[1],
            static fn (array $image): int => $image[3],
        );

        foreach ($ranked as [$sourceIndex, $title, $segments, $postedSize]) {
            if ($postedSize > 0 && ($postedSize < self::MIN_IMAGE_POSTED_BYTES || $postedSize > $this->config->mediaImageMaxBytes)) {
                continue;
            }

            // Without a posted size, fetch as many articles as the size limit could fill.
            $limit = $postedSize > 0
                ? count($segments)
                : max($this->config->segmentsToDownload, (int) ceil($this->config->mediaImageMaxBytes / self::TYPICAL_ARTICLE_BYTES));
            $messageIds = $this->extractSegments($segments, $limit, $seenMessageIds, $duplicateMessageIdCount);
            if ($messageIds === []) {
                continue;
            }

            $candidates[] = new ImageCandidate($title, $messageIds, $sourceIndex, $postedSize);
            if (count($candidates) >= self::MAX_IMAGE_CANDIDATES) {
                break;
            }
        }

        return $candidates;
    }

    /**
     * The PAR2 index lists every file's real name and size. It is the smallest .par2 that
     * is not a recovery volume or, in an obfuscated release, one of its smallest unnamed files.
     *
     * @param  array<int|string, mixed>  $nzbContents
     * @return list<ArchiveCandidate>
     */
    private function par2IndexCandidates(array $nzbContents): array
    {
        $named = [];
        $unnamed = [];
        foreach (array_values($nzbContents) as $sourceIndex => $file) {
            if (! is_array($file) || ! isset($file['segments'][0]) || (int) ($file['filecount'] ?? 1) !== 1) {
                continue;
            }

            $title = (string) ($file['title'] ?? '');
            if (preg_match('/\.par2\b/i', $title) === 1) {
                if (preg_match('/\.vol\d+[+-]\d+\.par2\b/i', $title) !== 1) {
                    $named[$sourceIndex] = $file;
                }
            } elseif (! $this->hasFileExtension($title)) {
                $unnamed[$sourceIndex] = $file;
            }
        }

        $bySize = static fn (array $a, array $b): int => (int) ($a['size'] ?? 0) <=> (int) ($b['size'] ?? 0);
        uasort($named, $bySize);
        uasort($unnamed, $bySize);
        $chosen = $named !== []
            ? array_slice($named, 0, 1, true)
            // A lone unnamed file is the release itself, not an index of it.
            : (count($unnamed) >= 3 ? array_slice($unnamed, 0, 2, true) : []);

        $candidates = [];
        foreach ($chosen as $sourceIndex => $file) {
            $candidates[] = new ArchiveCandidate(
                title: (string) ($file['title'] ?? ''),
                messageIds: array_values(array_unique(array_map(
                    strval(...),
                    array_slice($file['segments'], 0, $this->config->par2IndexMaxSegments),
                ))),
                likelyFirstVolume: false,
                sourceIndex: $sourceIndex,
            );
        }

        return $candidates;
    }

    /**
     * Full-length video files posted as they are, which can stand in for a missing sample.
     *
     * @param  array<int|string, mixed>  $nzbContents
     * @return list<int>
     */
    private function mainVideoSourceIndexes(array $nzbContents): array
    {
        if (! $this->config->mediaMainVideoFallback || ! $this->config->processThumbnails) {
            return [];
        }

        $sizes = [];
        foreach (array_values($nzbContents) as $sourceIndex => $file) {
            if (! is_array($file) || ! isset($file['segments'][0]) || (int) ($file['filecount'] ?? 1) !== 1) {
                continue;
            }

            $title = (string) ($file['title'] ?? '');
            if ((int) ($file['size'] ?? 0) >= $this->config->mediaMainVideoMinBytes
                && stripos($title, 'sample') === false
                && preg_match(self::ARCHIVE_PATTERN, $title) !== 1
                && preg_match('/'.$this->config->videoFileRegex.'(?:[. ")\]]|$)/i', $title) === 1
            ) {
                $sizes[$sourceIndex] = (int) $file['size'];
            }
        }

        arsort($sizes);

        return array_slice(array_keys($sizes), 0, 2);
    }

    /**
     * Every RAR volume starts with its own archive and file headers, but only in segment 1.
     * When the first volume lacks that segment, inspect the earliest volume that has it.
     *
     * @param  list<ArchiveCandidate>  $candidates
     * @return list<ArchiveCandidate>
     */
    private function preferRarVolumesWithHeaders(array $candidates): array
    {
        $volumes = [];
        foreach ($candidates as $index => $candidate) {
            $volume = $this->rarVolume($candidate->title);
            if ($volume !== null) {
                $volumes[$volume[0]][$index] = $volume[1];
            }
        }

        foreach ($volumes as $set) {
            asort($set);
            $first = array_key_first($set);
            if (! $candidates[$first]->likelyFirstVolume || $candidates[$first]->hasFirstSegment) {
                continue;
            }

            foreach (array_keys($set) as $index) {
                if ($candidates[$index]->hasFirstSegment) {
                    $candidates[$first] = $this->withLikelyFirstVolume($candidates[$first], false);
                    $candidates[$index] = $this->withLikelyFirstVolume($candidates[$index], true);
                    break;
                }
            }
        }

        return $candidates;
    }

    private function withLikelyFirstVolume(ArchiveCandidate $candidate, bool $likelyFirstVolume): ArchiveCandidate
    {
        return new ArchiveCandidate(
            title: $candidate->title,
            messageIds: $candidate->messageIds,
            likelyFirstVolume: $likelyFirstVolume,
            sourceIndex: $candidate->sourceIndex,
            tailMessageIds: $candidate->tailMessageIds,
            hasFirstSegment: $candidate->hasFirstSegment,
            setKey: $candidate->setKey,
        );
    }

    /**
     * Name shared by every volume of one archive set (Show.S01E01.part01.rar and
     * Show.S01E01.part02.rar), so a season pack packed per episode can be read set by set.
     */
    private function archiveSetKey(string $title): string
    {
        $volume = $this->rarVolume($title) ?? $this->sevenZipVolume($title);
        if ($volume !== null) {
            return $volume[0];
        }

        if (preg_match('/"([a-f0-9]{32})\.[1-9]\d{1,2}"/i', $title, $hashed) === 1) {
            return strtolower($hashed[1]);
        }

        $name = preg_match('/"([^"\r\n]+)"/', $title, $quoted) === 1 ? $quoted[1] : $title;

        return strtolower((string) preg_replace('/\.(?:zipx?|zipr\d{2,3}|z\d{2,3}|rar|r\d{2,3}|7z|\d{3})$/i', '', trim($name)));
    }

    /**
     * @return array{0: string, 1: int}|null set name and volume order (.rar before .r00, part01 before part02)
     */
    private function rarVolume(string $title): ?array
    {
        [$name, $prefix] = preg_match('/"([^"\r\n]+)"/', $title, $quoted) === 1
            ? [$quoted[1], '^(.+)']
            : [$title, '([^"\s\/]+)'];

        if (preg_match('/'.$prefix.'\.part(\d+)\.rar(?:$|[ ")-])/i', $name, $match) === 1) {
            return [strtolower($match[1]), (int) $match[2]];
        }
        if (preg_match('/'.$prefix.'\.rar(?:$|[ ")-])/i', $name, $match) === 1) {
            return [strtolower($match[1]), 0];
        }
        if (preg_match('/'.$prefix.'\.r(\d{2,3})(?:$|[ ")-])/i', $name, $match) === 1) {
            return [strtolower($match[1]), (int) $match[2] + 1];
        }

        return null;
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
