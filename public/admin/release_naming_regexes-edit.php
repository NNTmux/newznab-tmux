<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use Blacklight\Regexes;
use App\Models\Category;

$page = new AdminPage();
$regexes = new Regexes(['Settings' => $page->pdo, 'Table_Name' => 'release_naming_regexes']);

// Set the current action.
$action = \request()->input('action') ?? 'view';

switch ($action) {
    case 'submit':
        if (\request()->input('group_regex') === '') {
            $page->smarty->assign('error', 'Group regex must not be empty!');
            break;
        }

        if (\request()->input('regex') === '') {
            $page->smarty->assign('error', 'Regex cannot be empty');
            break;
        }

        if (\request()->input('description') === '') {
            \request()->merge(['description' => '']);
        }

        if (! is_numeric(\request()->input('ordinal')) || \request()->input('ordinal') < 0) {
            $page->smarty->assign('error', 'Ordinal must be a number, 0 or higher.');
            break;
        }

        if (\request()->input('id') === '') {
            $regex = $regexes->addRegex(\request()->all());
        } else {
            $regex = $regexes->updateRegex(\request()->all());
        }

        header('Location:'.WWW_TOP.'/release_naming_regexes-list.php');
        break;

    case 'view':
    default:
        if (\request()->has('id')) {
            $page->title = 'Release Naming Regex Edit';
            $id = \request()->input('id');
            $regex = $regexes->getRegexByID($id);
        } else {
            $page->title = 'Release Naming Regex Add';
            $regex = ['status' => 1];
        }
        break;
}

$page->smarty->assign('status_ids', [Category::STATUS_ACTIVE, Category::STATUS_INACTIVE]);
$page->smarty->assign('status_names', ['Yes', 'No']);
$page->smarty->assign('regex', $regex);

$page->content = $page->smarty->fetch('release_naming_regexes-edit.tpl');
$page->render();
