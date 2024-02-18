<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\RSS;
use App\Models\Category;
use App\Models\User;
use App\Models\UserDownload;
use App\Models\UserRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

class RssController extends BasePageController
{
    /**
     * @return JsonResponse|void
     *
     * @throws \Throwable
     */
    public function myMoviesRss(Request $request)
    {
        $rss = new RSS(['Settings' => $this->settings]);
        $offset = 0;

        $user = $this->userCheck($request);

        if (is_object($user)) {
            return $user;
        }

        $outputXML = (! ($request->has('o') && $request->input('o') === 'json'));

        $userNum = ($request->has('num') && is_numeric($request->input('num')) ? abs($request->input('num')) : 0);

        $relData = $rss->getMyMoviesRss($userNum, $user['user_id'], User::getCategoryExclusionById($user['user_id']));

        $rss->output($relData, $user['params'], $outputXML, $offset, 'rss');
    }

    /**
     * @return JsonResponse|void
     *
     * @throws \Throwable
     */
    public function myShowsRss(Request $request)
    {
        $rss = new RSS();
        $offset = 0;
        $user = $this->userCheck($request);
        if (is_object($user)) {
            return $user;
        }
        $userAirDate = $request->has('airdate') && is_numeric($request->input('airdate')) ? abs($request->input('airdate')) : -1;
        $userNum = ($request->has('num') && is_numeric($request->input('num')) ? abs($request->input('num')) : 0);
        $relData = $rss->getShowsRss($userNum, $user['user_id'], User::getCategoryExclusionById($user['user_id']), $userAirDate);
        $outputXML = (! ($request->has('o') && $request->input('o') === 'json'));

        $rss->output($relData, $user['params'], $outputXML, $offset, 'rss');
    }

    /**
     * @return JsonResponse|void
     *
     * @throws \Throwable
     */
    public function fullFeedRss(Request $request)
    {
        $rss = new RSS();
        $offset = 0;
        $user = $this->userCheck($request);
        if (is_object($user)) {
            return $user;
        }
        $userAirDate = $request->has('airdate') && is_numeric($request->input('airdate')) ? abs($request->input('airdate')) : -1;
        $userNum = ($request->has('num') && is_numeric($request->input('num')) ? abs($request->input('num')) : 0);
        $userLimit = $request->has('limit') && is_numeric($request->input('limit')) ? $request->input('limit') : 100;
        $userShow = $userAnidb = -1;
        if ($request->has('show')) {
            $userShow = ((int) $request->input('show') === 0 ? -1 : $request->input('show') + 0);
        } elseif ($request->has('anidb')) {
            $userAnidb = ((int) $request->input('anidb') === 0 ? -1 : $request->input('anidb') + 0);
        }
        $outputXML = (! ($request->has('o') && $request->input('o') === 'json'));
        $relData = $rss->getRss(Arr::wrap(0), $userShow, $userAnidb, $user['user_id'], $userAirDate, $userLimit, $userNum);
        $rss->output($relData, $user['params'], $outputXML, $offset, 'rss');
    }

    /**
     * @throws \Exception
     */
    public function showRssDesc(): void
    {
        $this->setPreferences();
        $rss = new RSS();

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

        $catExclusions = $this->userdata->categoryexclusions ?? [];

        $this->smarty->assign(
            [
                'categorylist' => Category::getCategories(true, $catExclusions),
                'parentcategorylist' => Category::getForMenu($catExclusions),
            ]
        );

        $content = $this->smarty->fetch('rssdesc.tpl');
        $this->smarty->assign(
            compact('content', 'title', 'meta_title', 'meta_keywords', 'meta_description')
        );
        $this->pagerender();
    }

