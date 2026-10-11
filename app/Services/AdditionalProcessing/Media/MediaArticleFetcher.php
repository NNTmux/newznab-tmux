<?php

declare(strict_types=1);

namespace App\Services\AdditionalProcessing\Media;

use App\Services\AdditionalProcessing\Enums\DownloadKind;
use App\Services\AdditionalProcessing\State\MediaByteBudget;
use App\Services\AdditionalProcessing\State\ReleaseProcessingContext;
use App\Services\AdditionalProcessing\UsenetDownloadService;

/**
 * Appends consecutive articles of one NZB file to a local file.
 *
 * Every article's yEnc header must say it starts where the previous one ended,
 * so a missing article or out-of-order NZB never produces a file with a hole in it.
 */
class MediaArticleFetcher
{
    public function __construct(private readonly UsenetDownloadService $downloadService) {}

    /**
     * @param  list<string>  $segments  the NZB file's message IDs, in order
     * @param  int|null  $expectedOffset  file offset the first article must start at, or null to accept any
     * @param  float  $deadline  hrtime seconds after which no article is requested
     * @return int bytes appended to $path
     */
    public function append(
        ReleaseProcessingContext $context,
        array $segments,
        int $fromSegment,
        ?int $expectedOffset,
        int $maxBytes,
        string $path,
        MediaByteBudget $budget,
        float $deadline,
        int $articleEstimate,
    ): int {
        $written = 0;

        for ($index = max(0, $fromSegment); $index < count($segments) && $written < $maxBytes; $index++) {
            if ($context->groupUnavailable || hrtime(true) / 1e9 >= $deadline || ! $budget->allows($articleEstimate)) {
                break;
            }

            $result = $this->downloadService->downloadArticle(
                DownloadKind::MediaSource,
                (string) $segments[$index],
                $context->releaseGroupName,
                $context->release->id,
            );
            if ($result['groupUnavailable']) {
                $context->groupUnavailable = true;

                break;
            }
            if (! $result['success'] || ! is_string($result['data'])) {
                break;
            }

            $budget->spend(strlen($result['data']));
            // A cached copy has no yEnc header; only a file's first article is placed without one.
            $offset = $result['part']?->offset() ?? ($index === 0 && $expectedOffset === 0 ? 0 : null);
            if ($offset === null || ($expectedOffset !== null && $offset !== $expectedOffset)) {
                break;
            }

            $chunk = substr($result['data'], 0, $maxBytes - $written);
            if (file_put_contents($path, $chunk, FILE_APPEND) === false) {
                break;
            }
            $written += strlen($chunk);
            $expectedOffset = $offset + strlen($result['data']);
        }

        return $written;
    }
}
