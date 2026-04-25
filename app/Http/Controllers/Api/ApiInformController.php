<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Release;
use App\Models\ReleaseInform;
use App\Models\User;
use App\Services\NameFixing\ReleaseUpdateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiInformController extends Controller
{
    /**
     * http://sitename/api/inform/release?api_token=xxxxx&relo=xxx&relp=xxx.
     *
     *
     * @throws \Exception
     */
    public function release(Request $request): JsonResponse
    {
        $releaseObName = $request->has('relo') && ! empty($request->input('relo')) ? $request->input('relo') : '';
        $releasePrName = $request->has('relp') && ! empty($request->input('relp')) ? $request->input('relp') : '';
        $apiToken = $request->has('api_token') && ! empty($request->input('api_token')) ? $request->input('api_token') : '';
        $user = User::findVerifiedByApiToken((string) $request->input('api_token'));
        if (! $user) {
            return apiJsonError(100);
        }

        if ($user->is_disabled || $user->hasRole('Disabled')) {
            return apiJsonError(101);
        }

        if (! empty($releaseObName) && ! empty($releasePrName) && ! empty($apiToken)) {
            ReleaseInform::insertOrIgnore(['relOName' => $releaseObName, 'relPName' => $releasePrName, 'api_token' => $apiToken, 'created_at' => now(), 'updated_at' => now()]);
            $release = Release::whereSearchname($releaseObName)->first();
            if (! empty($release)) {
                (new ReleaseUpdateService)->updateRelease($release, $releasePrName, 'Release Inform API', true, 'Filenames, ', true, true);
            }

            return response()->json(['message' => 'Release Information Added!'], 200);
        }

        return response()->json(['message' => 'Indexer inform error, wrong data supplied!'], 404);
    }
}
