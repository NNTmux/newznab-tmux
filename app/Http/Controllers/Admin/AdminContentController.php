<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use App\Models\User;
use Blacklight\Contents;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminContentController extends BasePageController
{
    /**
     * @throws \Exception
     */
    public function index()
    {
        $this->setAdminPrefs();
        $contentList = (new Contents)->getAll();

        $this->viewData = array_merge($this->viewData, [
            'contentlist' => $contentList,
            'meta_title' => 'Content List',
            'title' => 'Content List',
        ]);

        return view('admin.content.index', $this->viewData);
    }

    /**
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Foundation\Application|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector|\Illuminate\View\View
     *
     * @throws \Exception
     */
    public function create(Request $request)
    {
        $this->setAdminPrefs();
        $contents = new Contents;
        $meta_title = 'Content Add';

        // Set the current action.
        $action = $request->input('action') ?? 'view';

        $content = [
            'id' => '',
            'title' => '',
            'url' => '',
            'body' => '',
            'metadescription' => '',
            'metakeywords' => '',
            'contenttype' => '',
            'status' => '',
            'ordinal' => '',
            'created_at' => '',
            'role' => '',
        ];

        switch ($action) {
            case 'add':
                $meta_title = 'Content Add';
                $content['status'] = '1';
                $content['contenttype'] = '2';
                break;

            case 'submit':
                // Validate and add or update.
                if ($request->missing('id') || empty($request->input('id'))) {
                    $returnid = $contents->add($request->all());
                } else {
                    $content = $contents->update($request->all());
                    $returnid = $content['id'];
                }

                return redirect('admin/content-add?id='.$returnid);

            case 'view':
            default:
                if ($request->has('id')) {
                    $meta_title = 'Content Edit';
                    $id = $request->input('id');

                    $content = $contents->getByID($id, User::ROLE_ADMIN);
                }
                break;
        }

        $contenttypelist = [1 => 'Useful Link', 2 => 'Article', 3 => 'Homepage'];
        $rolelist = [1 => 'Everyone', 2 => 'Logged in Users', 3 => 'Admins'];

        $this->viewData = array_merge($this->viewData, [
            'status_ids' => [1, 0],
            'status_names' => ['Enabled', 'Disabled'],
            'yesno_ids' => [1, 0],
            'yesno_names' => ['Yes', 'No'],
            'contenttypelist' => $contenttypelist,
            'content' => $content,
            'rolelist' => $rolelist,
            'meta_title' => $meta_title,
            'title' => $meta_title,
        ]);

        return view('admin.content.add', $this->viewData);
    }

    public function destroy(Request $request): \Illuminate\Routing\Redirector|RedirectResponse|\Illuminate\Contracts\Foundation\Application
    {
        if ($request->has('id')) {
            $contents = new Contents;
            $contents->delete($request->input('id'));
        }

        $referrer = $request->server('HTTP_REFERER');

        return redirect()->to($referrer);
    }
}
