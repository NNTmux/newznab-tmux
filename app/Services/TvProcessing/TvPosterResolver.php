<?php

declare(strict_types=1);

namespace App\Services\TvProcessing;

use App\Services\FanartTvService;
use App\Services\TmdbClient;
use Illuminate\Support\Facades\Log;

/**
 * Picks a poster URL for a show: the provider's own poster, then TMDB, then Fanart.tv.
 */
class TvPosterResolver
{
    public function __construct(
        private readonly ?TmdbClient $tmdb = null,
        private readonly ?FanartTvService $fanart = null,
    ) {}

    /**
     * @param  array<string, mixed>  $show  AbstractTvProvider::formatShowInfo() output
     */
    public function resolve(array $show): string
    {
        $poster = (string) ($show['poster'] ?? '');
        if ($poster !== '') {
            return $poster;
        }

        try {
            $tmdbId = (int) ($show['tmdb'] ?? 0);
            $tmdb = $this->tmdb ?? app(TmdbClient::class);
            if ($tmdbId > 0 && $tmdb->isConfigured()) {
                $path = TmdbClient::getString($tmdb->getTvShow($tmdbId) ?? [], 'poster_path');
                if ($path !== '') {
                    return $tmdb->getImageUrl($path);
                }
            }

            $tvdbId = (int) ($show['tvdb'] ?? 0);
            $fanart = $this->fanart ?? new FanartTvService;
            if ($tvdbId > 0 && $fanart->isConfigured()) {
                return (string) $fanart->getBestTvPoster($tvdbId);
            }
        } catch (\Throwable $e) {
            Log::info('TV poster lookup failed', ['title' => $show['title'] ?? '', 'error' => $e->getMessage()]);
        }

        return '';
    }
}
