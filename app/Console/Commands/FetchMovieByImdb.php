<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\MovieService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class FetchMovieByImdb extends Command
{
    /**
     * The name and signature of the console command.
     *
     * Accepts either a numeric IMDb id (eg: 1234567) or prefixed (tt1234567). Will strip 'tt'.
     */
    protected $signature = 'nntmux:fetch-movie {imdbid : IMDb id with or without tt prefix}';

    /**
     * The console command description.
     */
    protected $description = 'Force fetch/update movie data for a specific IMDb id from external providers (TMDB/IMDB/Trakt/OMDB/iTunes/Fanart), always updating local data.';

    public function handle(): int
    {
        $raw = (string) $this->argument('imdbid');
        $normalized = strtolower(trim($raw));
        $normalized = preg_replace('/^tt/i', '', $normalized); // remove leading tt if present
        $imdbId = preg_replace('/\D/', '', $normalized); // keep digits only

        if ($imdbId === '' || ! ctype_digit($imdbId) || strlen($imdbId) < 5) {
            $this->error('Invalid IMDb id provided: '.$raw.' (parsed: '.$imdbId.')');

            return self::FAILURE;
        }

        $movie = app(MovieService::class);
        $movie->echooutput = true;
        $movie->service = 'console';

        $this->info('Force fetching movie data for IMDb id: tt'.$imdbId.' ...');

        // Clear all caches for this movie to force fresh data retrieval
        Cache::forget('tmdb_movie_'.md5('tt'.$imdbId));
        Cache::forget('trakt_movie_'.md5($imdbId));
        Cache::forget('imdb_movie_'.md5($imdbId));
        Cache::forget('omdb_movie_'.md5($imdbId));

        $ok = $movie->updateMovieInfo($imdbId);

        if (! $ok) {
            $this->error('Failed to fetch/update movie data for tt'.$imdbId.'.');

            return self::FAILURE;
        }

        $updated = $movie->getMovieInfo($imdbId);
        if ($updated === null) {
            $this->error('Movie info not found after update for tt'.$imdbId.'.');

            return self::FAILURE;
        }

        $this->line('');
        $this->info('Updated movie: '.($updated->title ?? 'Unknown Title').' ('.$updated->year.')');
        $this->line('IMDb: tt'.$imdbId);
        $this->line('TMDB ID: '.($updated->tmdbid ?? 'N/A'));
        $this->line('Trakt ID: '.($updated->traktid ?? 'N/A'));
        $this->line('Rating: '.($updated->rating ?? 'N/A'));
        $this->line('Genre: '.($updated->genre ?? 'N/A'));
        $this->line('Cover: '.(($updated->cover ?? 0) == 1 ? 'Yes' : 'No'));

        return self::SUCCESS;
    }
}
