<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Country;
use App\Models\DnzbFailure;
use App\Models\Predb;
use App\Models\Release;
use App\Models\ReleaseComment;
use App\Models\ReleaseRegex;
use App\Models\ReleaseReport;
use App\Models\Video;
use App\Services\AnidbService;
use App\Services\BookService;
use App\Services\Configuration\ConfigurationProvider;
use App\Services\ConsoleService;
use App\Services\GamesService;
use App\Services\MovieService;
use App\Services\MusicService;
use App\Services\PopulateAniListService;
use App\Services\ReleaseExtraService;
use App\Services\Releases\ReleaseSearchService;
use App\Support\TrailerUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class DetailsController extends BasePageController
{
    /**
     * Matches the release_comments.text varchar(2000) column.
     */
    private const int MAX_COMMENT_LENGTH = 2000;

    private const int MAX_COMMENTS_PER_MINUTE = 5;

    private ReleaseSearchService $releaseSearchService;

    private MovieService $movieService;

    private ReleaseExtraService $releaseExtraService;

    public function __construct(
        ReleaseSearchService $releaseSearchService,
        MovieService $movieService,
        ReleaseExtraService $releaseExtraService
    ) {
        parent::__construct();
        $this->releaseSearchService = $releaseSearchService;
        $this->movieService = $movieService;
        $this->releaseExtraService = $releaseExtraService;
    }

    public function show(Request $request, string $guid): mixed
    {
        $data = Release::getByGuid($guid);
        $releaseRegex = '';
        if (! empty($data)) {
            $releaseRegex = ReleaseRegex::query()->where('releases_id', '=', $data['id'])->first();
        }

        if (! $data) {
            return redirect()->back();
        }

        if ($this->isPostBack($request)) {
            return $this->postComment($request, (int) $data['id'], $guid);
        }

        $reVideo = $this->releaseExtraService->getVideo($data['id']);
        $reAudio = $this->releaseExtraService->getAudio($data['id']);
        $reSubs = $this->releaseExtraService->getSubs($data['id']);
        $comments = ReleaseComment::getComments($data['id']);
        $similars = $this->releaseSearchService->searchSimilar($data['id'], $data['searchname'], (array) $this->userdata->categoryexclusions);
        $failed = DnzbFailure::getFailedCount($data['id']);
        $reportData = ReleaseReport::query()
            ->with('responder')
            ->where('releases_id', $data['id'])
            ->whereIn('status', ['pending', 'reviewed', 'resolved'])
            ->get();
        $reportCount = $reportData->count();
        $reportReasons = ReleaseReport::reasonKeysToLabels($reportData->pluck('reason')->unique()->implode(', '));
        $originalReportData = ReleaseReport::query()
            ->where('releases_id', $data['id'])
            ->orderByDesc('created_at')
            ->get();
        $totalReportCount = $originalReportData->count();
        $allReportReasons = ReleaseReport::reasonKeysToLabels($originalReportData->pluck('reason')->unique()->implode(', '));
        $publicReportResponses = ReleaseReport::query()
            ->with('responder')
            ->where('releases_id', $data['id'])
            ->where('response_is_public', true)
            ->whereNotNull('response')
            ->where('response', '!=', '')
            ->orderByDesc('responded_at')
            ->get();
        $showInfo = '';
        if ($data['videos_id'] > 0) {
            $showInfo = Video::getByVideoID($data['videos_id']);
        }

        $mov = '';
        if (imdb_id_is_valid($data['imdbid'])) {
            $mov = $this->movieService->getMovieInfo($data['imdbid']);
            if (! empty($mov['title'])) {
                $mov['title'] = str_replace(['/', '\\'], '', $mov['title']);
                if (! empty($mov['actors'])) {
                    $mov['actors'] = makeFieldLinks($mov, 'actors', 'movies');
                }
                if (! empty($mov['genre'])) {
                    $mov['genre'] = makeFieldLinks($mov, 'genre', 'movies');
                }
                if (! empty($mov['director'])) {
                    $mov['director'] = makeFieldLinks($mov, 'director', 'movies');
                }
                if (app(ConfigurationProvider::class)->site()->trailersDisplay) {
                    $trailer = empty($mov['trailer']) ? $this->movieService->getTrailer($data['imdbid']) : $mov['trailer'];
                    $mov['trailer_url'] = TrailerUrl::watchUrl($trailer);
                }
            }
        }

        $game = '';
        if ((int) $data['gamesinfo_id'] > 0) {
            $game = (new GamesService)->getGamesInfoById($data['gamesinfo_id']);
        }

        $mus = '';
        if ((int) $data['musicinfo_id'] > 0) {
            $mus = (new MusicService)->getMusicInfo($data['musicinfo_id']);
        }

        $book = '';
        if ((int) $data['bookinfo_id'] > 0) {
            $book = (new BookService)->getBookInfo($data['bookinfo_id']);
        }

        $con = '';
        if ((int) $data['consoleinfo_id'] > 0) {
            $con = (new ConsoleService)->getConsoleInfo($data['consoleinfo_id']);
        }

        $AniDBAPIArray = '';
        if ($data['anidbid'] > 0) {
            $AniDBAPIArray = (new AnidbService)->getAnimeInfo($data['anidbid']);

            // If we have anilist_id but missing details, fetch from AniList
            if ($AniDBAPIArray && ! empty($AniDBAPIArray->anilist_id)) {
                $anilistId = $AniDBAPIArray->anilist_id;
                if (empty($AniDBAPIArray->country) && empty($AniDBAPIArray->media_type)) {
                    // Fetch fresh data from AniList if country/media_type is missing
                    try {
                        $palist = new PopulateAniListService;
                        $palist->populateTable('info', $anilistId);
                        // Refresh the data
                        $AniDBAPIArray = (new AnidbService)->getAnimeInfo($data['anidbid']);
                    } catch (\Exception $e) {
                        // Silently fail, use existing data
                    }
                }
            }
        }

        $pre = Predb::getForRelease($data['predb_id']);

        // Resolve AniDB country code to a Country model/name here so the view stays query-free
        $anidbCountryCode = null;
        if (is_object($AniDBAPIArray)) {
            $anidbCountryCode = $AniDBAPIArray->country ?? null;
        } elseif (is_array($AniDBAPIArray)) {
            $anidbCountryCode = $AniDBAPIArray['country'] ?? null;
        }
        $anidbCountryModel = null;
        $anidbCountryName = null;
        if (! empty($anidbCountryCode)) {
            $anidbCountryModel = Country::query()->find($anidbCountryCode);
            $anidbCountryName = $anidbCountryModel->name ?? $anidbCountryCode;
        }

        $this->viewData = array_merge($this->viewData, [
            'release' => $data,
            'reVideo' => $reVideo,
            'reAudio' => $reAudio,
            'reSubs' => $reSubs,
            'show' => $showInfo,
            'movie' => $mov,
            'anidb' => $AniDBAPIArray,
            'anidbCountryModel' => $anidbCountryModel,
            'anidbCountryName' => $anidbCountryName,
            'music' => $mus,
            'con' => $con,
            'game' => $game,
            'book' => $book,
            'predb' => $pre,
            'comments' => $comments,
            'searchname' => getSimilarName($data['searchname']),
            'similars' => $similars !== false ? $similars : [],
            'privateprofiles' => config('nntmux_settings.private_profiles'),
            'failed' => $failed,
            'reportCount' => $reportCount,
            'reportReasons' => $reportReasons,
            'originalReportData' => $originalReportData,
            'totalReportCount' => $totalReportCount,
            'allReportReasons' => $allReportReasons,
            'publicReportResponses' => $publicReportResponses,
            'regex' => $releaseRegex,
            'meta_title' => 'View NZB',
            'meta_keywords' => 'view,nzb,description,details',
            'meta_description' => 'View NZB for '.$data['searchname'],
        ]);

        return view('details.index', $this->viewData);
    }

    /**
     * Add a comment, honouring the user's posting rights, a length limit and a per-user rate limit.
     */
    private function postComment(Request $request, int $releaseId, string $guid): RedirectResponse
    {
        $detailsPage = redirect()->route('details', ['guid' => $guid]);

        if (! $this->userdata->can_post) {
            return $detailsPage->with('error', 'You are not allowed to post comments.');
        }

        $text = trim((string) $request->input('txtAddComment', ''));
        if ($text === '' || mb_strlen($text) > self::MAX_COMMENT_LENGTH) {
            return $detailsPage->with('error', 'Comments must be between 1 and '.self::MAX_COMMENT_LENGTH.' characters.');
        }

        $throttleKey = 'release-comment:'.$this->userdata->id;
        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_COMMENTS_PER_MINUTE)) {
            return $detailsPage->with('error', 'You are posting comments too quickly. Please wait a minute and try again.');
        }
        RateLimiter::hit($throttleKey, 60);

        ReleaseComment::addComment($releaseId, $text, (int) $this->userdata->id, $request->ip());

        return $detailsPage->with('success', 'Comment posted successfully!');
    }
}
