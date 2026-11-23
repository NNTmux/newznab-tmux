<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Exceptions\TvProviderException;
use App\Models\MovieInfo;
use App\Services\MdbListClient;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class RefreshRottenTomatoesScores extends Command
{
    /**
     * Where the previous run stopped, so a limited run continues instead of asking about the same movies again.
     */
    private const string CURSOR_CACHE_KEY = 'movie:refresh-rt-scores:cursor';

    /**
     * @var string
     */
    protected $signature = 'movie:refresh-rt-scores
        {--limit=5000 : Maximum number of movies to ask MDBList about in this run}
        {--all : Also refresh movies that already have a score}
        {--restart : Start from the first movie instead of where the previous run stopped}
        {--dry-run : Report the scores MDBList has without storing them}';

    /**
     * @var string
     */
    protected $description = 'Fill in Rotten Tomatoes scores for stored movies from MDBList, several movies per request';

    public function handle(MdbListClient $mdbList): int
    {
        if (! $mdbList->isConfigured()) {
            $this->error('MDBList is not configured: set MDBLIST_APIKEY.');

            return self::FAILURE;
        }

        $all = (bool) $this->option('all');
        $dryRun = (bool) $this->option('dry-run');
        $limit = max(1, (int) $this->option('limit'));
        $cursorKey = self::CURSOR_CACHE_KEY.($all ? ':all' : ':missing');
        $lastId = $this->option('restart') ? 0 : (int) Cache::get($cursorKey, 0);

        $asked = 0;
        $changed = 0;
        $finished = false;

        while ($asked < $limit) {
            $movies = $this->candidates($all)
                ->where('id', '>', $lastId)
                ->orderBy('id')
                ->limit(min($mdbList->batchSize(), $limit - $asked))
                ->get(['id', 'imdbid', 'rtrating']);

            if ($movies->isEmpty()) {
                $finished = true;
                break;
            }

            try {
                $scores = $mdbList->tomatometerScores($movies->pluck('imdbid')->map(static fn (mixed $imdbId): string => (string) $imdbId)->all());
            } catch (TvProviderException $e) {
                $this->warn('Stopped: '.$e->getMessage().($e->retryAfterSeconds !== null ? ' (retry in '.$e->retryAfterSeconds.' s)' : ''));
                break;
            }

            foreach ($movies as $movie) {
                $score = $scores[(string) $movie->imdbid] ?? null;
                if ($score === null || $movie->rtrating === $score.'%') {
                    continue;
                }

                $changed++;
                if ($this->output->isVerbose() || $dryRun) {
                    $this->line(sprintf('tt%s: %s -> %d%%', $movie->imdbid, $movie->rtrating !== '' ? $movie->rtrating : 'none', $score));
                }

                if (! $dryRun) {
                    // Only the score changes: the row keeps its age, so the regular metadata refresh still runs on time.
                    $movie->timestamps = false;
                    $movie->rtrating = $score.'%';
                    $movie->saveQuietly();
                }
            }

            $asked += $movies->count();
            $lastId = (int) $movies->last()->id;
        }

        if (! $dryRun) {
            $finished ? Cache::forget($cursorKey) : Cache::forever($cursorKey, $lastId);
        }

        $remaining = $finished ? 0 : $this->candidates($all)->where('id', '>', $lastId)->count();
        $this->info(sprintf(
            '%s %d of %d movies asked about; %d left for the next run.',
            $dryRun ? 'Would update' : 'Updated',
            $changed,
            $asked,
            $remaining,
        ));

        return self::SUCCESS;
    }

    /**
     * Movies to ask about: those without a percentage score ('', "N/A", old "45/100" values),
     * or every movie with --all.
     *
     * @return Builder<MovieInfo>
     */
    private function candidates(bool $all): Builder
    {
        $query = MovieInfo::query()->where('imdbid', '!=', '');

        return $all ? $query : $query->whereRaw("rtrating NOT LIKE '%!%' ESCAPE '!'");
    }
}
