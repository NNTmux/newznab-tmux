<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\SecondarySearchIndex;
use App\Facades\Search;
use App\Models\AnidbTitle;
use App\Support\SecondaryIndexDocuments;
use Illuminate\Support\Facades\Log;

class AnidbTitleObserver
{
    public function created(AnidbTitle $anidbTitle): void
    {
        $this->upsert($anidbTitle);
    }

    public function updated(AnidbTitle $anidbTitle): void
    {
        $this->upsert($anidbTitle);
    }

    public function upsert(AnidbTitle $anidbTitle): void
    {
        $this->sync($anidbTitle);
    }

    public function deleted(AnidbTitle $anidbTitle): void
    {
        try {
            $docId = SecondarySearchIndex::animeTitleDocumentId(
                (int) $anidbTitle->anidbid,
                (string) $anidbTitle->type,
                (string) $anidbTitle->lang,
                (string) $anidbTitle->title
            );
            Search::deleteSecondary(SecondarySearchIndex::Anime, $docId);
        } catch (\Throwable $e) {
            Log::error('AnidbTitleObserver: delete from search index failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sync(AnidbTitle $anidbTitle): void
    {
        try {
            $docId = SecondarySearchIndex::animeTitleDocumentId(
                (int) $anidbTitle->anidbid,
                (string) $anidbTitle->type,
                (string) $anidbTitle->lang,
                (string) $anidbTitle->title
            );
            Search::insertSecondary(
                SecondarySearchIndex::Anime,
                $docId,
                SecondaryIndexDocuments::animeTitle($anidbTitle)
            );
        } catch (\Throwable $e) {
            Log::error('AnidbTitleObserver: sync to search index failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
