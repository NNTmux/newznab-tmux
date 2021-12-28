<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use App\Models\User;
use Blacklight\Contents;
use Illuminate\Http\Request;

class AdminContentController extends BasePageController
{
    /**
     * @throws \Exception
     */
    public function index()
    {
        $this->setAdminPrefs();
        $contentList = (new Contents())->getAll();
        $this->smarty->assign('contentlist', $contentList);

        $meta_title = 'Content List';

        $content = $this->smarty->fetch('content-list.tpl');

        $this->smarty->assign(compact('meta_title', 'content'));

        $this->adminrender();
    }

    /**
     * @param  Request  $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     *
     * @throws \Exception
     */
    public function create(Request $request)
    {
        $this->setAdminPrefs();
        $contents = new Contents();
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
            'showinmenu' => '',
            'status' => '',
            'ordinal' => '',
            'created_at' => '',
            'role' => '',
        ];

        switch ($action) {
            case 'add':
                $meta_title = 'Content Add';
                $content['showinmenu'] = '1';
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
                break;

            case 'view':
            default:
                if ($request->has('id')) {
                    $meta_title = 'Content Edit';
                    $id = $request->input('id');

                    $content = $contents->getByID($id, User::ROLE_ADMIN);
                }
                break;
        }

        $this->smarty->assign('status_ids', [1, 0]);
        $this->smarty->assign('status_names', ['Enabled', 'Disabled']);

        $this->smarty->assign('yesno_ids', [1, 0]);
        $this->smarty->assign('yesno_names', ['Yes', 'No']);

        $contenttypelist = [1 => 'Useful Link', 2 => 'Article', 3 => 'Homepage'];
        $this->smarty->assign('contenttypelist', $contenttypelist);

        $this->smarty->assign('content', $content);

        $rolelist = [1 => 'Everyone', 2 => 'Logged in Users', 3 => 'Admins'];
        $this->smarty->assign('rolelist', $rolelist);

        $content = $this->smarty->fetch('content-add.tpl');

        $this->smarty->assign(compact('meta_title', 'content'));

        $this->adminrender();
    }

    /**
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function destroy(Request $request)
    {
        if ($request->has('id')) {
            $contents = new Contents();
            $contents->delete($request->input('id'));
        }

        $referrer = $request->server('HTTP_REFERER');

        return redirect($referrer);
    }
}
