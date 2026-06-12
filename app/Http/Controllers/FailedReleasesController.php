<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Release;
use App\Models\User;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class FailedReleasesController extends BasePageController
{
    public function failed(Request $request): Application|Response|\Illuminate\Contracts\Foundation\Application|ResponseFactory
    {
         if ($request->missing('guid')) {
            return response('Bad request, please supply all parameters!', 400)->withHeaders(['X-DNZB-RCode' => 400, 'X-DNZB-RText' => 'Bad request, please supply all parameters!']);
        }

        $user = $this->resolveUser($request);
        if ($user === null) {
            return response('Unauthorised, wrong rss key!', 401)->withHeaders(['X-DNZB-RCode' => 401, 'X-DNZB-RText' => 'Unauthorised, wrong rss key!']);
        }

        $alt = Release::getAlternate($request->input('guid'), $user->id);

        if (empty($alt)) {
            return response('No NZB found for alternate match!', 404)->withHeaders(['X-DNZB-RCode' => 404, 'X-DNZB-RText' => 'No NZB found for alternate match.']);
        }

        $locationParams = ['id' => $alt['guid']];
        if ($request->filled('api_token')) {
            $locationParams['r'] = $user->api_token;
        }

        return response('Success')->withHeaders([
            'Location' => url('/getnzb').'?'.http_build_query($locationParams, '', '&', PHP_QUERY_RFC3986),
        ]);
    }

    private function resolveUser(Request $request): ?User
    {
        $sessionUser = $request->user();
        if ($sessionUser instanceof User) {
            return $sessionUser;
        }

        if ($request->filled('api_token')) {
            return User::findVerifiedByApiToken((string) $request->input('api_token'));
        }

        return null;
    }
}
