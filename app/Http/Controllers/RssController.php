<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Category;
use Blacklight\http\RSS;
use App\Models\UserRequest;
use Illuminate\Http\Request;
use Blacklight\utility\Utility;
use Illuminate\Support\Facades\Auth;

class RssController extends BasePageController
{
    /**
     * @param \Illuminate\Http\Request $request
     * @throws \Exception
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
            $meta_description = 'View information about Newznab Tmux RSS Feeds.';

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
            $rssToken = $uid = -1;
            // User requested a feed, ensure either logged in or passing a valid token.
            if (Auth::check()) {
                $uid = Auth::id();
                $rssToken = $this->userdata['rsstoken'];
                $maxRequests = $this->userdata->role->apirequests;
            } else {
                if (! $request->has('i') || ! $request->has('r')) {
                    Utility::showApiError(100, 'Both the User ID and API key are required for viewing the RSS!');
                }

                $res = User::getByIdAndRssToken($request->input('i'), $request->input('r'));

                if (! $res) {
                    Utility::showApiError(100);
                }

                $uid = $res['id'];
                $rssToken = $res['rsstoken'];
                $maxRequests = $res->role->apirequests;
                $username = $res['username'];

                if (User::isDisabled($username)) {
                    Utility::showApiError(101);
                }
            }

            if (UserRequest::getApiRequests($uid) > $maxRequests) {
                Utility::showApiError(500, 'You have reached your daily limit for API requests!');
            } else {
                UserRequest::addApiRequest($uid, $request->getRequestUri());
            }

            // Valid or logged in user, get them the requested feed.
            $userShow = $userAnidb = -1;
            if ($request->has('show')) {
                $userShow = ((int) $request->input('show') === 0 ? -1 : $request->input('show') + 0);
            } elseif ($request->has('anidb')) {
                $userAnidb = ((int) $request->input('anidb') === 0 ? -1 : $request->input('anidb') + 0);
            }

            $outputXML = (! ($request->has('o') && $request->input('o') === 'json'));

            $userCat = ($request->has('t') ? ((int) $request->input('t') === 0 ? -1 : $request->input('t')) : -1);
            $userNum = ($request->has('num') && is_numeric($request->input('num')) ? abs($request->input('num')) : 100);
            $userAirDate = $request->has('airdate') && is_numeric($request->input('airdate')) ? abs($request->input('airdate')) : -1;

            $params =
                [
                    'dl'       => $request->has('dl') && $request->input('dl') === '1' ? '1' : '0',
                    'del'      => $request->has('del') && $request->input('del') === '1' ? '1' : '0',
                    'extended' => 1,
                    'uid'      => $uid,
                    'token'    => $rssToken,
                ];

            if ((int) $userCat === -3) {
                $relData = $rss->getShowsRss($userNum, $uid, User::getCategoryExclusion($uid), $userAirDate);
            } elseif ((int) $userCat === -4) {
                $relData = $rss->getMyMoviesRss($userNum, $uid, User::getCategoryExclusion($uid));
            } else {
                $relData = $rss->getRss(explode(',', $userCat), $userNum, $userShow, $userAnidb, $uid, $userAirDate);
            }

            $rss->output($relData, $params, $outputXML, $offset, 'rss');
        }
    }
}
