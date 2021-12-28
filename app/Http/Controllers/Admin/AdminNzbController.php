<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use Blacklight\NZBExport;
use Blacklight\NZBImport;
use Blacklight\Releases;
use Blacklight\utility\Utility;
use Illuminate\Http\Request;

class AdminNzbController extends BasePageController
{
    /**
     * @param  \Illuminate\Http\Request  $request
     *
     * @throws \Exception
     */
    public function import(Request $request)
    {
        $this->setAdminPrefs();

        $filesToProcess = [];
        if ($this->isPostBack()) {
            $useNzbName = false;
            $deleteNZB = true;
            // Get the list of NZB files from php /tmp folder if nzb files were uploaded.
            if (isset($_FILES['uploadedfiles'])) {
                foreach ($_FILES['uploadedfiles']['error'] as $key => $error) {
                    if ($error === UPLOAD_ERR_OK) {
                        $tmp_name = $_FILES['uploadedfiles']['tmp_name'][$key];
                        $name = $_FILES['uploadedfiles']['name'][$key];
                        $filesToProcess[] = $tmp_name;
                    }
                }
            } else {

                // Check if the user wants to use the file name as the release name.
                $useNzbName = ($request->has('usefilename') && $request->input('usefilename') === 'on');

                // Check if the user wants to delete the NZB file when done importing.
                $deleteNZB = ($request->has('deleteNZB') && $request->input('deleteNZB') === 'on');

                // Get the path the user set in the browser if he put one.
                $path = ($request->has('folder') ? $request->input('folder') : '');
                if (substr($path, \strlen($path) - 1) !== DS) {
                    $path .= DS;
                }

                // Get the files from the user specified path.
                $filesToProcess = glob($path.'*.nzb');
            }

            if (\count($filesToProcess) > 0) {

                // Create a new instance of NZBImport and send it the file locations.
                $NZBImport = new NZBImport(['Browser' => true, 'Settings' => null]);

                $this->smarty->assign(
                    'output',
                    $NZBImport->beginImport($filesToProcess, $useNzbName, $deleteNZB)
                );
            }
        }

        $meta_title = $title = 'Import Nzbs';
        $content = $this->smarty->fetch('nzb-import.tpl');
        $this->smarty->assign(compact('title', 'meta_title', 'content'));
        $this->adminrender();
    }

    /**
     * @param  \Illuminate\Http\Request  $request
     *
     * @throws \Exception
     */
    public function export(Request $request)
    {
        if (Utility::isCLI()) {
            exit('This script is only for exporting from the web, use the script in misc/testing'.
                PHP_EOL);
        }

        $this->setAdminPrefs();
        $rel = new Releases();

        if ($this->isPostBack()) {
            $path = $request->input('folder');
            $postFrom = ($request->input('postfrom') ?? '');
            $postTo = ($request->input('postto') ?? '');
            $group = ($request->input('group') === '-1' ? 0 : (int) $request->input('group'));
            $gzip = ($request->input('gzip') === '1');

            if ($path !== '') {
                $NE = new NZBExport([
                    'Browser'  => true, 'Settings' => null,
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
                    'folder'   => $path,
                    'output'   => $retVal,
                    'fromdate' => $postFrom,
                    'todate'   => $postTo,
                    'group'    => $request->input('group'),
                    'gzip'     => $request->input('gzip'),
                ]
            );
        } else {
            $this->smarty->assign(
                [
                    'fromdate' => $rel->getEarliestUsenetPostDate(),
                    'todate'   => $rel->getLatestUsenetPostDate(),
                ]
            );
        }

        $meta_title = $title = 'Export Nzbs';
        $this->smarty->assign(
            [
                'gziplist'  => [1 => 'True', 0 => 'False'],
                'grouplist' => $rel->getReleasedGroupsForSelect(true),
            ]
        );
        $content = $this->smarty->fetch('nzb-export.tpl');
        $this->smarty->assign(compact('title', 'meta_title', 'content'));
        $this->adminrender();
    }
}
