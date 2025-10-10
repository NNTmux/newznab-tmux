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
    public function edit(Request $request)
    {
        $this->setAdminPrefs();

        // Set the current action.
        $action = $request->input('action') ?? 'view';

        switch ($action) {
            case 'submit':
                Settings::settingsUpdate($request->all());

                return redirect()->to('admin/tmux-edit')->with('success', 'Tmux settings updated successfully');

            case 'view':
            default:
                break;
        }

        $meta_title = $title = 'Tmux Settings Edit';

        $this->viewData = array_merge($this->viewData, [
            'site' => $this->settings,
            'yesno_ids' => [1, 0],
            'yesno_names' => ['yes', 'no'],
            'backfill_ids' => [0, 4, 1],
            'backfill_names' => ['Disabled', 'Safe', 'All'],
            'backfill_group_ids' => [1, 2, 3, 4, 5, 6],
            'backfill_group' => ['Newest', 'Oldest', 'Alphabetical', 'Alphabetical - Reverse', 'Most Posts', 'Fewest Posts'],
            'backfill_days' => ['Days per Group', 'Safe Backfill day'],
            'backfill_days_ids' => [1, 2],
            'dehash_ids' => [0, 1],
            'dehash_names' => ['Disabled', 'Enabled'],
            'import_ids' => [0, 1, 2],
            'import_names' => ['Disabled', 'Import - Do Not Use Filenames', 'Import - Use Filenames'],
            'releases_ids' => [0, 1],
            'releases_names' => ['Disabled', 'Update Releases'],
            'post_ids' => [0, 1, 2, 3],
            'post_names' => ['Disabled', 'PostProcess Additional', 'PostProcess NFOs', 'All'],
            'fix_crap_radio_ids' => ['Disabled', 'All', 'Custom'],
            'fix_crap_radio_names' => ['Disabled', 'All', 'Custom'],
            'fix_crap_check_ids' => ['blacklist', 'blfiles', 'executable', 'gibberish', 'hashed', 'installbin', 'passworded', 'passwordurl', 'sample', 'scr', 'short', 'size', 'huge', 'nzb', 'codec'],
            'fix_crap_check_names' => ['blacklist', 'blfiles', 'executable', 'gibberish', 'hashed', 'installbin', 'passworded', 'passwordurl', 'sample', 'scr', 'short', 'size', 'huge', 'nzb', 'codec'],
            'sequential_ids' => [0, 1],
            'sequential_names' => ['Disabled', 'Enabled'],
            'binaries_ids' => [0, 1],
            'binaries_names' => ['Disabled', 'Enabled'],
            'lookup_reqids_ids' => [0, 1, 2],
            'lookup_reqids_names' => ['Disabled', 'Lookup Request IDs', 'Lookup Request IDs Threaded'],
            'predb_ids' => [0, 1],
            'predb_names' => ['Disabled', 'Enabled'],
            'title' => $title,
            'meta_title' => $meta_title,
        ]);

        return view('admin.site.tmux-edit', $this->viewData);
    }
}
