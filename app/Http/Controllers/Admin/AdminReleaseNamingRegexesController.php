<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use App\Models\Category;
use App\Services\RegexService;
use Illuminate\Http\Request;

class AdminReleaseNamingRegexesController extends BasePageController
{
    /**
     * @throws \Exception
     */
    public function index(Request $request): mixed
    {
        $this->setAdminPrefs();
        $regexes = new RegexService('release_naming_regexes');

        $meta_title = $title = 'Release Naming Regex List';

        $group = '';
        if ($request->has('group') && ! empty($request->input('group'))) {
            $group = $request->input('group');
        }
        $regex = $regexes->getRegex($group);

        $this->viewData = array_merge($this->viewData, [
            'group' => $group,
            'regex' => $regex,
            'title' => $title,
            'meta_title' => $meta_title,
        ]);

        return view('admin.regexes.release-naming-list', $this->viewData);
    }

    /**
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\View\View
     *
     * @throws \Exception
     */
    public function edit(Request $request)
    {
        $this->setAdminPrefs();
        $regexes = new RegexService('release_naming_regexes');

        // Set the current action.
        $action = $request->input('action') ?? 'view';
        $error = '';
        $regex = ['id' => '', 'group_regex' => '', 'regex' => '', 'description' => '', 'ordinal' => '', 'status' => 1];
        $meta_title = $title = 'Release Naming Regex';

        switch ($action) {
            case 'submit':
                if (empty($request->input('group_regex'))) {
                    $error = 'Group regex must not be empty!';
                    break;
                }

                if (empty($request->input('regex'))) {
                    $error = 'Regex cannot be empty';
                    break;
                }

                if (empty($request->input('description'))) {
                    $request->merge(['description' => '']);
                }

                if (! is_numeric($request->input('ordinal')) || $request->input('ordinal') < 0) {
                    $error = 'Ordinal must be a number, 0 or higher.';
                    break;
                }

                if (empty($request->input('id'))) {
                    $regexes->addRegex($request->all());
                } else {
                    $regexes->updateRegex($request->all());
                }

                return redirect()->to('admin/release_naming_regexes-list');

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

        $this->viewData = array_merge($this->viewData, [
            'error' => $error,
            'regex' => (object) $regex,
            'status_ids' => [Category::STATUS_ACTIVE, Category::STATUS_INACTIVE],
            'status_names' => ['Yes', 'No'],
            'title' => $title,
            'meta_title' => $meta_title,
        ]);

        return view('admin.regexes.release-naming-edit', $this->viewData);
    }

    /**
     * @throws \Exception
     */
    public function testRegex(Request $request): mixed
    {
        $this->setAdminPrefs();
        $meta_title = $title = 'Release Naming Regex Test';

        $group = trim($request->has('group') && ! empty($request->input('group')) ? $request->input('group') : '');
        $regex = trim($request->has('regex') && ! empty($request->input('regex')) ? $request->input('regex') : '');
        $showLimit = ($request->has('showlimit') && is_numeric($request->input('showlimit')) ? $request->input('showlimit') : 250);
        $queryLimit = ($request->has('querylimit') && is_numeric($request->input('querylimit')) ? $request->input('querylimit') : 100000);

        $data = null;
        if ($group && $regex) {
            $data = (new RegexService('release_naming_regexes'))->testReleaseNamingRegex($group, $regex, $showLimit, $queryLimit);
        }

        $this->viewData = array_merge($this->viewData, [
            'group' => $group,
            'regex' => $regex,
            'showlimit' => $showLimit,
            'querylimit' => $queryLimit,
            'data' => $data,
            'title' => $title,
            'meta_title' => $meta_title,
        ]);

        return view('admin.regexes.release-naming-test', $this->viewData);
    }
}
