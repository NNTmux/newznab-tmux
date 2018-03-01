<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use App\Models\Category;
use Blacklight\Binaries;

$page = new AdminPage();
$bin = new Binaries(['Settings' => $page->pdo]);
$error = '';
$regex = ['id' => '', 'groupname' => '', 'regex' => '', 'description' => '', 'msgcol' => 1, 'status' => 1, 'optype' => 1];

switch (request()->input('action') ?? 'view') {
    case 'submit':
        if (request()->input('groupname') === '') {
            $error = 'Group must be a valid usenet group';
            break;
        }

        if (request()->input('regex') === '') {
            $error = 'Regex cannot be empty';
            break;
        }

        if (request()->input('id') === '') {
            $bin->addBlacklist(request()->all());
        } else {
            $bin->updateBlacklist(request()->all());
        }

        header('Location:'.WWW_TOP.'/binaryblacklist-list.php');
        break;

    case 'addtest':
        if (request()->has('regex') && request()->has('groupname')) {
            $regex += [
                    'groupname' => request()->input('groupname'),
                    'regex'     => request()->input('regex'),
                    'ordinal'   => 1,
                    'status'    => 1,
            ];
        }
        break;

    case 'view':
    default:
        if (request()->has('id')) {
            $page->title = 'Binary Black/Whitelist Edit';
            $regex = $bin->getBlacklistByID(request()->input('id'));
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
