<?php

namespace App\Http\Controllers;

use App\Models\UserSerie;
use App\Models\Video;
use Blacklight\Releases;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class SeriesController extends BasePageController
{
    /**
     * @throws \Exception
     */
    public function index(Request $request, string $id = '')
    {
        $releases = new Releases;

        if ($id && ctype_digit($id)) {
            $category = -1;
            if ($request->has('t') && ctype_digit($request->input('t'))) {
                $category = $request->input('t');
            }

            $catarray = [];
            $catarray[] = $category;

            $page = $request->has('page') && is_numeric($request->input('page')) ? $request->input('page') : 1;
            $offset = ($page - 1) * config('nntmux.items_per_page');

            $rel = $releases->tvSearch(['id' => $id], '', '', '', $offset, 1000, '', $catarray, -1);

            $show = Video::getByVideoID($id);

            $nodata = '';
            $seasons = [];
            $myshows = null;
            $seriestitles = '';
            $seriessummary = '';
            $seriescountry = '';

            if (! $show) {
                $nodata = 'No video information for this series.';
            } elseif (! $rel) {
                $nodata = 'No releases for this series.';
            } else {
                $myshows = UserSerie::getShow($this->userdata->id, $show['id']);

                // Sort releases by season, episode, date posted.
                $series = $episode = $posted = [];
                foreach ($rel as $rlk => $rlv) {
                    $series[$rlk] = $rlv->series;
                    $episode[$rlk] = $rlv->episode;
                    $posted[$rlk] = $rlv->postdate;
                }
                Arr::sort($series, [[$episode, false], [$posted, false], $rel]);

                $series = [];
                foreach ($rel as $r) {
                    $series[$r->series][$r->episode][] = $r;
                }

                $seasons = Arr::sortRecursive($series);

                // get series name(s), description, country and genre
                $seriestitlesArray = $seriessummaryArray = $seriescountryArray = [];
                $seriestitlesArray[] = $show['title'];

                if (! empty($show['summary'])) {
                    $seriessummaryArray[] = $show['summary'];
                }

                if (! empty($show['countries_id'])) {
                    $seriescountryArray[] = $show['countries_id'];
                }

                $seriestitles = implode('/', array_map('trim', $seriestitlesArray));
                $seriessummary = $seriessummaryArray ? array_shift($seriessummaryArray) : '';
                $seriescountry = $seriescountryArray ? array_shift($seriescountryArray) : '';
            }

            // Calculate statistics
            $episodeCount = 0;
            $seasonCount = count($seasons);
            $totalSeasonsAvailable = $seasonCount;

            // Get first and last aired dates from TV episodes
            $firstEpisodeAired = null;
            $lastEpisodeAired = null;
            $totalSeasonsAired = 0;
            $totalEpisodesAired = 0;

            if (! empty($show['id'])) {
                $episodeStats = \App\Models\TvEpisode::query()
                    ->where('videos_id', $show['id'])
                    ->whereNotNull('firstaired')
                    ->where('firstaired', '!=', '')
                    ->selectRaw('MIN(firstaired) as first_aired, MAX(firstaired) as last_aired, COUNT(DISTINCT series) as total_seasons, COUNT(*) as total_episodes')
                    ->first();

                if ($episodeStats) {
                    if (! empty($episodeStats->first_aired) && $episodeStats->first_aired != '0000-00-00') {
                        $firstEpisodeAired = \Carbon\Carbon::parse($episodeStats->first_aired);
                    }
                    if (! empty($episodeStats->last_aired) && $episodeStats->last_aired != '0000-00-00') {
                        $lastEpisodeAired = \Carbon\Carbon::parse($episodeStats->last_aired);
                    }
                    $totalSeasonsAired = $episodeStats->total_seasons ?? 0;
                    $totalEpisodesAired = $episodeStats->total_episodes ?? 0;
                }
            }

            foreach ($seasons as $seasonNum => $episodes) {
                $episodeCount += count($episodes);
            }

            $catid = $category !== -1 ? $category : '';

            $this->viewData = array_merge($this->viewData, [
                'seasons' => $seasons,
                'show' => $show,
                'myshows' => $myshows,
                'seriestitles' => $seriestitles,
                'seriessummary' => $seriessummary,
                'seriescountry' => $seriescountry,
                'category' => $catid,
                'nodata' => $nodata,
                'episodeCount' => $episodeCount,
                'seasonCount' => $seasonCount,
                'firstEpisodeAired' => $firstEpisodeAired,
                'lastEpisodeAired' => $lastEpisodeAired,
                'totalSeasonsAvailable' => $totalSeasonsAvailable,
                'totalSeasonsAired' => $totalSeasonsAired,
                'totalEpisodesAired' => $totalEpisodesAired,
                'meta_title' => 'View TV Series',
                'meta_keywords' => 'view,series,tv,show,description,details',
                'meta_description' => 'View TV Series',
            ]);

            return view('series.viewseries', $this->viewData);
        } else {
            $letter = ($id && preg_match('/^(0\-9|[A-Z])$/i', $id)) ? $id : '0-9';

            $showname = ($request->has('title') && ! empty($request->input('title'))) ? $request->input('title') : '';

            if ($showname !== '' && ! $id) {
                $letter = '';
            }

            $masterserieslist = Video::getSeriesList($this->userdata->id, $letter, $showname);

            $serieslist = [];
            foreach ($masterserieslist as $s) {
                if (preg_match('/^[0-9]/', $s['title'])) {
                    $thisrange = '0-9';
                } else {
                    preg_match('/([A-Z]).*/i', $s['title'], $hits);
                    $thisrange = strtoupper($hits[1]);
                }
                $serieslist[$thisrange][] = $s;
            }
            ksort($serieslist);

            $this->viewData = array_merge($this->viewData, [
                'serieslist' => $serieslist,
                'seriesrange' => range('A', 'Z'),
                'seriesletter' => $letter,
                'showname' => $showname,
                'meta_title' => 'View Series List',
                'meta_keywords' => 'view,series,tv,show,description,details',
                'meta_description' => 'View Series List',
            ]);

            return view('series.viewserieslist', $this->viewData);
        }
    }

    /**
     * Show trending TV shows (top 15 most downloaded in last 48 hours)
     *
     * @throws \Exception
     */
    public function showTrending(Request $request)
    {
        // Cache key for trending TV shows (48 hours)
        $cacheKey = 'trending_tv_top_15_48h';

        // Get trending TV shows from cache or calculate (refresh every hour)
        $trendingShows = \Illuminate\Support\Facades\Cache::remember($cacheKey, 3600, function () {
            // Calculate timestamp for 48 hours ago
            $fortyEightHoursAgo = \Illuminate\Support\Carbon::now()->subHours(48);

            // Get TV shows with their download counts from last 48 hours
            // Join with user_downloads to get actual download timestamps
            $query = \Illuminate\Support\Facades\DB::table('videos as v')
                ->join('tv_info as ti', 'v.id', '=', 'ti.videos_id')
                ->join('releases as r', 'v.id', '=', 'r.videos_id')
                ->leftJoin('user_downloads as ud', 'r.id', '=', 'ud.releases_id')
                ->select([
                    'v.id',
                    'v.title',
                    'v.started',
                    'v.tvdb',
                    'v.tvmaze',
                    'v.trakt',
                    'v.tmdb',
                    'v.countries_id',
                    'ti.summary',
                    'ti.image',
                    \Illuminate\Support\Facades\DB::raw('COUNT(DISTINCT ud.id) as total_downloads'),
                    \Illuminate\Support\Facades\DB::raw('COUNT(DISTINCT r.id) as release_count'),
                ])
                ->where('v.type', 0) // 0 = TV
                ->where('v.title', '!=', '')
                ->where('ud.timestamp', '>=', $fortyEightHoursAgo)
                ->groupBy('v.id', 'v.title', 'v.started', 'v.tvdb', 'v.tvmaze', 'v.trakt', 'v.tmdb', 'v.countries_id', 'ti.summary', 'ti.image')
                ->havingRaw('COUNT(DISTINCT ud.id) > 0')
                ->orderByDesc('total_downloads')
                ->limit(15)
                ->get();

            return $query;
        });

        $this->viewData = array_merge($this->viewData, [
            'trendingShows' => $trendingShows,
            'meta_title' => 'Trending TV Shows - Last 48 Hours',
            'meta_keywords' => 'trending,tv,shows,series,popular,downloads,recent',
            'meta_description' => 'Browse the most popular and downloaded TV shows in the last 48 hours',
        ]);

        return view('series.trending', $this->viewData);
    }
}
