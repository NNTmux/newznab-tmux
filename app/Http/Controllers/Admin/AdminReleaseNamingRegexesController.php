<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use App\Models\Category;
use Blacklight\Regexes;
use Illuminate\Http\Request;

class AdminReleaseNamingRegexesController extends BasePageController
{
    /**
     * @param  \Illuminate\Http\Request  $request
     *
     * @throws \Exception
     */
    public function index(Request $request): void
    {
        $this->setAdminPrefs();
        $regexes = new Regexes(['Settings' => null, 'Table_Name' => 'release_naming_regexes']);

        $meta_title = $title = 'Release Naming Regex List';

        $group = '';
        if ($request->has('group') && ! empty($request->input('group'))) {
            $group = $request->input('group');
        }
        $regex = $regexes->getRegex($group);
        $this->smarty->assign('regex', $regex);

        $content = $this->smarty->fetch('release_naming_regexes-list.tpl');
        $this->smarty->assign(compact('title', 'meta_title', 'content'));
        $this->adminrender();
    }

    /**
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     *
     * @throws \Exception
     */
    public function edit(Request $request): \Illuminate\Routing\Redirector|\Illuminate\Http\RedirectResponse
    {
        $this->setAdminPrefs();
        $regexes = new Regexes(['Settings' => null, 'Table_Name' => 'release_naming_regexes']);

        // Set the current action.
        $action = $request->input('action') ?? 'view';

        switch ($action) {
            case 'submit':
                if (empty($request->input('group_regex'))) {
                    $this->smarty->assign('error', 'Group regex must not be empty!');
                    break;
                }

                if (empty($request->input('regex'))) {
                    $this->smarty->assign('error', 'Regex cannot be empty');
                    break;
                }

                if (empty($request->input('description'))) {
                    $request->merge(['description' => '']);
                }

                if (! is_numeric($request->input('ordinal')) || $request->input('ordinal') < 0) {
                    $this->smarty->assign('error', 'Ordinal must be a number, 0 or higher.');
                    break;
                }

                if (empty($request->input('id'))) {
                    $regexes->addRegex($request->all());
                } else {
                    $regexes->updateRegex($request->all());
                }

                return redirect('admin/release_naming_regexes-list');
                break;

            case 'view':
            default:
                if ($request->has('id')) {
                    $meta_title = $title = 'Release Naming Regex Edit';
                    $id = $request->input('id');
                    $regex = $regexes->getRegexByID($id);
                } else {
                    $meta_title = $title = 'Release Naming Regex Add';
                    $regex = ['status' => 1];
                }
                break;
        }

        $this->smarty->assign('status_ids', [Category::STATUS_ACTIVE, Category::STATUS_INACTIVE]);
        $this->smarty->assign('status_names', ['Yes', 'No']);
        $this->smarty->assign('regex', $regex);

        $content = $this->smarty->fetch('release_naming_regexes-edit.tpl');

        $this->smarty->assign(compact('title', 'meta_title', 'content'));

        $this->adminrender();
    }

    /**
     * @param  \Illuminate\Http\Request  $request
     *
     * @throws \Exception
     */
    public function testRegex(Request $request): void
    {
        $this->setAdminPrefs();
        $meta_title = $title = 'Release Naming Regex Test';

        $group = trim($request->has('group') && ! empty($request->input('group')) ? $request->input('group') : '');
        $regex = trim($request->has('regex') && ! empty($request->input('regex')) ? $request->input('regex') : '');
        $showLimit = ($request->has('showlimit') && is_numeric($request->input('showlimit')) ? $request->input('showlimit') : 250);
        $queryLimit = ($request->has('querylimit') && is_numeric($request->input('querylimit')) ? $request->input('querylimit') : 100000);
        $this->smarty->assign(['group' => $group, 'regex' => $regex, 'showlimit' => $showLimit, 'querylimit' => $queryLimit]);

        if ($group && $regex) {
            $this->smarty->assign('data', (new Regexes(['Settings' => null, 'Table_Name' => 'release_naming_regexes']))->testReleaseNamingRegex($group, $regex, $showLimit, $queryLimit));
        }

        $content = $this->smarty->fetch('release_naming_regexes-test.tpl');
        $this->smarty->assign(compact('title', 'meta_title', 'content'));
        $this->adminrender();
    }
}
