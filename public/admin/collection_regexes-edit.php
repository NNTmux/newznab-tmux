<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use Blacklight\Regexes;
use App\Models\Category;
use Blacklight\http\AdminPage;

$page = new AdminPage();
$regexes = new Regexes(['Settings' => $page->pdo, 'Table_Name' => 'collection_regexes']);
$error = '';
$regex = ['id' => '', 'regex' => '', 'description' => '', 'group_regex' => '', 'ordinal' => '', 'status' => 1];

switch (request()->input('action') ?? 'view') {
    case 'submit':
        if (request()->input('group_regex') === '') {
            $error = 'Group regex must not be empty!';
            break;
        }

        if (request()->input('regex') === '') {
            $error = 'Regex cannot be empty';
            break;
        }

        if (request()->input('description') === '') {
            request()->merge(['description' => '']);
        }

        if (! is_numeric(request()->input('ordinal')) || request()->input('ordinal') < 0) {
            $error = 'Ordinal must be a number, 0 or higher.';
            break;
        }

        if (request()->input('id') === '') {
            $regexes->addRegex(request()->all());
        } else {
            $regexes->updateRegex(request()->all());
        }

        header('Location:'.WWW_TOP.'/collection_regexes-list.php');
        break;

    case 'view':
    default:
        if (request()->has('id')) {
            $page->title = 'Collections Regex Edit';
            $regex = $regexes->getRegexByID(request()->input('id'));
        } else {
            $page->title = 'Collections Regex Add';
            $regex += ['status' => 1];
        }
        break;
}

$page->smarty->assign('regex', $regex);
$page->smarty->assign('error', $error);
$page->smarty->assign('status_ids', [Category::STATUS_ACTIVE, Category::STATUS_INACTIVE]);
$page->smarty->assign('status_names', ['Yes', 'No']);

$page->content = $page->smarty->fetch('collection_regexes-edit.tpl');
$page->render();
