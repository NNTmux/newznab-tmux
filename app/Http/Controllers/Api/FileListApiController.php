<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Release;
use Blacklight\NZB;
use Illuminate\Http\JsonResponse;

class FileListApiController extends Controller
{
    /**
     * Get file list for a release
     */
    public function getFileList(string $guid): JsonResponse
    {
        $nzb = new NZB;

        $rel = Release::getByGuid($guid);
        if (! $rel) {
            return response()->json(['error' => 'Release not found'], 404);
        }

        $nzbpath = $nzb->NZBPath($guid);

        if (! file_exists($nzbpath)) {
            return response()->json(['error' => 'NZB file not found'], 404);
        }

        ob_start();
        @readgzfile($nzbpath);
        $nzbfile = ob_get_clean();

        $files = $nzb->nzbFileList($nzbfile);

        return response()->json([
            'release' => [
                'guid' => $rel['guid'],
                'searchname' => $rel['searchname'],
            ],
            'files' => $files,
            'total' => count($files),
        ]);
    }
}
