<?php

namespace App\Http\Controllers;

use Blacklight\AniDB;
use App\Models\Category;
use Blacklight\Releases;
use Illuminate\Http\Request;

class AnimeController extends BasePageController
{
    public function __construct(Request $request)
    {
        parent::__construct($request);
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @throws \Exception
     */
    public function index(Request $request)
    {
        $this->setPrefs();
        $releases = new Releases();
        $aniDB = new AniDB();

        if ($request->has('id') && ctype_digit($request->input('id'))) {

            // force the category to TV_ANIME as it should be for anime, as $catarray was NULL and we know the category for sure for anime
            $aniDbReleases = $releases->animeSearch($request->input('id'), 0, 1000, '', [Category::TV_ANIME], -1);
            $aniDbInfo = $aniDB->getAnimeInfo($request->input('id'));

            if (! $releases && ! $aniDbInfo) {
                $this->show404();
            } elseif (! $aniDbInfo) {
                $this->smarty->assign('nodata', 'No AniDB information for this series.');
            } elseif (! $aniDbReleases) {
                $this->smarty->assign('nodata', 'No releases for this series.');
            } else {
                $this->smarty->assign('anidb', $aniDbInfo);
                $this->smarty->assign('animeEpisodeTitles', $aniDbReleases);
                $this->smarty->assign([
                        'animeAnidbid' => $aniDbInfo['anidbid'],
                        'animeTitle' => $aniDbInfo['title'],
                        'animeType' => $aniDbInfo['type'],
                        'animePicture' => $aniDbInfo['picture'],
                        'animeStartDate' => $aniDbInfo['startdate'],
                        'animeEndDate' => $aniDbInfo['enddate'],
                        'animeDescription' => $aniDbInfo['description'],
                        'animeRating' => $aniDbInfo['rating'],
                        'animeRelated' => $aniDbInfo['related'],
                        'animeSimilar' => $aniDbInfo['similar'],
                        'animeCategories' => $aniDbInfo['categories'],
                        'animeCreators' => $aniDbInfo['creators'],
                        'animeCharacters' => $aniDbInfo['characters'],
                    ]);

                $this->smarty->assign('nodata', '');

                $this->title = $aniDbInfo['title'];
                $this->meta_title = 'View Anime '.$aniDbInfo['title'];
                $this->meta_keywords = 'view,anime,anidb,description,details';
                $this->meta_description = 'View '.$aniDbInfo['title'].' Anime';
            }
            $this->content = $this->smarty->fetch('viewanime.tpl');
            $this->pagerender();
        } else {
            $letter = ($request->has('id') && preg_match('/^(0\-9|[A-Z])$/i', $request->input('id'))) ? $request->input('id') : '0-9';

            $animeTitle = ($request->has('title') && ! empty($request->input('title'))) ? $request->input('title') : '';

            if ($animeTitle !== '' && ! $request->has('id')) {
                $letter = '';
            }

            $masterserieslist = $aniDB->getAnimeList($letter, $animeTitle);

            $title = 'Anime List';
            $meta_title = 'View Anime List';
            $meta_keywords = 'view,anime,series,description,details';
            $meta_description = 'View Anime List';

            $animelist = [];
            if ($masterserieslist instanceof \Traversable) {
                foreach ($masterserieslist as $s) {
                    if (preg_match('/^[0-9]/', $s['title'])) {
                        $thisrange = '0-9';
                    } else {
                        preg_match('/([A-Z]).*/i', $s['title'], $matches);
                        $thisrange = strtoupper($matches[1]);
                    }
                    $animelist[$thisrange][] = $s;
                }
                ksort($animelist);
            }

            $this->smarty->assign('animelist', $animelist);
            $this->smarty->assign('animerange', range('A', 'Z'));
            $this->smarty->assign('animeletter', $letter);
            $this->smarty->assign('animetitle', $animeTitle);
            $content = $this->smarty->fetch('viewanimelist.tpl');

            $this->smarty->assign(
                [
                    'content' => $content,
                    'title' => $title,
                    'meta_title' => $meta_title,
                    'meta_keywords' => $meta_keywords,
                    'meta_description' => $meta_description,
                ]
            );
            $this->pagerender();
        }
    }
}
