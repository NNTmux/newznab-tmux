<?php

namespace App\Http\Controllers;

use App\Models\DnzbFailure;
use App\Models\Predb;
use App\Models\Release;
use App\Models\ReleaseComment;
use App\Models\ReleaseFile;
use App\Models\ReleaseNfo;
use App\Models\ReleaseRegex;
use App\Models\Settings;
use App\Models\UserDownload;
use App\Models\Video;
use App\Models\XxxInfo;
use App\Services\MovieService;
use App\Services\Releases\ReleaseSearchService;
use App\Services\BookService;
use App\Services\AnidbService;
use App\Services\XxxBrowseService;
use Blacklight\Console;
use Blacklight\Games;
use Blacklight\Music;
use Blacklight\ReleaseExtra;
use Illuminate\Http\Request;

class DetailsController extends BasePageController
{
    private ReleaseSearchService $releaseSearchService;

    private MovieService $movieService;

    private XxxBrowseService $xxxBrowseService;

    public function __construct(ReleaseSearchService $releaseSearchService, MovieService $movieService, XxxBrowseService $xxxBrowseService)
    {
        parent::__construct();
        $this->releaseSearchService = $releaseSearchService;
        $this->movieService = $movieService;
        $this->xxxBrowseService = $xxxBrowseService;
    }

    public function show(Request $request, string $guid)
    {
        if ($guid !== null) {
            $re = new ReleaseExtra;
            $data = Release::getByGuid($guid);
            $releaseRegex = '';
            if (! empty($data)) {
                $releaseRegex = ReleaseRegex::query()->where('releases_id', '=', $data['id'])->first();
            }

            if (! $data) {
                return redirect()->back();
            }

            if ($this->isPostBack($request)) {
                ReleaseComment::addComment($data['id'], $data['gid'], $request->input('txtAddComment'), $this->userdata->id, $request->ip());

                return redirect()->route('details', ['guid' => $guid])->with('success', 'Comment posted successfully!');
            }

            $nfoData = ReleaseNfo::getReleaseNfo($data['id']);
            $nfo = $nfoData ? $nfoData->nfo : null;
            $reVideo = $re->getVideo($data['id']);
            $reAudio = $re->getAudio($data['id']);
            $reSubs = $re->getSubs($data['id']);
            $comments = ReleaseComment::getComments($data['id']);
            $similars = $this->releaseSearchService->searchSimilar($data['id'], $data['searchname'], $this->userdata->categoryexclusions);
            $failed = DnzbFailure::getFailedCount($data['id']);
            $downloadedBy = UserDownload::query()->with('user')->where('releases_id', $data['id'])->get(['users_id']);

            $showInfo = '';
            if ($data['videos_id'] > 0) {
                $showInfo = Video::getByVideoID($data['videos_id']);
            }

            $mov = '';
            if ($data['imdbid'] !== '' && $data['imdbid'] !== 0000000) {
                $mov = $this->movieService->getMovieInfo($data['imdbid']);
                if (! empty($mov['title'])) {
                    $mov['title'] = str_replace(['/', '\\'], '', $mov['title']);
                    $mov['actors'] = makeFieldLinks($mov, 'actors', 'movies');
                    $mov['genre'] = makeFieldLinks($mov, 'genre', 'movies');
                    $mov['director'] = makeFieldLinks($mov, 'director', 'movies');
                    if (Settings::settingValue('trailers_display')) {
                        $trailer = empty($mov['trailer']) || $mov['trailer'] === '' ? $this->movieService->getTrailer($data['imdbid']) : $mov['trailer'];
                        if ($trailer) {
                            $mov['trailer'] = sprintf('<iframe width="%d" height="%d" src="%s"></iframe>', Settings::settingValue('trailers_size_x'), Settings::settingValue('trailers_size_y'), $trailer);
                        }
                    }
                }
            }

            $xxx = '';
            if ($data['xxxinfo_id'] !== '' && $data['xxxinfo_id'] !== 0) {
                $xxx = XxxInfo::getXXXInfo($data['xxxinfo_id']);

                if (isset($xxx['trailers'])) {
                    $xxx['trailers'] = $this->xxxBrowseService->insertSwf($xxx['classused'], $xxx['trailers']);
                }

                if ($xxx && isset($xxx['title'])) {
                    $xxx['title'] = str_replace(['/', '\\'], '', $xxx['title']);
                    $xxx['actors'] = makeFieldLinks($xxx, 'actors', 'xxx');
                    $xxx['genre'] = makeFieldLinks($xxx, 'genre', 'xxx');
                    $xxx['director'] = makeFieldLinks($xxx, 'director', 'xxx');
                } else {
                    $xxx = false;
                }
            }

            $game = '';
            if ($data['gamesinfo_id'] !== '') {
                $game = (new Games)->getGamesInfoById($data['gamesinfo_id']);
            }

            $mus = '';
            if ($data['musicinfo_id'] !== '') {
                $mus = (new Music)->getMusicInfo($data['musicinfo_id']);
            }

            $book = '';
            if ($data['bookinfo_id'] !== '') {
                $book = (new BookService)->getBookInfo($data['bookinfo_id']);
            }

            $con = '';
            if ($data['consoleinfo_id'] !== '') {
                $con = (new Console)->getConsoleInfo($data['consoleinfo_id']);
            }

            $AniDBAPIArray = '';
            if ($data['anidbid'] > 0) {
                $AniDBAPIArray = (new AnidbService)->getAnimeInfo($data['anidbid']);

                // If we have anilist_id but missing details, fetch from AniList
                if ($AniDBAPIArray && !empty($AniDBAPIArray->anilist_id)) {
                    $anilistId = is_object($AniDBAPIArray) ? $AniDBAPIArray->anilist_id : ($AniDBAPIArray['anilist_id'] ?? null);
                    if ($anilistId && (empty($AniDBAPIArray->country) && empty($AniDBAPIArray->media_type))) {
                        // Fetch fresh data from AniList if country/media_type is missing
                        try {
                            $palist = new \App\Services\PopulateAniListService;
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

            $releasefiles = ReleaseFile::getReleaseFiles($data['id']);

            $this->viewData = array_merge($this->viewData, [
                'releasefiles' => $releasefiles,
                'release' => $data,
                'reVideo' => $reVideo,
                'reAudio' => $reAudio,
                'reSubs' => $reSubs,
                'nfo' => $nfo,
                'show' => $showInfo,
                'movie' => $mov,
                'xxx' => $xxx,
                'anidb' => $AniDBAPIArray,
                'music' => $mus,
                'con' => $con,
                'game' => $game,
                'book' => $book,
                'predb' => $pre,
                'comments' => $comments,
                'files' => $releasefiles,
                'searchname' => getSimilarName($data['searchname']),
                'similars' => $similars !== false ? $similars : [],
                'privateprofiles' => config('nntmux_settings.private_profiles'),
                'failed' => $failed,
                'regex' => $releaseRegex,
                'downloadedby' => $downloadedBy,
                'meta_title' => 'View NZB',
                'meta_keywords' => 'view,nzb,description,details',
                'meta_description' => 'View NZB for '.$data['searchname'],
            ]);

            return view('details.index', $this->viewData);
        }

        return redirect()->back()->with('error', 'Release not found');
    }
}
