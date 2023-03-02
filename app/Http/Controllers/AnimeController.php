<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Blacklight\AniDB;
use Blacklight\Releases;
use Illuminate\Http\Request;

class AnimeController extends BasePageController
{
    /**
     * @var \Blacklight\AniDB
     */
    protected $aniDb;

    /**
     * @var \Blacklight\Releases
     */
    protected $releases;

    /**
     * AnimeController constructor.
     *
     * @throws \Exception
     */
    public function __construct()
    {
        parent::__construct();
        $this->aniDb = new AniDB();
        $this->releases = new Releases();
    }

    /**
     * @throws \Exception
     */
    public function showAnime(Request $request): void
    {
        $this->setPrefs();

        if ($request->has('id') && ctype_digit($request->input('id'))) {
            // force the category to TV_ANIME as it should be for anime, as $catarray was NULL and we know the category for sure for anime
            $aniDbReleases = $this->releases->animeSearch($request->input('id'), 0, 1000, '', [Category::TV_ANIME], -1);
            $aniDbInfo = $this->aniDb->getAnimeInfo($request->input('id'));
            $title = $aniDbInfo->title;
            $meta_title = 'View Anime '.$aniDbInfo->title;
            $meta_keywords = 'view,anime,anidb,description,details';
            $meta_description = 'View '.$aniDbInfo->title.' Anime';

            if (! $this->releases && ! $aniDbInfo) {
                $this->smarty->assign('nodata', 'No releases and AniDB info for this series.');
            } elseif (! $aniDbInfo) {
                $this->smarty->assign('nodata', 'No AniDB information for this series.');
            } elseif (! $aniDbReleases) {
                $this->smarty->assign('nodata', 'No releases for this series.');
            } else {
                $this->smarty->assign('animeEpisodeTitles', $aniDbReleases);
                $this->smarty->assign([
                    'animeAnidbid' => $aniDbInfo->anidbid,
                    'animeTitle' => $aniDbInfo->title,
                    'animeType' => $aniDbInfo->type,
                    'animePicture' => $aniDbInfo->picture,
                    'animeStartDate' => $aniDbInfo->startdate,
                    'animeEndDate' => $aniDbInfo->enddate,
                    'animeDescription' => $aniDbInfo->description,
                    'animeRating' => $aniDbInfo->rating,
                    'animeRelated' => $aniDbInfo->related,
                    'animeSimilar' => $aniDbInfo->similar,
                    'animeCategories' => $aniDbInfo->categories,
                    'animeCreators' => $aniDbInfo->creators,
                    'animeCharacters' => $aniDbInfo->characters,
                ]);

                $this->smarty->assign('nodata', '');
            }
            $content = $this->smarty->fetch('viewanime.tpl');
            $this->smarty->assign(compact('content', 'title', 'meta_title', 'meta_keywords', 'meta_description'));
            $this->pagerender();
        }
    }

    /**
     * @throws \Exception
     */
    public function showList(Request $request): void
    {
        $this->setPrefs();
        $letter = ($request->has('id') && preg_match('/^(0\-9|[A-Z])$/i', $request->input('id'))) ? $request->input('id') : '0-9';

        $animeTitle = ($request->has('title') && ! empty($request->input('title'))) ? $request->input('title') : '';

        if ($animeTitle !== '' && $request->missing('id')) {
            $letter = '';
        }

        $masterserieslist = $this->aniDb->getAnimeList($letter, $animeTitle);

        $title = 'Anime List';
        $meta_title = 'View Anime List';
        $meta_keywords = 'view,anime,series,description,details';
        $meta_description = 'View Anime List';

        $animelist = [];
        foreach ($masterserieslist as $s) {
            if (preg_match('/^[0-9]/', $s->title)) {
                $thisrange = '0-9';
            } else {
                preg_match('/([A-Z]).*/i', $s->title, $hits);
                $thisrange = strtoupper($hits[1]);
            }
            $animelist[$thisrange][] = $s;
        }
        ksort($animelist);

        $this->smarty->assign('animelist', $animelist);
        $this->smarty->assign('animerange', range('A', 'Z'));
        $this->smarty->assign('animeletter', $letter);
        $this->smarty->assign('animetitle', $animeTitle);
        $content = $this->smarty->fetch('viewanimelist.tpl');

        $this->smarty->assign(compact('content', 'title', 'meta_title', 'meta_keywords', 'meta_description'));
        $this->pagerender();
    }
}
