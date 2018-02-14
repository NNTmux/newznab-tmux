<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use Blacklight\Regexes;
use App\Models\Category;

$page = new AdminPage();
$regexes = new Regexes(['Settings' => $page->pdo, 'Table_Name' => 'release_naming_regexes']);

// Set the current action.
$action = $_REQUEST['action'] ?? 'view';

switch ($action) {
    case 'submit':
        if ($_POST['group_regex'] === '') {
            $page->smarty->assign('error', 'Group regex must not be empty!');
            break;
        }

        if ($_POST['regex'] === '') {
            $page->smarty->assign('error', 'Regex cannot be empty');
            break;
        }

        if ($_POST['description'] === '') {
            $_POST['description'] = '';
        }

        if (! is_numeric($_POST['ordinal']) || $_POST['ordinal'] < 0) {
            $page->smarty->assign('error', 'Ordinal must be a number, 0 or higher.');
            break;
        }

        if ($_POST['id'] === '') {
            $regex = $regexes->addRegex($_POST);
        } else {
            $regex = $regexes->updateRegex($_POST);
        }

        header('Location:'.WWW_TOP.'/release_naming_regexes-list.php');
        break;

    case 'view':
    default:
        if (isset($_GET['id'])) {
            $page->title = 'Release Naming Regex Edit';
            $id = $_GET['id'];
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
