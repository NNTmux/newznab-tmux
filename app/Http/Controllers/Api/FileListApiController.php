<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Release;
use App\Services\Nzb\NzbParserService;
use App\Services\Nzb\NzbService;
use Illuminate\Http\JsonResponse;

class FileListApiController extends Controller
{
    /**
     * Get file list for a release
     */
    public function getFileList(string $guid): JsonResponse
    {
        $nzb = app(NzbService::class);
        $nzbParser = app(NzbParserService::class);

        $rel = Release::getByGuid($guid);
        if (! $rel) {
            return response()->json(['error' => 'Release not found'], 404);
        }

        $nzbpath = $nzb->nzbPath($guid);

        if (! file_exists($nzbpath)) {
            return response()->json(['error' => 'NZB file not found'], 404);
        }

        ob_start();
        @readgzfile($nzbpath);
        $nzbfile = ob_get_clean();

        $files = $nzbParser->parseNzbFileList($nzbfile);

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
