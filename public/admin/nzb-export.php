<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use Blacklight\Releases;
use Blacklight\NZBExport;
use Blacklight\http\AdminPage;

if (\Blacklight\utility\Utility::isCLI()) {
    exit('This script is only for exporting from the web, use the script in misc/testing'.
        PHP_EOL);
}

$page = new AdminPage();
$rel = new Releases(['Settings' => $page->pdo]);

if ($page->isPostBack()) {
    $retVal = $path = '';

    $path = request()->input('folder');
    $postFrom = (request()->input('postfrom') ?? '');
    $postTo = (request()->input('postto') ?? '');
    $group = (request()->input('group') === '-1' ? 0 : (int) request()->input('group'));
    $gzip = (request()->input('gzip') === '1');

    if ($path !== '') {
        $NE = new NZBExport([
            'Browser'  => true, 'Settings' => $page->pdo,
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

    $page->smarty->assign(
        [
            'folder'   => $path,
            'output'   => $retVal,
            'fromdate' => $postFrom,
            'todate'   => $postTo,
            'group'    => request()->input('group'),
            'gzip'     => request()->input('gzip'),
        ]
    );
} else {
    $page->smarty->assign(
        [
            'fromdate' => $rel->getEarliestUsenetPostDate(),
            'todate'   => $rel->getLatestUsenetPostDate(),
        ]
    );
}

$page->title = 'Export Nzbs';
$page->smarty->assign(
    [
        'gziplist'  => [1 => 'True', 0 => 'False'],
        'grouplist' => $rel->getReleasedGroupsForSelect(true),
    ]
);
$page->content = $page->smarty->fetch('nzb-export.tpl');
$page->render();
