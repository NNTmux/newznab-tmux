<?php

declare(strict_types=1);

namespace App\Services\AdditionalProcessing\Media;

use App\Services\AdditionalProcessing\Config\ProcessingConfiguration;
use App\Services\AdditionalProcessing\DTO\ArchiveCandidate;
use App\Services\AdditionalProcessing\DTO\MediaSourcePlan;
use App\Services\AdditionalProcessing\DTO\Par2FileEntry;
use App\Services\AdditionalProcessing\Enums\DownloadKind;
use App\Services\AdditionalProcessing\State\MediaByteBudget;
use App\Services\AdditionalProcessing\State\ReleaseProcessingContext;
use App\Services\AdditionalProcessing\UsenetDownloadService;
use dariusiii\rarinfo\Par2Info;

/**
 * Reads the release's PAR2 index to learn the real names behind obfuscated NZB files.
 */
class MediaSourceResolver
{
    private const string PAR2_SIGNATURE = "PAR2\0PKT";

    public function __construct(
        private readonly ProcessingConfiguration $config,
        private readonly UsenetDownloadService $downloadService,
        private readonly Par2FileMapper $mapper = new Par2FileMapper,
    ) {}

    public function resolve(ReleaseProcessingContext $context): ?MediaSourcePlan
    {
        $workPlan = $context->workPlan;
        if (! $this->config->par2MediaMapping || $workPlan === null || $workPlan->par2IndexCandidates === []) {
            return null;
        }

        $budget = $context->mediaBudget ??= new MediaByteBudget($this->config->mediaMaxBytes);
        $files = $this->readIndex($context, $workPlan->par2IndexCandidates, $budget);
        if ($files === []) {
            return null;
        }

        [$verified, $articlePartSize] = $this->verifyAmbiguousFiles($context, $files, $budget);

        $mapped = $this->mapper->map($context->nzbContents, $files, $articlePartSize, $verified);

        return $mapped === [] ? null : new MediaSourcePlan($mapped, $articlePartSize);
    }

    /**
     * @param  list<ArchiveCandidate>  $candidates
     * @return list<Par2FileEntry>
     */
    private function readIndex(ReleaseProcessingContext $context, array $candidates, MediaByteBudget $budget): array
    {
        foreach ($candidates as $candidate) {
            if ($context->groupUnavailable || ! $budget->allows($this->estimatedBytes($context, $candidate->sourceIndex, count($candidate->messageIds)))) {
                return [];
            }

            $result = $this->downloadService->download(
                DownloadKind::Par2Index,
                $candidate->messageIds,
                $context->releaseGroupName,
                $context->release->id,
                $candidate->title,
                cache: false,
            );
            if ($result['groupUnavailable']) {
                $context->groupUnavailable = true;

                return [];
            }
            if (! $result['success'] || ! is_string($result['data'])) {
                continue;
            }

            $budget->spend(strlen($result['data']));
            if (! str_starts_with($result['data'], self::PAR2_SIGNATURE)) {
                continue;
            }

            $par2Info = new Par2Info;
            $par2Info->setData($result['data']);
            if ($par2Info->error) {
                continue;
            }

            $files = Par2FileEntry::listFromPar2Info($par2Info->getFileList());
            if ($files !== []) {
                return $files;
            }
        }

        return [];
    }

    /**
     * Download the first article of NZB files that more than one wanted PAR2 entry could be,
     * and identify them by the PAR2 hash of their first 16 KiB or their yEnc name.
     *
     * @param  list<Par2FileEntry>  $files
     * @return array{0: array<int, Par2FileEntry>, 1: int|null}
     */
    private function verifyAmbiguousFiles(ReleaseProcessingContext $context, array $files, MediaByteBudget $budget): array
    {
        $verified = [];
        $articlePartSize = null;
        $attempts = 0;
        $sizeMatched = $this->mapper->map($context->nzbContents, $files);
        $sizeMatchedFiles = array_map(static fn ($mapped): Par2FileEntry => $mapped->file, $sizeMatched);

        foreach ($this->wantedFiles($files) as $wanted) {
            if (in_array($wanted, $sizeMatchedFiles, true) || in_array($wanted, $verified, true)) {
                continue;
            }

            foreach ($this->mapper->candidateIndexes($context->nzbContents, $wanted, $articlePartSize) as $sourceIndex) {
                if ($attempts >= $this->config->par2VerifyFiles || $context->groupUnavailable) {
                    break 2;
                }
                if (isset($verified[$sourceIndex])) {
                    continue;
                }

                $messageId = (string) ($context->nzbContents[$sourceIndex]['segments'][0] ?? '');
                if ($messageId === '' || ! $budget->allows($this->estimatedBytes($context, $sourceIndex, 1))) {
                    break 2;
                }

                $attempts++;
                $result = $this->downloadService->downloadArticle(
                    DownloadKind::MediaSource,
                    $messageId,
                    $context->releaseGroupName,
                    $context->release->id,
                );
                if ($result['groupUnavailable']) {
                    $context->groupUnavailable = true;

                    break 2;
                }
                if (! $result['success'] || ! is_string($result['data'])) {
                    continue;
                }
                $budget->spend(strlen($result['data']));

                $part = $result['part'];
                if ($part !== null && $part->length() !== null && $part->end !== null && $part->end < $part->size) {
                    $articlePartSize ??= $part->length();
                }

                foreach ($files as $file) {
                    if (in_array($file, $verified, true)) {
                        continue;
                    }
                    if ($file->matchesStartOf($result['data'])
                        || ($part !== null && $part->name === $file->name && $part->size === $file->size)
                    ) {
                        $verified[$sourceIndex] = $file;

                        break;
                    }
                }

                if (in_array($wanted, $verified, true)) {
                    break;
                }
            }
        }

        return [$verified, $articlePartSize];
    }

    /**
     * PAR2 entries worth identifying for previews and archive inspection, most useful first:
     * first archive volumes, then samples, then other videos (largest first), then images.
     *
     * @param  list<Par2FileEntry>  $files
     * @return list<Par2FileEntry>
     */
    private function wantedFiles(array $files): array
    {
        $ranked = [];
        foreach ($files as $file) {
            $rank = match (true) {
                preg_match('/\.(?:part0*1\.rar|rar|7z(?:\.0*1)?|zip|0*1)$/i', $file->name) === 1
                    && preg_match('/\.part(?!0*1\.)\d+\.rar$/i', $file->name) !== 1 => 0,
                preg_match('/'.$this->config->videoFileRegex.'$/i', $file->name) === 1
                    && preg_match('/(?:^|[\\\\\/._ -])sample(?:[\\\\\/._ -]|$)/i', $file->name) === 1 => 1,
                preg_match('/'.$this->config->videoFileRegex.'$/i', $file->name) === 1 => 2,
                preg_match('/\.(?:jpe?g|png|webp)$/i', $file->name) === 1 => 3,
                default => null,
            };
            if ($rank !== null) {
                $ranked[] = [$rank, -$file->size, $file];
            }
        }

        usort($ranked, static fn (array $a, array $b): int => [$a[0], $a[1]] <=> [$b[0], $b[1]]);

        return array_column($ranked, 2);
    }

    private function estimatedBytes(ReleaseProcessingContext $context, int $sourceIndex, int $articles): int
    {
        $entry = $context->nzbContents[$sourceIndex] ?? [];
        $segments = is_array($entry['segments'] ?? null) ? max(1, count($entry['segments'])) : 1;

        return (int) ceil((int) ($entry['size'] ?? 0) / $segments * min($articles, $segments));
    }
}
