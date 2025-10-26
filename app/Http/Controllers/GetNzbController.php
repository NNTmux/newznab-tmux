<?php

namespace App\Http\Controllers;

use App\Models\Release;
use App\Models\User;
use App\Models\UserDownload;
use App\Models\UsersRelease;
use Blacklight\NZB;
use Blacklight\utility\Utility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class GetNzbController extends BasePageController
{
    /**
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Foundation\Application|\Illuminate\Http\JsonResponse|\Illuminate\Http\Response|\STS\ZipStream\ZipStream|\Symfony\Component\HttpFoundation\StreamedResponse
     *
     * @throws \Exception
     */
    public function getNzb(Request $request, $guid = null)
    {

        // If guid is passed as URL parameter, merge it into request as 'id'
        if ($guid !== null && ! $request->has('id')) {
            $request->merge(['id' => $guid]);
        }

        // Page is accessible only by the rss token, or logged in users.
        if ($request->user()) {
            $uid = $this->userdata->id;
            $userName = $this->userdata->username;
            $maxDownloads = $this->userdata->role->downloadrequests;
            $rssToken = $this->userdata->api_token;
            if ($this->userdata->hasRole('Disabled')) {
                return Utility::showApiError(101);
            }
        } else {
            if ($request->missing('r')) {
                return Utility::showApiError(200);
            }

            $res = User::getByRssToken($request->input('r'));
            if (! $res) {
                return Utility::showApiError(100);
            }

            $uid = $res['id'];
            $userName = $res->username;
            $rssToken = $res['api_token'];
            $maxDownloads = $res->role->downloadrequests;
            if ($res->hasRole('Disabled')) {
                return Utility::showApiError(101);
            }
        }

        // Check download limit on user role.
        $requests = UserDownload::getDownloadRequests($uid);
        if ($requests > $maxDownloads) {
            return Utility::showApiError(501);
        }

        if (! $request->input('id')) {
            return Utility::showApiError(200, 'Parameter id is required');
        }

        // Remove any suffixed id with .nzb which is added to help weblogging programs see nzb traffic.
        $request->merge(['id' => str_ireplace('.nzb', '', $request->input('id'))]);

        // User requested a zip of guid,guid,guid releases.
        if ($request->has('zip') && $request->input('zip') === '1') {
            $guids = explode(',', $request->input('id'));
            if ($requests + \count($guids) > $maxDownloads) {
                return Utility::showApiError(501);
            }

            $zip = getStreamingZip($guids);
            if ($zip !== '') {
                User::incrementGrabs($uid, \count($guids));
                foreach ($guids as $guid) {
                    Release::updateGrab($guid);
                    UserDownload::addDownloadRequest($uid, $guid);

                    if ($request->has('del') && (int) $request->input('del') === 1) {
                        UsersRelease::delCartByUserAndRelease($guid, $uid);
                    }
                }

                Log::channel('zipped')->info('User '.$userName.' downloaded zipped files from site with IP: '.$request->ip());

                return $zip;
            }

            return response()->json(['message' => 'Unable to create .zip file'], 404);
        }

        $nzbPath = (new NZB)->getNZBPath($request->input('id'));

        if (! File::exists($nzbPath)) {
            return Utility::showApiError(300, 'NZB file not found!');
        }

        $relData = Release::getByGuid($request->input('id'));
        if ($relData === null) {
            return Utility::showApiError(300, 'Release not found!');
        }

        // Update release and user actions
        Release::updateGrab($request->input('id'));
        UserDownload::addDownloadRequest($uid, $relData['id']);
        User::incrementGrabs($uid);

        if ($request->has('del') && (int) $request->input('del') === 1) {
            UsersRelease::delCartByUserAndRelease($request->input('id'), $uid);
        }

        // Build headers
        $headers = [
            'Content-Type' => 'application/x-nzb',
            'Expires' => now()->addYear()->toRfc7231String(),
            'X-DNZB-Failure' => url('/failed').'?guid='.$request->input('id').'&userid='.$uid.'&api_token='.$rssToken,
            'X-DNZB-Category' => e($relData['category_name']), // Escape category name
            'X-DNZB-Details' => url('/details/'.$request->input('id')),
        ];

        if (! empty($relData['imdbid']) && $relData['imdbid'] > 0) {
            $headers['X-DNZB-MoreInfo'] = 'http://www.imdb.com/title/tt'.$relData['imdbid'];
        } elseif (! empty($relData['tvdb']) && $relData['tvdb'] > 0) {
            $headers['X-DNZB-MoreInfo'] = 'http://www.thetvdb.com/?tab=series&id='.$relData['tvdb'];
        }

        if ((int) $relData['nfostatus'] === 1) {
            $headers['X-DNZB-NFO'] = url('/nfo/'.$request->input('id'));
        }

        $headers += ['X-DNZB-RCode' => '200',
            'X-DNZB-RText' => 'OK, NZB content follows.', ];

        // Raising this value may increase performance
        $buffer_size = 1000000;
        $bytes = '696';

        // Open our file (in binary mode)
        $zd = gzopen($nzbPath, 'rb');

        $insert = '    <file poster="_ZWsSmvpc3Cl@HmhGEn.16m'.$uid.'" date="'.now()->timestamp.'" subject="&quot;release.nfo&quot; yEnc (1/1) 546">
		<groups>
			<group>alt.binaries.test</group>
		</groups>
		<segments>
			<segment bytes="'.$bytes.'" number="1">o9AgxKI0_40wnnFGymLkxrDxt@EggKOp4.48R</segment>
		</segments>
	</file>';

        $final = '';
        // Keep repeating until the end of the input file
        while (! gzeof($zd)) {
            // Read buffer-size bytes
            $contents = gzread($zd, $buffer_size);
            $tmp = preg_replace('/file poster=\"/i', 'file poster="'.$uid.'-', $contents, 10);
            $final .= $tmp;
        }

        $final = preg_replace('/<\/nzb>/i', $insert.PHP_EOL.'</nzb>'.PHP_EOL, $final, 1);

        gzclose($zd);

        // Sanitize file name
        $cleanName = str_replace([',', ' ', '/', '\\'], '_', $relData['searchname']);

        return response()->streamDownload(function () use ($final) {
            echo $final;
        }, $cleanName.'.nzb', $headers);
    }
}
