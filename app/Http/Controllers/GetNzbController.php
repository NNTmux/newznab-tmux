<?php

namespace App\Http\Controllers;

use Blacklight\NZB;
use App\Models\User;
use App\Models\Release;
use App\Models\Settings;
use Blacklight\Releases;
use App\Models\UserDownload;
use App\Models\UsersRelease;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Blacklight\utility\Utility;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;

class GetNzbController extends BasePageController
{
    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     * @throws \Exception
     */
    public function getNzb(Request $request)
    {
        $this->setPrefs();

        // Page is accessible only by the rss token, or logged in users.
        if (Auth::check()) {
            $uid = Auth::id();
            $maxDownloads = $this->userdata->role->downloadrequests;
            $rssToken = $this->userdata['rsstoken'];
            if (User::isDisabled($this->userdata['username'])) {
                Utility::showApiError(101);
            }
        } else {
            if (! $request->has('i') || ! $request->has('r')) {
                Utility::showApiError(200);
            }

            $res = User::getByIdAndRssToken($request->input('i'), $request->input('r'));
            if (! $res) {
                Utility::showApiError(100);
            }

            $uid = $res['id'];
            $rssToken = $res['rsstoken'];
            $maxDownloads = $res->role->downloadrequests;
            if (User::isDisabled($res['username'])) {
                Utility::showApiError(101);
            }
        }

        // Remove any suffixed id with .nzb which is added to help weblogging programs see nzb traffic.
        if ($request->has('id')) {
            str_ireplace('.nzb', '', $request->input('id'));
        }
        //
        // A hash of the users ip to record against the download
        //
        $hosthash = '';
        if ((int) Settings::settingValue('..storeuserips') === 1) {
            $hosthash = User::getHostHash($request->ip(), Settings::settingValue('..siteseed'));
        }

        // Check download limit on user role.
        $requests = UserDownload::getDownloadRequests($uid);
        if ($requests > $maxDownloads) {
            Utility::showApiError(501);
        }

        if (! $request->input('id')) {
            Utility::showApiError(200, 'Parameter id is required');
        }

        // Remove any suffixed id with .nzb which is added to help weblogging programs see nzb traffic.
        $request->merge(['id' => str_ireplace('.nzb', '', $request->input('id'))]);

        $rel = new Releases(['Settings' => $this->settings]);
        // User requested a zip of guid,guid,guid releases.
        if ($request->has('zip') && $request->input('zip') === '1') {
            $guids = explode(',', $request->input('id'));
            if ($requests['num'] + \count($guids) > $maxDownloads) {
                Utility::showApiError(501);
            }

            $zip = $rel->getZipped($guids);
            if (\strlen($zip) > 0) {
                User::incrementGrabs($uid, \count($guids));
                foreach ($guids as $guid) {
                    Release::updateGrab($guid);
                    UserDownload::addDownloadRequest($uid, $guid);

                    if ($request->has('del') && (int) $request->input('del') === 1) {
                        UsersRelease::delCartByUserAndRelease($guid, $uid);
                    }
                }

                return response()->streamDownload(function () use ($zip) {
                    echo $zip;
                }, Carbon::now()->format('Ymdhis').'.nzb.zip', ['Content-type:' => 'application/octet-stream']);
            }

            $this->show404();
        }

        $nzbPath = (new NZB())->getNZBPath($request->input('id'));

        /*if (! File::exists($nzbPath)) {
            Utility::showApiError(300, 'NZB file not found!');
        }*/

        $relData = Release::getByGuid($request->input('id'));
        if ($relData !== null) {
            Release::updateGrab($request->input('id'));
            UserDownload::addDownloadRequest($uid, $relData['id']);
            User::incrementGrabs($uid);
            if ($request->has('del') && (int) $request->input('del') === 1) {
                UsersRelease::delCartByUserAndRelease($request->input('id'), $uid);
            }
        } else {
            Utility::showApiError(300, 'Release not found!');
        }

        $cleanName = str_replace([',', ' ', '/'], '_', $relData['searchname']);

        $headers = [
            'Content-Disposition:' => 'attachment; filename='.$cleanName.'.nzb',
            'Content-Type:' => 'application/x-nzb',
            'Expires:' => date('r', Carbon::now()->addDays(365)->timestamp),
            'X-DNZB-Failure:' => $this->serverurl.'failed'.'?guid='.$request->input('id').'&userid='.$uid.'&rsstoken='.$rssToken,
            'X-DNZB-Category:' => $relData['category_name'],
            'X-DNZB-Details:' => $this->serverurl.'details/'.$request->input('id'),
        ];

        if (! empty($relData['imdbid']) && $relData['imdbid'] > 0) {
            $headers += ['X-DNZB-MoreInfo:' => 'http://www.imdb.com/title/tt'.$relData['imdbid']];
        } elseif (! empty($relData['tvdb']) && $relData['tvdb'] > 0) {
            $headers += ['X-DNZB-MoreInfo' => 'http://www.thetvdb.com/?tab=series&id='.$relData['tvdb']];
        }

        if ((int) $relData['nfostatus'] === 1) {
            $headers += ['X-DNZB-NFO: ' => $this->serverurl.'nfo/'.$request->input('id')];
        }

        $headers += ['X-DNZB-RCode:' => '200',
            'X-DNZB-RText:' => 'OK, NZB content follows.', ];

        return response()->streamDownload(function () use ($nzbPath) {
            readgzfile($nzbPath);
        }, $cleanName.'.nzb', $headers);
    }
}
