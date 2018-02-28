<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use App\Models\Category;
use Blacklight\Binaries;

$page = new AdminPage();
$bin = new Binaries(['Settings' => $page->pdo]);
$error = '';
$regex = ['id' => '', 'groupname' => '', 'regex' => '', 'description' => '', 'msgcol' => 1, 'status' => 1, 'optype' => 1];

switch ($page->request->input('action') ?? 'view') {
    case 'submit':
        if ($page->request->input('groupname') === '') {
            $error = 'Group must be a valid usenet group';
            break;
        }

        if ($page->request->input('regex') === '') {
            $error = 'Regex cannot be empty';
            break;
        }

        if ($page->request->input('id') === '') {
            $bin->addBlacklist($page->request->all());
        } else {
            $bin->updateBlacklist($page->request->all());
        }

        header('Location:'.WWW_TOP.'/binaryblacklist-list.php');
        break;

    case 'addtest':
        if ($page->request->has('regex') && $page->request->has('groupname')) {
            $regex += [
                    'groupname' => $page->request->input('groupname'),
                    'regex'     => $page->request->input('regex'),
                    'ordinal'   => 1,
                    'status'    => 1,
            ];
        }
        break;

    case 'view':
    default:
        if ($page->request->has('id')) {
            $page->title = 'Binary Black/Whitelist Edit';
            $regex = $bin->getBlacklistByID($page->request->input('id'));
        } else {
            $page->title = 'Binary Black/Whitelist Add';
            $regex += [
                    'status' => 1,
                    'optype' => 1,
                    'msgcol' => 1,
            ];
        }
        break;
}

$page->smarty->assign(
    [
                'error'        => $error,
                'regex'        => $regex,
                'status_ids'   => [Category::STATUS_ACTIVE, Category::STATUS_INACTIVE],
                'status_names' => ['Yes', 'No'],
                'optype_ids'   => [1, 2],
                'optype_names' => ['Black', 'White'],
                'msgcol_ids'   => [
                        Binaries::BLACKLIST_FIELD_SUBJECT,
                        Binaries::BLACKLIST_FIELD_FROM,
                        Binaries::BLACKLIST_FIELD_MESSAGEID,
                ],
                'msgcol_names' => ['Subject', 'Poster', 'MessageId'],
        ]
);

$page->content = $page->smarty->fetch('binaryblacklist-edit.tpl');
$page->render();
