<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use Blacklight\Tmux;
use App\Models\Settings;

$page = new AdminPage();
$tmux = new Tmux();
$id = 0;

// Set the current action.
$action = $_REQUEST['action'] ?? 'view';

switch ($action) {
    case 'submit':
        $error = '';
        $ret = (new Settings())->update($_POST);
        $page->title = 'Tmux Settings Edit';
        $page->smarty->assign('site', $page->settings);
        break;

    case 'view':
    default:
        $page->title = 'Tmux Settings Edit';
        $page->smarty->assign('site', $page->settings);
        break;
}

$page->smarty->assign('yesno_ids', [1, 0]);
$page->smarty->assign('yesno_names', ['yes', 'no']);

$page->smarty->assign('backfill_ids', [0, 4, 1]);
$page->smarty->assign('backfill_names', ['Disabled', 'Safe', 'All']);
$page->smarty->assign('backfill_group_ids', [1, 2, 3, 4, 5, 6]);
$page->smarty->assign('backfill_group', ['Newest', 'Oldest', 'Alphabetical', 'Alphabetical - Reverse', 'Most Posts', 'Fewest Posts']);
$page->smarty->assign('backfill_days', ['Days per Group', 'Safe Backfill day']);
$page->smarty->assign('backfill_days_ids', [1, 2]);
$page->smarty->assign('dehash_ids', [0, 1, 2, 3]);
$page->smarty->assign('dehash_names', ['Disabled', 'Decrypt Hashes', 'Predb', 'All']);
$page->smarty->assign('import_ids', [0, 1, 2]);
$page->smarty->assign('import_names', ['Disabled', 'Import - Do Not Use Filenames', 'Import - Use Filenames']);
$page->smarty->assign('releases_ids', [0, 1]);
$page->smarty->assign('releases_names', ['Disabled', 'Update Releases']);
$page->smarty->assign('post_ids', [0, 1, 2, 3]);
$page->smarty->assign('post_names', ['Disabled', 'PostProcess Additional', 'PostProcess NFOs', 'All']);
$page->smarty->assign('fix_crap_radio_ids', ['Disabled', 'All', 'Custom']);
$page->smarty->assign('fix_crap_radio_names', ['Disabled', 'All', 'Custom']);
$page->smarty->assign('fix_crap_check_ids', ['blacklist', 'blfiles', 'executable', 'gibberish', 'hashed', 'installbin', 'passworded', 'passwordurl', 'sample', 'scr', 'short', 'size', 'huge', 'nzb', 'codec']);
$page->smarty->assign('fix_crap_check_names', ['blacklist', 'blfiles', 'executable', 'gibberish', 'hashed', 'installbin', 'passworded', 'passwordurl', 'sample', 'scr', 'short', 'size', 'huge', 'nzb', 'codec']);
$page->smarty->assign('sequential_ids', [0, 1]);
$page->smarty->assign('sequential_names', ['Disabled', 'Enabled']);
$page->smarty->assign('binaries_ids', [0, 1, 2]);
$page->smarty->assign('binaries_names', ['Disabled', 'Simple Threaded Update', 'Complete Threaded Update']);
$page->smarty->assign('lookup_reqids_ids', [0, 1, 2]);
$page->smarty->assign('lookup_reqids_names', ['Disabled', 'Lookup Request IDs', 'Lookup Request IDs Threaded']);
$page->smarty->assign('predb_ids', [0, 1]);
$page->smarty->assign('predb_names', ['Disabled', 'Enabled']);

$page->content = $page->smarty->fetch('tmux-edit.tpl');
$page->render();
