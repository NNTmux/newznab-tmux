<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Release;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FailedReleasesController extends BasePageController
{
    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return mixed
     * @throws \Exception
     */
    public function show(Request $request)
    {
        $this->setPrefs();
        // Page is accessible only by the rss token, or logged in users.
        if (Auth::check()) {
            $uid = Auth::id();
            $rssToken = $this->userdata['rsstoken'];
        } else {
            if (! $request->has('userid') || ! $request->has('rsstoken')) {
                return response('Error!', 400)->headers(['X-DNZB-RCode' => 400, 'X-DNZB-RText' => 'Bad request, please supply all parameters!']);
            }

            $res = User::getByIdAndRssToken($request->input('userid'), $request->input('rsstoken'));
            if ($res === null) {
                return response('Error!', 401)->headers(['X-DNZB-RCode' => 401, 'X-DNZB-RText' => 'Unauthorised, wrong user ID or rss key!']);
            }

            $uid = $res['id'];
            $rssToken = $res['rsstoken'];
        }

        if (isset($uid, $rssToken) && is_numeric($uid) && $request->has('guid')) {
            $alt = Release::getAlternate($request->input('guid'), $uid);
            if ($alt === null) {
                return response('Error!', 404)->withHeaders(['X-DNZB-RCode' => 404, 'X-DNZB-RText' => 'No NZB found for alternate match.']);
            }

            return response('Success', 200)->header('Location:'.$this->serverurl.'getnzb?id='.$alt['guid'].'&i='.$uid.'&r='.$rssToken);
        }
    }
}
