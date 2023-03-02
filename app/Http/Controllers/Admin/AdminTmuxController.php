<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use App\Models\Settings;
use Illuminate\Http\Request;

class AdminTmuxController extends BasePageController
{
    /**
     * @throws \Exception
     */
    public function edit(Request $request): void
    {
        $this->setAdminPrefs();

        // Set the current action.
        $action = $request->input('action') ?? 'view';

        switch ($action) {
            case 'submit':
                Settings::settingsUpdate($request->all());
                $meta_title = $title = 'Tmux Settings Edit';
                $this->smarty->assign('site', $this->settings);
                break;

            case 'view':
            default:
                $meta_title = $title = 'Tmux Settings Edit';
                $this->smarty->assign('site', $this->settings);
                break;
        }

        $this->smarty->assign('yesno_ids', [1, 0]);
        $this->smarty->assign('yesno_names', ['yes', 'no']);

        $this->smarty->assign('backfill_ids', [0, 4, 1]);
        $this->smarty->assign('backfill_names', ['Disabled', 'Safe', 'All']);
        $this->smarty->assign('backfill_group_ids', [1, 2, 3, 4, 5, 6]);
        $this->smarty->assign('backfill_group', ['Newest', 'Oldest', 'Alphabetical', 'Alphabetical - Reverse', 'Most Posts', 'Fewest Posts']);
        $this->smarty->assign('backfill_days', ['Days per Group', 'Safe Backfill day']);
        $this->smarty->assign('backfill_days_ids', [1, 2]);
        $this->smarty->assign('dehash_ids', [0, 1]);
        $this->smarty->assign('dehash_names', ['Disabled', 'Enabled']);
        $this->smarty->assign('import_ids', [0, 1, 2]);
        $this->smarty->assign('import_names', ['Disabled', 'Import - Do Not Use Filenames', 'Import - Use Filenames']);
        $this->smarty->assign('releases_ids', [0, 1]);
        $this->smarty->assign('releases_names', ['Disabled', 'Update Releases']);
        $this->smarty->assign('post_ids', [0, 1, 2, 3]);
        $this->smarty->assign('post_names', ['Disabled', 'PostProcess Additional', 'PostProcess NFOs', 'All']);
        $this->smarty->assign('fix_crap_radio_ids', ['Disabled', 'All', 'Custom']);
        $this->smarty->assign('fix_crap_radio_names', ['Disabled', 'All', 'Custom']);
        $this->smarty->assign('fix_crap_check_ids', ['blacklist', 'blfiles', 'executable', 'gibberish', 'hashed', 'installbin', 'passworded', 'passwordurl', 'sample', 'scr', 'short', 'size', 'huge', 'nzb', 'codec']);
        $this->smarty->assign('fix_crap_check_names', ['blacklist', 'blfiles', 'executable', 'gibberish', 'hashed', 'installbin', 'passworded', 'passwordurl', 'sample', 'scr', 'short', 'size', 'huge', 'nzb', 'codec']);
        $this->smarty->assign('sequential_ids', [0, 1]);
        $this->smarty->assign('sequential_names', ['Disabled', 'Enabled']);
        $this->smarty->assign('binaries_ids', [0, 1]);
        $this->smarty->assign('binaries_names', ['Disabled', 'Enabled']);
        $this->smarty->assign('lookup_reqids_ids', [0, 1, 2]);
        $this->smarty->assign('lookup_reqids_names', ['Disabled', 'Lookup Request IDs', 'Lookup Request IDs Threaded']);
        $this->smarty->assign('predb_ids', [0, 1]);
        $this->smarty->assign('predb_names', ['Disabled', 'Enabled']);

        $content = $this->smarty->fetch('tmux-edit.tpl');

        $this->smarty->assign(compact('title', 'meta_title', 'content'));

        $this->adminrender();
    }
}