    /**
     * @throws \Throwable
     */
    public function cartRss(Request $request): JsonResponse|array
    {
        $rss = new RSS();
        $offset = 0;
        $user = $this->userCheck($request);
        if (is_object($user)) {
            return $user;
        }
        $outputXML = (! ($request->has('o') && $request->input('o') === 'json'));
        $userAirDate = $request->has('airdate') && is_numeric($request->input('airdate')) ? abs($request->input('airdate')) : -1;
        $userNum = ($request->has('num') && is_numeric($request->input('num')) ? abs($request->input('num')) : 0);
        $userLimit = $request->has('limit') && is_numeric($request->input('limit')) ? $request->input('limit') : 100;
        $userShow = $userAnidb = -1;
        if ($request->has('show')) {
            $userShow = ((int) $request->input('show') === 0 ? -1 : $request->input('show') + 0);
        } elseif ($request->has('anidb')) {
            $userAnidb = ((int) $request->input('anidb') === 0 ? -1 : $request->input('anidb') + 0);
        }

        $relData = $rss->getRss([-2], $userShow, $userAnidb, $user['user_id'], $userAirDate, $userLimit, $userNum);
        $rss->output($relData, $user['params'], $outputXML, $offset, 'rss');
    }

    /**
     * @throws \Throwable
     */
    public function categoryFeedRss(Request $request): JsonResponse|array
    {
        $this->setPreferences();
        $rss = new RSS();
        $offset = 0;
        if ($request->missing('id')) {
            return response()->json(['error' => 'Category ID is missing'], '403');
        }

        $user = $this->userCheck($request);
        if (is_object($user)) {
            return $user;
        }
        $categoryId = explode(',', $request->input('id'));
        $userAirDate = $request->has('airdate') && is_numeric($request->input('airdate')) ? abs($request->input('airdate')) : -1;
        $userNum = ($request->has('num') && is_numeric($request->input('num')) ? abs($request->input('num')) : 0);
        $userLimit = $request->has('limit') && is_numeric($request->input('limit')) ? $request->input('limit') : 100;
        $userShow = $userAnidb = -1;
        if ($request->has('show')) {
            $userShow = ((int) $request->input('show') === 0 ? -1 : $request->input('show') + 0);
        } elseif ($request->has('anidb')) {
            $userAnidb = ((int) $request->input('anidb') === 0 ? -1 : $request->input('anidb') + 0);
        }
        $outputXML = (! ($request->has('o') && $request->input('o') === 'json'));
        $relData = $rss->getRss($categoryId, $userShow, $userAnidb, $user['user_id'], $userAirDate, $userLimit, $userNum);
        $rss->output($relData, $user['params'], $outputXML, $offset, 'rss');
    }

    /**
     * @throws \Throwable
     */
    private function userCheck(Request $request): JsonResponse|array
    {
        if ($request->missing('api_token')) {
            return response()->json(['error' => 'API key is required for viewing the RSS!'], 403);
        }

        $res = User::getByRssToken($request->input('api_token'));

        if ($res === null) {
            return response()->json(['error' => 'Invalid RSS token'], 403);
        }

        $uid = $res['id'];
        $rssToken = $res['api_token'];
        $maxRequests = $res->role->apirequests;
        $maxDownloads = $res->role->downloadrequests;
        $usedRequests = UserRequest::getApiRequests($uid);
        $time = UserRequest::whereUsersId($uid)->min('timestamp');
        $apiOldestTime = $time !== null ? Carbon::createFromTimeString($time)->toRfc2822String() : '';
        $grabTime = UserDownload::whereUsersId($uid)->min('timestamp');
        $oldestGrabTime = $grabTime !== null ? Carbon::createFromTimeString($grabTime)->toRfc2822String() : '';

        if ($res->hasRole('Disabled')) {
            return response()->json(['error' => 'Your account is disabled'], 403);
        }

        if ($usedRequests > $maxRequests) {
            return response()->json(['error' => 'You have reached your daily limit for API requests!'], 403);
        } else {
            UserRequest::addApiRequest($rssToken, $request->getRequestUri());
        }
        $params =
            [
                'dl' => $request->has('dl') && $request->input('dl') === '1' ? '1' : '0',
                'del' => $request->has('del') && $request->input('del') === '1' ? '1' : '0',
                'extended' => 1,
                'uid' => $uid,
                'token' => $rssToken,
                'apilimit' => $maxRequests,
                'requests' => $usedRequests,
                'downloadlimit' => $maxDownloads,
                'grabs' => UserDownload::getDownloadRequests($uid),
                'oldestapi' => $apiOldestTime,
                'oldestgrab' => $oldestGrabTime,
            ];

        return ['user' => $res, 'user_id' => $uid, 'rss_token' => $rssToken, 'max_requests' => $maxRequests, 'params' => $params];
    }
}
