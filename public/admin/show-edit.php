<?php

use App\Models\Video;

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';
require_once NN_WWW.'pages/smartyTV.php';

$page = new AdminPage();
$tv = new smartyTV(['Settings' => $page->pdo]);

switch (request()->input('action') ?? 'view') {
    case 'submit':
        //TODO: Use a function that allows overwrites
        //$tv->update($_POST["id"], $_POST["title"],$_POST["summary"], $_POST['countries_id']);

        if (request()->has('from') && ! empty(request()->input('from'))) {
            header('Location:'.request()->input('from'));
            exit;
        }

        header('Location:'.WWW_TOP.'/show-list.php');
        break;

    case 'view':
    default:
        if (request()->has('id')) {
            $page->title = 'TV Show Edit';
            $show = Video::getByVideoID(request()->input('id'));
        }
        break;
}

$page->smarty->assign('show', $show);

$page->title = 'Edit TV Show Data';
$page->content = $page->smarty->fetch('show-edit.tpl');
$page->render();
