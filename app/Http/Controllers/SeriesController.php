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
    public function index(Request $request, string $id = ''): void
    {
        $this->setPreferences();
        $releases = new Releases;
        $title = 'Series';
        $meta_title = 'View TV Series';
        $meta_keywords = 'view,series,tv,show,description,details';
        $meta_description = 'View TV Series';

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

            if (! $show) {
                $this->smarty->assign('nodata', 'No video information for this series.');
            } elseif (! $rel) {
                $this->smarty->assign('nodata', 'No releases for this series.');
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

                $this->smarty->assign('seasons', Arr::sortRecursive($series));
                $this->smarty->assign('show', $show);
                $this->smarty->assign('myshows', $myshows);

                //get series name(s), description, country and genre
                $seriestitles = $seriessummary = $seriescountry = [];
                $seriestitles[] = $show['title'];

                if (! empty($show['summary'])) {
                    $seriessummary[] = $show['summary'];
                }

                if (! empty($show['countries_id'])) {
                    $seriescountry[] = $show['countries_id'];
                }

                $seriestitles = implode('/', array_map('trim', $seriestitles));
                $this->smarty->assign('seriestitles', $seriestitles);
                $this->smarty->assign('seriessummary', $seriessummary ? array_shift($seriessummary) : '');
                $this->smarty->assign('seriescountry', $seriescountry ? array_shift($seriescountry) : '');

                $title = 'Series';
                $meta_title = 'View TV Series';
                $meta_keywords = 'view,series,tv,show,description,details';
                $meta_description = 'View TV Series';

                if ($category !== -1) {
                    $catid = $category;
                } else {
                    $catid = '';
                }
                $this->smarty->assign('category', $catid);
                $this->smarty->assign('nodata', '');
            }
            $content = $this->smarty->fetch('viewseries.tpl');
            $this->smarty->assign([
                'title' => $title,
                'content' => $content,
                'meta_title' => $meta_title,
                'meta_keywords' => $meta_keywords,
                'meta_description' => $meta_description,
            ]);
            $this->pagerender();
        } else {
            $letter = ($id && preg_match('/^(0\-9|[A-Z])$/i', $id)) ? $id : '0-9';

            $showname = ($request->has('title') && ! empty($request->input('title'))) ? $request->input('title') : '';

            if ($showname !== '' && ! $id) {
                $letter = '';
            }

            $masterserieslist = Video::getSeriesList($this->userdata->id, $letter, $showname);

            $title = 'Series List';
            $meta_title = 'View Series List';
            $meta_keywords = 'view,series,tv,show,description,details';
            $meta_description = 'View Series List';

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

            $this->smarty->assign('serieslist', $serieslist);
            $this->smarty->assign('seriesrange', range('A', 'Z'));
            $this->smarty->assign('seriesletter', $letter);
            $this->smarty->assign('showname', $showname);

            $content = $this->smarty->fetch('viewserieslist.tpl');

            $this->smarty->assign(compact('title', 'content', 'meta_title', 'meta_keywords', 'meta_description'));
            $this->pagerender();
        }
    }
}
