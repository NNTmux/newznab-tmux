<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Events\UserAccessedApi;
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
    private RSS $rss;

    public function __construct(RSS $rss)
    {
        parent::__construct();
        $this->rss = $rss;
    }

    /**
     * @return JsonResponse|void
     *
     * @throws \Throwable
     */
    public function myMoviesRss(Request $request)
    {
        $user = $this->userCheck($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $outputXML = ! ($request->has('o') && $request->input('o') === 'json');
        $userNum = $request->has('num') && is_numeric($request->input('num')) ? abs((int) $request->input('num')) : 0;

        $relData = $this->rss->getMyMoviesRss($userNum, $user['user_id'], User::getCategoryExclusionById($user['user_id']));
        $this->rss->output($relData, $user['params'], $outputXML, 0, 'rss');
    }

    /**
     * @return JsonResponse|void
     *
     * @throws \Throwable
     */
    public function myShowsRss(Request $request)
    {
        $user = $this->userCheck($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $userAirDate = $request->has('airdate') && is_numeric($request->input('airdate')) ? abs((int) $request->input('airdate')) : -1;
        $userNum = $request->has('num') && is_numeric($request->input('num')) ? abs((int) $request->input('num')) : 0;
        $outputXML = ! ($request->has('o') && $request->input('o') === 'json');

        $relData = $this->rss->getShowsRss($userNum, $user['user_id'], User::getCategoryExclusionById($user['user_id']), $userAirDate);
        $this->rss->output($relData, $user['params'], $outputXML, 0, 'rss');
    }

    /**
     * @return JsonResponse|void
     *
     * @throws \Throwable
     */
    public function fullFeedRss(Request $request)
    {
        $user = $this->userCheck($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        [$userShow, $userAnidb, $userAirDate, $userNum, $userLimit, $outputXML] = $this->parseCommonRssParams($request);

        $relData = $this->rss->getRss(Arr::wrap(0), $userShow, $userAnidb, $user['user_id'], $userAirDate, $userLimit, $userNum);
        $this->rss->output($relData, $user['params'], $outputXML, 0, 'rss');
    }

    /**
     * @throws \Exception
     */
    public function showRssDesc(): mixed
    {
        $firstShow = $this->rss->getFirstInstance('videos_id', 'releases', 'id');
        $firstAni = $this->rss->getFirstInstance('anidbid', 'releases', 'id');

        $show = ($firstShow !== null) ? $firstShow->videos_id : 1;
        $anidb = ($firstAni !== null) ? $firstAni->anidbid : 1;

        $catExclusions = $this->userdata->categoryexclusions ?? [];

        $this->viewData = array_merge($this->viewData, [
            'show' => $show,
            'anidb' => $anidb,
            'categorylist' => Category::getCategories(true, $catExclusions),
            'parentcategorylist' => Category::getForMenu($catExclusions),
            'title' => 'Rss Info',
            'meta_title' => 'Rss Nzb Info',
            'meta_keywords' => 'view,nzb,description,details,rss,atom',
            'meta_description' => 'View information about NNTmux RSS Feeds.',
        ]);

        return view('rss.rssdesc', $this->viewData);
    }

    /**
     * @return JsonResponse|void
     *
     * @throws \Throwable
     */
    public function cartRss(Request $request)
    {
        $user = $this->userCheck($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        [$userShow, $userAnidb, $userAirDate, $userNum, $userLimit, $outputXML] = $this->parseCommonRssParams($request);

        $relData = $this->rss->getRss([-2], $userShow, $userAnidb, $user['user_id'], $userAirDate, $userLimit, $userNum);
        $this->rss->output($relData, $user['params'], $outputXML, 0, 'rss');
    }

    /**
     * @return JsonResponse|void
     *
     * @throws \Throwable
     */
    public function categoryFeedRss(Request $request)
    {
        if ($request->missing('id')) {
            return response()->json(['error' => 'Category ID is missing'], 403);
        }

        $user = $this->userCheck($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $categoryId = explode(',', $request->input('id'));
        [$userShow, $userAnidb, $userAirDate, $userNum, $userLimit, $outputXML] = $this->parseCommonRssParams($request);

        $relData = $this->rss->getRss($categoryId, $userShow, $userAnidb, $user['user_id'], $userAirDate, $userLimit, $userNum);
        $this->rss->output($relData, $user['params'], $outputXML, 0, 'rss');
    }

    /**
     * @return JsonResponse|void
     *
     * @throws \Throwable
     */
    public function trendingMoviesRss(Request $request)
    {
        $user = $this->userCheck($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $outputXML = ! ($request->has('o') && $request->input('o') === 'json');
        $relData = $this->rss->getTrendingMoviesRss();
        $this->rss->output($relData, $user['params'], $outputXML, 0, 'rss');
    }

    /**
     * @return JsonResponse|void
     *
     * @throws \Throwable
     */
    public function trendingShowsRss(Request $request)
    {
        $user = $this->userCheck($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $outputXML = ! ($request->has('o') && $request->input('o') === 'json');
        $relData = $this->rss->getTrendingShowsRss();
        $this->rss->output($relData, $user['params'], $outputXML, 0, 'rss');
    }

    /**
     * Parse common RSS request parameters (show, anidb, airdate, num, limit, outputXML).
     *
     * @return array{int, int, int, int, int|string, bool}
     */
    private function parseCommonRssParams(Request $request): array
    {
        $userShow = $userAnidb = -1;
        if ($request->has('show')) {
            $userShow = (int) $request->input('show') === 0 ? -1 : $request->input('show') + 0;
        } elseif ($request->has('anidb')) {
            $userAnidb = (int) $request->input('anidb') === 0 ? -1 : $request->input('anidb') + 0;
        }

        $userAirDate = $request->has('airdate') && is_numeric($request->input('airdate')) ? abs((int) $request->input('airdate')) : -1;
        $userNum = $request->has('num') && is_numeric($request->input('num')) ? abs((int) $request->input('num')) : 0;
        $userLimit = $request->has('limit') && is_numeric($request->input('limit')) ? $request->input('limit') : 100;
        $outputXML = ! ($request->has('o') && $request->input('o') === 'json');

        return [$userShow, $userAnidb, $userAirDate, $userNum, $userLimit, $outputXML];
    }

    /**
     * @return JsonResponse|array<string, mixed>
     *
     * @throws \Throwable
     */
    private function userCheck(Request $request): JsonResponse|array
    {
        if ($request->missing('api_token')) {
            return response()->json(['error' => 'API key is required for viewing the RSS!'], 403);
        }

        $res = User::findVerifiedByApiToken((string) $request->input('api_token'));

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
        }

        UserRequest::addApiRequest($rssToken, $request->getRequestUri());
        event(new UserAccessedApi($res, $request->ip()));

        $params = [
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
