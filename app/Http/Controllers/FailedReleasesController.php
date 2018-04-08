<?php

namespace App\Http\Controllers;

use App\Models\Release;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FailedReleasesController extends BasePageController
{
    /**
     * @param \Illuminate\Http\Request $request
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
                header('X-DNZB-RCode: 400');
                header('X-DNZB-RText: Bad request, please supply all parameters!');
                $this->show403();
            } else {
                $res = User::getByIdAndRssToken($request->input('userid'), $request->input('rsstoken'));
            }
            if ($res === null) {
                header('X-DNZB-RCode: 401');
                header('X-DNZB-RText: Unauthorised, wrong user ID or rss key!');
                $this->show403();
            } else {
                $uid = $res['id'];
                $rssToken = $res['rsstoken'];
            }
        }

        if (isset($uid, $rssToken) && is_numeric($uid) && $request->has('guid')) {
            $alt = Release::getAlternate($request->input('guid'), $uid);
            if ($alt === null) {
                header('X-DNZB-RCode: 404');
                header('X-DNZB-RText: No NZB found for alternate match.');
                $this->show404();
            } else {
                header('Location: '.$this->serverurl.'getnzb?id='.$alt['guid'].'&i='.$uid.'&r='.$rssToken);
            }
        }
    }
}
