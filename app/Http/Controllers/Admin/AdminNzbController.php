<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use Blacklight\NZBExport;
use Blacklight\NZBImport;
use Blacklight\Releases;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminNzbController extends BasePageController
{
    /**
     * @throws \Exception
     */
    public function import(Request $request): void
    {
        $this->setAdminPrefs();

        $filesToProcess = [];
        $uploadMessages = [];

        if ($this->isPostBack($request)) {
            $useNzbName = false;
            $deleteNZB = true;

            // Get the list of NZB files from php /tmp folder if nzb files were uploaded.
            if (isset($_FILES['uploadedfiles']) && ! empty($_FILES['uploadedfiles']['name'][0])) {
                $maxFileSize = min(
                    $this->convertToBytes(ini_get('upload_max_filesize')),
                    $this->convertToBytes(ini_get('post_max_size'))
                );

                foreach ($_FILES['uploadedfiles']['error'] as $key => $error) {
                    $fileName = $_FILES['uploadedfiles']['name'][$key];

                    if ($error === UPLOAD_ERR_OK) {
                        $tmp_name = $_FILES['uploadedfiles']['tmp_name'][$key];
                        $filesToProcess[] = $tmp_name;
                        $uploadMessages[] = "File '{$fileName}' uploaded successfully.";
                    } else {
                        // Handle specific upload errors
                        $errorMessage = $this->getUploadErrorMessage($error, $fileName, $maxFileSize);
                        $uploadMessages[] = $errorMessage;
                    }
                }
            } else {
                // Check if the user wants to use the file name as the release name.
                $useNzbName = ($request->has('usefilename') && $request->input('usefilename') === 'on');

                // Check if the user wants to delete the NZB file when done importing.
                $deleteNZB = ($request->has('deleteNZB') && $request->input('deleteNZB') === 'on');

                // Get the path the user set in the browser if he put one.
                $path = ($request->has('folder') ? $request->input('folder') : '');
                if (! Str::endsWith($path, '/')) {
                    $path .= '/';
                }

                // Get the files from the user specified path.
                $nzbFiles = glob($path.'*.nzb', GLOB_NOSORT);
                if (empty($nzbFiles)) {
                    $uploadMessages[] = "No NZB files found in the specified path: {$path}";
                } else {
                    $filesToProcess = $nzbFiles;
                    $uploadMessages[] = 'Found '.count($nzbFiles).' NZB file(s) in the specified path.';
                }
            }

            $importResults = [];
            if (\count($filesToProcess) > 0) {
                // Create a new instance of NZBImport and send it the file locations.
                $NZBImport = new NZBImport(['Browser' => true, 'Settings' => null]);
                $importResults = $NZBImport->beginImport($filesToProcess, $useNzbName, $deleteNZB);
            }

            $output = implode('<br>', array_merge($uploadMessages, is_array($importResults) ? $importResults : [$importResults]));
            $this->smarty->assign('output', $output);
        }

        $meta_title = $title = 'Import Nzbs';
        $content = $this->smarty->fetch('nzb-import.tpl');
        $this->smarty->assign(compact('title', 'meta_title', 'content'));
        $this->adminrender();
    }

    /**
     * Helper method to convert PHP size strings (like "2M") to bytes
     */
    private function convertToBytes($sizeStr)
    {
        $sizeStr = trim($sizeStr);
        $unit = strtolower(substr($sizeStr, -1));
        $size = (int) $sizeStr;

        switch ($unit) {
            case 'g':
                $size *= 1024;
            case 'm':
                $size *= 1024;
            case 'k':
                $size *= 1024;
        }

        return $size;
    }

    /**
     * Get human-readable error message for upload errors
     */
    private function getUploadErrorMessage($errorCode, $fileName, $maxFileSize)
    {
        switch ($errorCode) {
            case UPLOAD_ERR_INI_SIZE:
                return "The file '{$fileName}' exceeds the upload_max_filesize directive (".
                       ini_get('upload_max_filesize').').';
            case UPLOAD_ERR_FORM_SIZE:
                return "The file '{$fileName}' exceeds the MAX_FILE_SIZE directive specified in the HTML form.";
            case UPLOAD_ERR_PARTIAL:
                return "The file '{$fileName}' was only partially uploaded.";
            case UPLOAD_ERR_NO_FILE:
                return "No file was uploaded for '{$fileName}'.";
            case UPLOAD_ERR_NO_TMP_DIR:
                return "Missing temporary folder for file '{$fileName}'.";
            case UPLOAD_ERR_CANT_WRITE:
                return "Failed to write file '{$fileName}' to disk.";
            case UPLOAD_ERR_EXTENSION:
                return "File upload stopped by extension for '{$fileName}'.";
            default:
                return "Unknown upload error for '{$fileName}'.";
        }
    }

    /**
     * @throws \Exception
     */
    public function export(Request $request): void
    {
        $this->setAdminPrefs();
        $rel = new Releases;

        if ($this->isPostBack($request)) {
            $path = $request->input('folder');
            $postFrom = ($request->input('postfrom') ?? '');
            $postTo = ($request->input('postto') ?? '');
            $group = ($request->input('group') === '-1' ? 0 : (int) $request->input('group'));
            $gzip = ($request->input('gzip') === '1');

            if ($path !== '') {
                $NE = new NZBExport([
                    'Browser' => true, 'Settings' => null,
                    'Releases' => $rel,
                ]);
                $retVal = $NE->beginExport(
                    [
                        $path,
                        $postFrom,
                        $postTo,
                        $group,
                        $gzip,
                    ]
                );
            } else {
                $retVal = 'Error, a path is required!';
            }

            $this->smarty->assign(
                [
                    'folder' => $path,
                    'output' => $retVal,
                    'fromdate' => $postFrom,
                    'todate' => $postTo,
                    'group' => $request->input('group'),
                    'gzip' => $request->input('gzip'),
                ]
            );
        } else {
            $this->smarty->assign(
                [
                    'fromdate' => $rel->getEarliestUsenetPostDate(),
                    'todate' => $rel->getLatestUsenetPostDate(),
                ]
            );
        }

        $meta_title = $title = 'Export Nzbs';
        $this->smarty->assign(
            [
                'gziplist' => [1 => 'True', 0 => 'False'],
                'grouplist' => $rel->getReleasedGroupsForSelect(true),
            ]
        );
        $content = $this->smarty->fetch('nzb-export.tpl');
        $this->smarty->assign(compact('title', 'meta_title', 'content'));
        $this->adminrender();
    }
}
