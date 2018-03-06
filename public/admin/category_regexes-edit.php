<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';


use Blacklight\http\AdminPage;
use Blacklight\Regexes;
use App\Models\Category;

$page = new AdminPage();
$regexes = new Regexes(['Settings' => $page->pdo, 'Table_Name' => 'category_regexes']);

// Set the current action.
$action = request()->input('action') ?? 'view';

$regex = [
    'id' => '',
    'group_regex' => '',
    'regex' => '',
    'description' => '',
    'ordinal' => '',
    'categories_id' => '',
    'status' => 1, ];

$page->smarty->assign('regex', $regex);

switch ($action) {
    case 'submit':
        if (request()->input('group_regex') === '') {
            $page->smarty->assign('error', 'Group regex must not be empty!');
            break;
        }

        if (request()->input('regex') === '') {
            $page->smarty->assign('error', 'Regex cannot be empty');
            break;
        }

        if (! is_numeric(request()->input('ordinal')) || request()->input('ordinal') < 0) {
            $page->smarty->assign('error', 'Ordinal must be a number, 0 or higher.');
            break;
        }

        if (request()->input('id') === '') {
            $regexes->addRegex(request()->all());
        } else {
            $regexes->updateRegex(request()->all());
        }

        header('Location:'.WWW_TOP.'/category_regexes-list.php');
        break;

    case 'view':
    default:
        if (request()->has('id')) {
            $page->title = 'Category Regex Edit';
            $id = request()->input('id');
            $regex = $regexes->getRegexByID($id);
        } else {
            $page->title = 'Category Regex Add';
        }
        $page->smarty->assign('regex', $regex);
        break;
}

$page->smarty->assign('status_ids', [Category::STATUS_ACTIVE, Category::STATUS_INACTIVE]);
$page->smarty->assign('status_names', ['Yes', 'No']);

$categories_db = $page->pdo->queryDirect(
    'SELECT c.id, c.title, cp.title AS parent_title
	FROM categories c
	INNER JOIN categories cp ON c.parentid = cp.id
	WHERE c.parentid IS NOT NULL
	ORDER BY c.id ASC'
);
$categories = ['category_names', 'category_ids'];
if ($categories_db) {
    foreach ($categories_db as $category_db) {
        $categories['category_names'][] = $category_db['parent_title'].' '.$category_db['title'].': '.$category_db['id'];
        $categories['category_ids'][] = $category_db['id'];
    }
}
$page->smarty->assign('category_names', $categories['category_names']);
$page->smarty->assign('category_ids', $categories['category_ids']);

$page->content = $page->smarty->fetch('category_regexes-edit.tpl');
$page->render();
