<?php

declare(strict_types=1);

namespace App\Services\AdditionalProcessing\Media;

use App\Services\AdditionalProcessing\Config\ProcessingConfiguration;
use App\Services\AdditionalProcessing\DTO\ArchiveCandidate;
use App\Services\AdditionalProcessing\DTO\ArchivedMediaEntry;
use App\Services\AdditionalProcessing\Enums\VideoContainer;
use App\Services\AdditionalProcessing\State\ReleaseProcessingContext;
use dariusiii\rarinfo\ArchiveInfo;

/**
 * Finds videos stored uncompressed in archive volumes, as scene releases store them,
 * and cuts their bytes straight out of the downloaded data. No unrar is needed, and
 * the file listing says exactly where the video starts and how much the volume holds.
 */
class ArchivedMediaSlicer
{
    public function __construct(private readonly ProcessingConfiguration $config) {}

    /**
     * Keep the start of each stored video in a volume's listing for the media-source stage.
     *
     * @param  array<int|string, mixed>  $files  ArchiveInfo::getArchiveFileList() of $data
     */
    public function register(ReleaseProcessingContext $context, string $data, array $files, ?ArchiveCandidate $source): void
    {
        if (! $this->config->mediaArchiveSlicing || ! $this->config->processThumbnails || $source === null) {
            return;
        }

        foreach ($this->storedVideos($files) as $file) {
            $entry = $this->entryFor($data, $file, $source->sourceIndex, count($source->messageIds));
            if ($entry !== null) {
                $context->rememberArchivedMedia($entry);
            }
        }
    }

    /**
     * Read the chunk of a stored video that a later volume holds, from that volume's first bytes.
     */
    public function chunkFromVolume(string $volumeData, string $entryName): ?string
    {
        $archive = new ArchiveInfo;
        if (! $archive->setData($volumeData, true) || $archive->error) {
            return null;
        }

        foreach ($this->storedVideos($archive->getArchiveFileList()) as $file) {
            if ($file['name'] !== $entryName && basename(str_replace('\\', '/', $file['name'])) !== basename(str_replace('\\', '/', $entryName))) {
                continue;
            }

            [$start, $end] = $this->range($file);
            if ($start < strlen($volumeData)) {
                return substr($volumeData, $start, min($end, strlen($volumeData) - 1) - $start + 1);
            }
        }

        return null;
    }

    /**
     * @param  array{name: string, size: int, range: string, next_offset: int|null}  $file
     */
    private function entryFor(string $data, array $file, int $sourceIndex, int $segmentsDownloaded): ?ArchivedMediaEntry
    {
        [$start] = $this->range($file);
        if ($start >= strlen($data)) {
            return null;
        }

        $size = $file['size'];
        $inVolumeEnd = $file['next_offset'] !== null && $file['next_offset'] > $start
            ? $file['next_offset'] - 1
            : $start + $size - 1;
        $available = min($inVolumeEnd, strlen($data) - 1) - $start + 1;
        $head = substr($data, $start, min($available, $this->config->mediaSampleMaxBytes));
        $name = $file['name'];

        return new ArchivedMediaEntry(
            name: $name,
            realSize: $size,
            sourceIndex: $sourceIndex,
            headBytes: $head,
            rangeStart: $start,
            inVolumeEnd: $inVolumeEnd,
            volumeBytesDownloaded: strlen($data),
            volumeSegmentsDownloaded: $segmentsDownloaded,
            isSample: preg_match('/(?:^|[\\\\\/._ -])sample(?:[\\\\\/._ -]|$)/i', $name) === 1
                || $size < $this->config->mediaMainVideoMinBytes,
            container: VideoContainer::detect($head) ?? VideoContainer::fromFileName($name),
        );
    }

    /**
     * Unencrypted, uncompressed video entries of the archive itself (not of nested archives).
     *
     * @param  array<int|string, mixed>  $files
     * @return list<array{name: string, size: int, range: string, next_offset: int|null}>
     */
    private function storedVideos(array $files): array
    {
        $videos = [];
        foreach ($files as $file) {
            if (is_array($file)
                && is_string($file['name'] ?? null)
                && isset($file['size'], $file['range'])
                && ($file['source'] ?? 'main') === 'main'
                && empty($file['pass'])
                && empty($file['is_dir'])
                && ($file['compressed'] ?? null) === 0
                && (int) $file['size'] > 0
                && VideoContainer::fromFileName($file['name']) !== null
            ) {
                $videos[] = [
                    'name' => $file['name'],
                    'size' => (int) $file['size'],
                    'range' => (string) $file['range'],
                    'next_offset' => isset($file['next_offset']) ? (int) $file['next_offset'] : null,
                ];
            }
        }

        return $videos;
    }

    /**
     * @param  array{range: string}  $file
     * @return array{0: int, 1: int}
     */
    private function range(array $file): array
    {
        $bounds = explode('-', $file['range'], 2);

        return [(int) $bounds[0], (int) ($bounds[1] ?? -1)];
    }
}
