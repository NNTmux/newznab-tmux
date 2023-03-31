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
    public function index(Request $request): void
    {
        $this->setAdminPrefs();
        $regexes = new Regexes(['Settings' => null, 'Table_Name' => 'collection_regexes']);

        $meta_title = $title = 'Collections Regex List';

        $group = ($request->has('group') && ! empty($request->input('group')) ? $request->input('group') : '');
        $regex = $regexes->getRegex($group);
        $this->smarty->assign(compact('group', 'regex'));

        $content = $this->smarty->fetch('collection_regexes-list.tpl');

        $this->smarty->assign(compact('title', 'meta_title', 'content'));

        $this->adminrender();
    }

    /**
     * @throws \Exception
     */
    public function edit(Request $request): \Illuminate\Routing\Redirector|\Illuminate\Http\RedirectResponse
    {
        $this->setAdminPrefs();
        $regexes = new Regexes(['Settings' => null, 'Table_Name' => 'collection_regexes']);
        $error = '';
        $regex = ['id' => '', 'regex' => '', 'description' => '', 'group_regex' => '', 'ordinal' => '', 'status' => 1];

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
                break;

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

        $this->smarty->assign('regex', $regex);
        $this->smarty->assign('error', $error);
        $this->smarty->assign('status_ids', [Category::STATUS_ACTIVE, Category::STATUS_INACTIVE]);
        $this->smarty->assign('status_names', ['Yes', 'No']);

        $content = $this->smarty->fetch('collection_regexes-edit.tpl');

        $this->smarty->assign(compact('title', 'meta_title', 'content'));

        $this->adminrender();
    }

    /**
     * @throws \Exception
     */
    public function testRegex(Request $request): void
    {
        $this->setAdminPrefs();
        $meta_title = $title = 'Collections Regex Test';

        $group = trim($request->has('group') && ! empty($request->input('group')) ? $request->input('group') : '');
        $regex = trim($request->has('regex') && ! empty($request->input('regex')) ? $request->input('regex') : '');
        $limit = ($request->has('limit') && is_numeric($request->input('limit')) ? $request->input('limit') : 50);
        $this->smarty->assign(['group' => $group, 'regex' => $regex, 'limit' => $limit]);

        if ($group && $regex) {
            $this->smarty->assign('data', (new Regexes(['Settings' => null, 'Table_Name' => 'collection_regexes']))->testCollectionRegex($group, $regex, $limit));
        }

        $content = $this->smarty->fetch('collection_regexes-test.tpl');

        $this->smarty->assign(compact('title', 'meta_title', 'content'));

        $this->adminrender();
    }
}
