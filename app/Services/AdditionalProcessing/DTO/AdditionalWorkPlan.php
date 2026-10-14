<?php

declare(strict_types=1);

namespace App\Services\AdditionalProcessing\DTO;

final readonly class AdditionalWorkPlan
{
    /**
     * @param  list<string>  $sampleMessageIds
     * @param  list<string>  $jpgMessageIds
     * @param  list<ArchiveCandidate>  $archiveCandidates
     * @param  list<string>  $unsupportedReasons
     * @param  list<ArchiveCandidate>  $probeCandidates  unnamed files to sample, in order
     * @param  list<ArchiveCandidate>  $par2IndexCandidates  files that may be the PAR2 index, in order
     * @param  list<int>  $mainVideoSourceIndexes  source indexes of full-length video files, largest first
     * @param  list<ImageCandidate>  $imageCandidates  posted images, best first; the first one's IDs are $jpgMessageIds
     */
    public function __construct(
        public array $sampleMessageIds = [],
        public array $jpgMessageIds = [],
        public string $mediaInfoMessageId = '',
        public string $audioInfoMessageId = '',
        public string $audioInfoExtension = '',
        public array $archiveCandidates = [],
        public int $bookFileCount = 0,
        public bool $bookFlood = false,
        public int $duplicateMessageIdCount = 0,
        public array $unsupportedReasons = [],
        public array $probeCandidates = [],
        public bool $probeIsOnlyFile = false,
        public array $par2IndexCandidates = [],
        public array $mainVideoSourceIndexes = [],
        public array $imageCandidates = [],
    ) {}

    /**
     * Fill what this plan lacks from a plan made after the PAR2 index revealed real file names.
     * Anything found under the posted names is kept.
     */
    public function withMappedNames(self $mapped): self
    {
        $takeArchives = $this->archiveCandidates === [] && $mapped->archiveCandidates !== [];
        $keepAudio = $this->audioInfoMessageId !== '';

        $merged = new self(
            sampleMessageIds: $this->sampleMessageIds !== [] ? $this->sampleMessageIds : $mapped->sampleMessageIds,
            jpgMessageIds: $this->jpgMessageIds !== [] ? $this->jpgMessageIds : $mapped->jpgMessageIds,
            mediaInfoMessageId: $this->mediaInfoMessageId !== '' ? $this->mediaInfoMessageId : $mapped->mediaInfoMessageId,
            audioInfoMessageId: $keepAudio ? $this->audioInfoMessageId : $mapped->audioInfoMessageId,
            audioInfoExtension: $keepAudio ? $this->audioInfoExtension : $mapped->audioInfoExtension,
            archiveCandidates: $takeArchives ? $mapped->archiveCandidates : $this->archiveCandidates,
            bookFileCount: $this->bookFileCount,
            bookFlood: $this->bookFlood,
            duplicateMessageIdCount: $this->duplicateMessageIdCount,
            unsupportedReasons: $this->unsupportedReasons,
            // Only files still unnamed are worth probing.
            probeCandidates: $this->archiveCandidates !== [] ? $this->probeCandidates : $mapped->probeCandidates,
            probeIsOnlyFile: $this->archiveCandidates !== [] ? $this->probeIsOnlyFile : $mapped->probeIsOnlyFile,
            par2IndexCandidates: $this->par2IndexCandidates,
            mainVideoSourceIndexes: $this->mainVideoSourceIndexes !== [] ? $this->mainVideoSourceIndexes : $mapped->mainVideoSourceIndexes,
            imageCandidates: $this->jpgMessageIds !== [] ? $this->imageCandidates : $mapped->imageCandidates,
        );

        if (! $merged->hasSupportedCandidates()) {
            return $merged;
        }

        return new self(...[
            ...get_object_vars($merged),
            'unsupportedReasons' => array_values(array_diff($merged->unsupportedReasons, ['no-supported-candidates'])),
        ]);
    }

    public function hasSupportedCandidates(): bool
    {
        return $this->archiveCandidates !== []
            || $this->sampleMessageIds !== []
            || $this->jpgMessageIds !== []
            || $this->mediaInfoMessageId !== ''
            || $this->audioInfoMessageId !== ''
            || $this->probeCandidates !== [];
    }

    public function hasCompressedFile(): bool
    {
        return $this->archiveCandidates !== [];
    }

    /**
     * Distinct archive sets in the release, e.g. one per episode of a season pack.
     */
    public function archiveSetCount(): int
    {
        return count(array_unique(array_map(fn (ArchiveCandidate $candidate): string => $this->setKeyOf($candidate), $this->archiveCandidates)));
    }

    public function setKeyOf(ArchiveCandidate $candidate): string
    {
        return $candidate->setKey !== '' ? $candidate->setKey : 'src:'.$candidate->sourceIndex;
    }

    /**
     * @return list<ArchiveCandidate>
     */
    public function orderedArchiveCandidates(bool $reverse = false): array
    {
        return $reverse ? array_reverse($this->archiveCandidates) : $this->archiveCandidates;
    }

    /**
     * Return a stable priority view without changing the legacy processing order.
     *
     * @return list<ArchiveCandidate>
     */
    public function prioritizedArchiveCandidates(): array
    {
        $firstVolumes = [];
        $remaining = [];

        foreach ($this->archiveCandidates as $candidate) {
            if ($candidate->likelyFirstVolume) {
                $firstVolumes[] = $candidate;
            } else {
                $remaining[] = $candidate;
            }
        }

        return [...$firstVolumes, ...$remaining];
    }
}
