<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\User;
use App\Models\UserRequest;
use Blacklight\http\RSS;
use Blacklight\utility\Utility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RssController extends BasePageController
{
    /**
     * @param \Illuminate\Http\Request $request
     * @throws \Throwable
     */
    public function rss(Request $request)
    {
        $this->setPrefs();
        $rss = new RSS(['Settings' => $this->settings]);
        $offset = 0;

        // If no content id provided then show user the rss selection page.
        if (! $request->has('t') && ! $request->has('show') && ! $request->has('anidb')) {
            // User has to either be logged in, or using rsskey.

            $title = 'Rss Info';
            $meta_title = 'Rss Nzb Info';
            $meta_keywords = 'view,nzb,description,details,rss,atom';
            $meta_description = 'View information about NNTmux RSS Feeds.';

            $firstShow = $rss->getFirstInstance('videos_id', 'releases', 'id');
            $firstAni = $rss->getFirstInstance('anidbid', 'releases', 'id');

            if ($firstShow !== null) {
                $this->smarty->assign('show', $firstShow->videos_id);
            } else {
                $this->smarty->assign('show', 1);
            }

            if ($firstAni !== null) {
                $this->smarty->assign('anidb', $firstAni->anidbid);
            } else {
                $this->smarty->assign('anidb', 1);
            }

            $this->smarty->assign(
                [
                    'categorylist'       => Category::getCategories(true, $this->userdata['categoryexclusions']),
                    'parentcategorylist' => Category::getForMenu($this->userdata['categoryexclusions']),
                ]
            );

            $content = $this->smarty->fetch('rssdesc.tpl');
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
        } else {
            $uid = -1;
            // User requested a feed, ensure either logged in or passing a valid token.
            if (Auth::check()) {
                $uid = $this->userdata->id;
                $rssToken = $this->userdata['api_token'];
                $maxRequests = $this->userdata->role->apirequests;
            } else {
                if (! $request->has('r')) {
                    Utility::showApiError(100, 'API key is required for viewing the RSS!');
                }

                $res = User::getByRssToken($request->input('r'));

                if (! $res) {
                    Utility::showApiError(100);
                }

                $uid = $res['id'];
                $rssToken = $res['api_token'];
                $maxRequests = $res->role->apirequests;

                if ($res->hasRole('Disabled')) {
                    Utility::showApiError(101);
                }
            }

            if (UserRequest::getApiRequests($uid) > $maxRequests) {
                Utility::showApiError(500, 'You have reached your daily limit for API requests!');
            } else {
                UserRequest::addApiRequest($rssToken, $request->getRequestUri());
            }

            // Valid or logged in user, get them the requested feed.
            $userShow = $userAnidb = -1;
            if ($request->has('show')) {
                $userShow = ((int) $request->input('show') === 0 ? -1 : $request->input('show') + 0);
            } elseif ($request->has('anidb')) {
                $userAnidb = ((int) $request->input('anidb') === 0 ? -1 : $request->input('anidb') + 0);
            }

            $outputXML = (! ($request->has('o') && $request->input('o') === 'json'));

            $userCat = ($request->has('t') ? ((int) $request->input('t') === 0 ? -1 : (int) $request->input('t')) : -1);
            $userNum = ($request->has('num') && is_numeric($request->input('num')) ? abs($request->input('num')) : 0);
            $userLimit = $request->has('limit') && is_numeric($request->input('limit')) ? $request->input('limit') : 100;
            $userAirDate = $request->has('airdate') && is_numeric($request->input('airdate')) ? abs($request->input('airdate')) : -1;

            $params =
                [
                    'dl'       => $request->has('dl') && $request->input('dl') === '1' ? '1' : '0',
                    'del'      => $request->has('del') && $request->input('del') === '1' ? '1' : '0',
                    'extended' => 1,
                    'uid'      => $uid,
                    'token'    => $rssToken,
                ];

            if ($userCat === -3) {
                $relData = $rss->getShowsRss($userNum, $uid, User::getCategoryExclusionById($uid), $userAirDate);
            } elseif ($userCat === -4) {
                $relData = $rss->getMyMoviesRss($userNum, $uid, User::getCategoryExclusionById($uid));
            } else {
                $relData = $rss->getRss(explode(',', $userCat), $userShow, $userAnidb, $uid, $userAirDate, $userLimit, $userNum);
            }

            $rss->output($relData, $params, $outputXML, $offset, 'rss');
        }
    }
}
