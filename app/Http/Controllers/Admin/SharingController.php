<?php

namespace App\Http\Controllers\Admin;

use App\Models\Sharing;
use App\Models\SharingSite;
use Illuminate\Http\Request;
use App\Http\Controllers\BasePageController;

class SharingController extends BasePageController
{
    public function index(Request $request)
    {
        $this->setAdminPrefs();

        $title = 'Sharing Settings';

        $allSites = SharingSite::query()->orderByDesc('id')->paginate(config('nntmux.items_per_cover_page'));
        if ($allSites->total() === 0) {
            $allSites = false;
        }

        $ourSite = Sharing::query()->first();

        if (! empty($request->all())) {
            if (! empty($request->input('sharing_name')) && ! preg_match('/\s+/', $request->input('sharing_name')) && strlen($request->input('sharing_name')) < 255) {
                $site_name = trim($request->input('sharing_name'));
            } else {
                $site_name = $ourSite['site_name'];
            }
            if (! empty($request->input('sharing_maxpush')) && is_numeric($request->input('sharing_maxpush'))) {
                $max_push = trim($request->input('sharing_maxpush'));
            } else {
                $max_push = $ourSite['max_push'];
            }
            if (! empty($request->input('sharing_maxpoll')) && is_numeric($request->input('sharing_maxpush'))) {
                $max_pull = trim($request->input('sharing_maxpoll'));
            } else {
                $max_pull = $ourSite['max_pull'];
            }
            if (! empty($request->input('sharing_maxdownload')) && is_numeric($request->input('sharing_maxdownload'))) {
                $max_download = trim($request->input('sharing_maxdownload'));
            } else {
                $max_download = $ourSite['max_download'];
            }
            Sharing::query()
                ->update(
                    [
                        'site_name' => $site_name,
                        'max_push' => $max_push,
                        'max_pull' => $max_pull,
                        'max_download' => $max_download,
                    ]
                );

            $ourSite = $ourSite = Sharing::query()->first();
        }

        $this->smarty->assign(['local' => $ourSite, 'sites' => $allSites]);

        $content = $this->smarty->fetch('sharing.tpl');

        $this->smarty->assign(
            [
                'title' => $title,
                'content' => $content,
            ]
        );
        $this->adminrender();
    }
}
