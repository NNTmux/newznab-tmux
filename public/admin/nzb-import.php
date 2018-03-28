<?php

// Check if the user is running from CLI.
if (PHP_SAPI === 'cli') {
    exit('This is a web only script, run misc/testing/nzb-import.php instead.');
}

require_once dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'resources/views/themes/smarty.php';

use Blacklight\NZBImport;
use Blacklight\http\BasePage;

$page = new BasePage();
$page->setAdminPrefs();

$filesToProcess = [];
if ($page->isPostBack()) {
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
        $useNzbName = (request()->has('usefilename') && request()->input('usefilename') === 'on');

        // Check if the user wants to delete the NZB file when done importing.
        $deleteNZB = (request()->has('deleteNZB') && request()->input('deleteNZB') === 'on');

        // Get the path the user set in the browser if he put one.
        $path = (request()->has('folder') ? request()->input('folder') : '');
        if (substr($path, strlen($path) - 1) !== DS) {
            $path .= DS;
        }

        // Get the files from the user specified path.
        $filesToProcess = glob($path.'*.nzb');
    }

    if (count($filesToProcess) > 0) {

        // Create a new instance of NZBImport and send it the file locations.
        $NZBImport = new NZBImport(['Browser' => true, 'Settings' => $page->pdo]);

        $page->smarty->assign(
            'output',
            $NZBImport->beginImport($filesToProcess, $useNzbName, $deleteNZB)
        );
    }
}

$page->title = 'Import Nzbs';
$page->content = $page->smarty->fetch('nzb-import.tpl');
$page->adminrender();
