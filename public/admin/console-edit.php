<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use Blacklight\Genres;
use Blacklight\Console;

use Blacklight\http\AdminPage;
use Illuminate\Support\Carbon;

$page = new AdminPage();
$console = new Console(['Settings' => $page->pdo]);
$gen = new Genres();
$id = 0;

// set the current action
$action = request()->input('action') ?? 'view';

if (request()->has('id')) {
    $id = request()->input('id');
    $con = $console->getConsoleInfo($id);

    if (! $con) {
        $page->show404();
    }

    switch ($action) {
        case 'submit':
            $coverLoc = WWW_DIR.'covers/console/'.$id.'.jpg';

            if ($_FILES['cover']['size'] > 0) {
                $tmpName = $_FILES['cover']['tmp_name'];
                $file_info = getimagesize($tmpName);
                if (! empty($file_info)) {
                    move_uploaded_file($_FILES['cover']['tmp_name'], $coverLoc);
                }
            }

            request()->merge(['cover' => file_exists($coverLoc) ? 1 : 0]);
            request()->merge(['salesrank' => (empty(request()->input('salesrank')) || ! ctype_digit(request()->input('salesrank'))) ? 'null' : request()->input('salesrank')]);
            request()->merge(['releasedate' => (empty(request()->input('releasedate')) || ! strtotime(request()->input('releasedate'))) ? $con['releasedate'] : Carbon::parse(request()->input('releasedate'))->timestamp]);

            $console->update($id, request()->input('title'), request()->input('asin'), request()->input('url'), request()->input('salesrank'), request()->input('platform'), request()->input('publisher'), request()->input('releasedate'), request()->input('esrb'), request()->input('cover'), request()->input('genre'));

            header('Location:'.WWW_TOP.'/console-list.php');
            die();
        break;
        case 'view':
        default:
            $page->title = 'Console Edit';
            $page->smarty->assign('console', $con);
            $page->smarty->assign('genres', $gen->getGenres(Genres::CONSOLE_TYPE));
        break;
    }
}

$page->content = $page->smarty->fetch('console-edit.tpl');
$page->render();
