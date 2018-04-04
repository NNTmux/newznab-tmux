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
use Blacklight\utility\Utility;
use Illuminate\Support\Facades\Auth;

class GetNzbController extends BasePageController
{
    /**
     * @param                          $guid
     * @param \Illuminate\Http\Request request()
     *
     * @throws \Exception
     */
    public function getNzb($guid)
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
            if (! request()->has('i') || ! request()->has('r')) {
                Utility::showApiError(200);
            }

            $res = User::getByIdAndRssToken(request()->input('i'), request()->input('r'));
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
        if ($guid) {
            str_ireplace('.nzb', '', $guid);
        }
        //
        // A hash of the users ip to record against the download
        //
        $hosthash = '';
        if ((int) Settings::settingValue('..storeuserips') === 1) {
            $hosthash = User::getHostHash(request()->ip(), Settings::settingValue('..siteseed'));
        }

        // Check download limit on user role.
        $requests = UserDownload::getDownloadRequests($uid);
        if ($requests > $maxDownloads) {
            Utility::showApiError(501);
        }

        if (! $guid) {
            Utility::showApiError(200, 'Parameter id is required');
        }

        // Remove any suffixed id with .nzb which is added to help weblogging programs see nzb traffic.
        request()->merge(['id' => str_ireplace('.nzb', '', request()->input('id'))]);

        $rel = new Releases(['Settings' => $this->settings]);
        // User requested a zip of guid,guid,guid releases.
        if (request()->has('zip') && request()->input('zip') === '1') {
            $guids = explode(',', request()->input('id'));
            if ($requests['num'] + \count($guids) > $maxDownloads) {
                Utility::showApiError(501);
            }

            $zip = $rel->getZipped($guids);
            if (\strlen($zip) > 0) {
                User::incrementGrabs($uid, \count($guids));
                foreach ($guids as $guid) {
                    Release::updateGrab($guid);
                    UserDownload::addDownloadRequest($uid, $guid);

                    if (request()->has('del') && (int) request()->input('del') === 1) {
                        UsersRelease::delCartByUserAndRelease($guid, $uid);
                    }
                }

                header('Content-type: application/octet-stream');
                header('Content-disposition: attachment; filename='.date('Ymdhis').'.nzb.zip');
                exit($zip);
            }

            $this->show404();
        }

        $nzbPath = (new NZB())->getNZBPath(request()->input('id'));
        if (! file_exists($nzbPath)) {
            Utility::showApiError(300, 'NZB file not found!');
        }

        $relData = Release::getByGuid($guid);
        if ($relData !== null) {
            Release::updateGrab($guid);
            UserDownload::addDownloadRequest($uid, $relData['id']);
            User::incrementGrabs($uid);
            if (request()->has('del') && (int) request()->input('del') === 1) {
                UsersRelease::delCartByUserAndRelease($guid, $uid);
            }
        } else {
            Utility::showApiError(300, 'Release not found!');
        }

        // Start reading output buffer.
        ob_start();
        // De-gzip the NZB and store it in the output buffer.
        readgzfile($nzbPath);

        $cleanName = str_replace([',', ' ', '/'], '_', $relData['searchname']);

        // Set the NZB file name.
        header('Content-Disposition: attachment; filename='.$cleanName.'.nzb');
        // Get the size of the NZB file.
        header('Content-Length: '.ob_get_length());
        header('Content-Type: application/x-nzb');
        header('Expires: '.date('r', time() + 31536000));
        // Set X-DNZB header data.
        header('X-DNZB-Failure: '.$this->serverurl.'failed/'.'?guid='.$guid.'&userid='.$uid.'&rsstoken='.$rssToken);
        header('X-DNZB-Category: '.$relData['category_name']);
        header('X-DNZB-Details: '.$this->serverurl.'details/'.$guid);
        if (! empty($relData['imdbid']) && $relData['imdbid'] > 0) {
            header('X-DNZB-MoreInfo: http://www.imdb.com/title/tt'.$relData['imdbid']);
        } elseif (! empty($relData['tvdb']) && $relData['tvdb'] > 0) {
            header('X-DNZB-MoreInfo: http://www.thetvdb.com/?tab=series&id='.$relData['tvdb']);
        }
        header('X-DNZB-Name: '.$cleanName);
        if ((int) $relData['nfostatus'] === 1) {
            header('X-DNZB-NFO: '.$this->serverurl.'nfo/'.$guid);
        }
        header('X-DNZB-RCode: 200');
        header('X-DNZB-RText: OK, NZB content follows.');

        // Print buffer and flush it.
        ob_end_flush();
    }
}
