<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\SecondarySearchIndex;
use App\Facades\Search;
use App\Models\AnidbInfo;
use App\Models\AnidbTitle;
use Illuminate\Support\Facades\Log;

/**
 * When AniList-backed metadata changes, refresh all title rows for that anime in the search index.
 */
class AnidbInfoObserver
{
    public function __construct(
        private readonly AnidbTitleObserver $titleObserver
    ) {}

    public function created(AnidbInfo $anidbInfo): void
    {
        $this->reindexTitles($anidbInfo);
    }

    public function updated(AnidbInfo $anidbInfo): void
    {
        $this->reindexTitles($anidbInfo);
    }

    public function deleted(AnidbInfo $anidbInfo): void
    {
        try {
            foreach (AnidbTitle::query()->where('anidbid', $anidbInfo->anidbid)->cursor() as $titleRow) {
                $docId = SecondarySearchIndex::animeTitleDocumentId(
                    (int) $titleRow->anidbid,
                    (string) $titleRow->type,
                    (string) $titleRow->lang,
                    (string) $titleRow->title
                );
                Search::deleteSecondary(SecondarySearchIndex::Anime, $docId);
            }
        } catch (\Throwable $e) {
            Log::error('AnidbInfoObserver: delete from search index failed', [
                'anidbid' => $anidbInfo->anidbid,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function reindexTitles(AnidbInfo $anidbInfo): void
    {
        try {
            foreach (AnidbTitle::query()->where('anidbid', $anidbInfo->anidbid)->cursor() as $titleRow) {
                $this->titleObserver->upsert($titleRow);
            }
        } catch (\Throwable $e) {
            Log::error('AnidbInfoObserver: reindex titles failed', [
                'anidbid' => $anidbInfo->anidbid,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
