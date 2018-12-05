<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Release;
use Illuminate\Http\Request;

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
        if (! $request->has('userid') || ! $request->has('api_token')) {
            return response('Bad request, please supply all parameters!', 400)->withHeaders(['X-DNZB-RCode' => 400, 'X-DNZB-RText' => 'Bad request, please supply all parameters!']);
        }

        $res = User::getByIdAndRssToken($request->input('userid'), $request->input('api_token'));
        if ($res === null) {
            return response('Unauthorised, wrong user ID or rss key!', 401)->withHeaders(['X-DNZB-RCode' => 401, 'X-DNZB-RText' => 'Unauthorised, wrong user ID or rss key!']);
        }

        $uid = $res['id'];
        $rssToken = $res['api_token'];

        if (isset($uid, $rssToken) && $request->has('guid')) {
            $alt = Release::getAlternate($request->input('guid'), $uid);

            if (empty($alt)) {
                return response('No NZB found for alternate match!', 404)->withHeaders(['X-DNZB-RCode' => 404, 'X-DNZB-RText' => 'No NZB found for alternate match.']);
            }

            return response('Success', 200)->withHeaders(['Location' => url('/').'/getnzb?id='.$alt['guid'].'&i='.$uid.'&r='.$rssToken]);
        }

        return response('Bad request, please supply all parameters!', 400)->withHeaders(['X-DNZB-RCode' => 400, 'X-DNZB-RText' => 'Bad request, please supply all parameters!']);
    }
}
