<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use App\Models\Category;
use Blacklight\Regexes;
use Illuminate\Http\Request;

class AdminCollectionRegexesController extends BasePageController
{
    /**
     * @throws \Exception
     */
    public function index(Request $request)
    {
        $this->setAdminPrefs();
        $regexes = new Regexes(['Settings' => null, 'Table_Name' => 'collection_regexes']);

        $meta_title = $title = 'Collections Regex List';

        $group = ($request->has('group') && ! empty($request->input('group')) ? $request->input('group') : '');
        $regex = $regexes->getRegex($group);

        $this->viewData = array_merge($this->viewData, [
            'group' => $group,
            'regex' => $regex,
            'title' => $title,
            'meta_title' => $meta_title,
        ]);

        return view('admin.regexes.collection-list', $this->viewData);
    }

    /**
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\View\View
     *
     * @throws \Exception
     */
    public function edit(Request $request)
    {
        $this->setAdminPrefs();
        $regexes = new Regexes(['Settings' => null, 'Table_Name' => 'collection_regexes']);
        $error = '';
        $regex = ['id' => '', 'regex' => '', 'description' => '', 'group_regex' => '', 'ordinal' => '', 'status' => 1];
        $meta_title = $title = 'Collections Regex';

        switch ($request->input('action') ?? 'view') {
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

                return redirect()->to('admin/collection_regexes-list');

            case 'view':
            default:
                if ($request->has('id')) {
                    $meta_title = $title = 'Collections Regex Edit';
                    $regex = $regexes->getRegexByID($request->input('id'));
                } else {
                    $meta_title = $title = 'Collections Regex Add';
                    $regex += ['status' => 1];
                }
                break;
        }

        $this->viewData = array_merge($this->viewData, [
            'regex' => (object) $regex,
            'error' => $error,
            'status_ids' => [Category::STATUS_ACTIVE, Category::STATUS_INACTIVE],
            'status_names' => ['Yes', 'No'],
            'title' => $title,
            'meta_title' => $meta_title,
        ]);

        return view('admin.regexes.collection-edit', $this->viewData);
    }

    /**
     * @throws \Exception
     */
    public function testRegex(Request $request)
    {
        $this->setAdminPrefs();
        $meta_title = $title = 'Collections Regex Test';

        $group = trim($request->has('group') && ! empty($request->input('group')) ? $request->input('group') : '');
        $regex = trim($request->has('regex') && ! empty($request->input('regex')) ? $request->input('regex') : '');
        $limit = ($request->has('limit') && is_numeric($request->input('limit')) ? $request->input('limit') : 50);

        $data = null;
        if ($group && $regex) {
            $data = (new Regexes(['Settings' => null, 'Table_Name' => 'collection_regexes']))->testCollectionRegex($group, $regex, $limit);
        }

        $this->viewData = array_merge($this->viewData, [
            'group' => $group,
            'regex' => $regex,
            'limit' => $limit,
            'data' => $data,
            'title' => $title,
            'meta_title' => $meta_title,
        ]);

        return view('admin.regexes.collection-test', $this->viewData);
    }
}
