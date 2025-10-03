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
        $this->setPreferences();
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
}
