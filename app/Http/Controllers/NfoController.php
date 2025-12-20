<?php

namespace App\Http\Controllers;

use App\Models\Release;
use App\Models\ReleaseNfo;
use Illuminate\Http\Request;

class NfoController extends BasePageController
{
    /**
     * @throws \Exception
     */
    public function showNfo(Request $request, string $id = '')
    {
        if ($id) {
            $rel = Release::getByGuid($id);

            if (! $rel) {
                abort(404, 'Release does not exist');
            }

            $nfo = ReleaseNfo::getReleaseNfo($rel['id']);

            if ($nfo !== null) {
                $nfo['nfoUTF'] = cp437toUTF($nfo['nfo']);

                $modal = $request->has('modal');

                if ($modal) {
                    // Return just the NFO content for modal display
                    return view('nfo.view', [
                        'rel' => $rel,
                        'nfo' => $nfo,
                        'modal' => true,
                    ]);
                } else {
                    // Return full page view
                    return view('nfo.view', [
                        'rel' => $rel,
                        'nfo' => $nfo,
                        'modal' => false,
                        'meta_title' => 'View NFO - '.$rel['searchname'],
                        'meta_keywords' => 'view,nzb,nfo,description,details',
                        'meta_description' => 'View NFO File for '.$rel['searchname'],
                    ]);
                }
            } else {
                abort(404, 'NFO does not exist');
            }
        }

        abort(404, 'Invalid request');
    }
}
