<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use Blacklight\Console;
use Blacklight\Genres;
use Blacklight\utility\Utility;
use Illuminate\Http\Request;

class AdminConsoleController extends BasePageController
{
    /**
     * @throws \Exception
     */
    public function index(): void
    {
        $this->setAdminPrefs();

        $meta_title = $title = 'Console List';

        $consoleList = Utility::getRange('consoleinfo');

        $this->smarty->assign('consolelist', $consoleList);

        $content = $this->smarty->fetch('console-list.tpl');

        $this->smarty->assign(compact('title', 'meta_title', 'content'));

        $this->adminrender();
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\RedirectResponse|void
     * @throws \Exception
     */
    public function edit(Request $request)
    {
        $this->setAdminPrefs();
        $console = new Console(['Settings' => null]);
        $gen = new Genres();
        $meta_title = $title = 'Console Edit';

        // set the current action
        $action = $request->input('action') ?? 'view';

        if ($request->has('id')) {
            $id = $request->input('id');
            $con = $console->getConsoleInfo($id);

            if (! $con) {
                $this->show404();
            }

            switch ($action) {
                case 'submit':
                    $coverLoc = storage_path('covers/console/'.$id.'.jpg');

                    if ($_FILES['cover']['size'] > 0) {
                        $tmpName = $_FILES['cover']['tmp_name'];
                        $file_info = getimagesize($tmpName);
                        if (! empty($file_info)) {
                            move_uploaded_file($_FILES['cover']['tmp_name'], $coverLoc);
                        }
                    }

                    $request->merge(['cover' => file_exists($coverLoc) ? 1 : 0]);
                    $request->merge(['salesrank' => (empty($request->input('salesrank')) || ! ctype_digit($request->input('salesrank'))) ? 'null' : $request->input('salesrank')]);
                    $request->merge(['releasedate' => (empty($request->input('releasedate')) || ! strtotime($request->input('releasedate'))) ? $con['releasedate'] : Carbon::parse($request->input('releasedate'))->timestamp]);

                    $console->update($id, $request->input('title'), $request->input('asin'), $request->input('url'), $request->input('salesrank'), $request->input('platform'), $request->input('publisher'), $request->input('releasedate'), $request->input('esrb'), $request->input('cover'), $request->input('genre'));

                    return redirect()->to('admin/console-list.');
                    break;
                case 'view':
                default:
                    $this->smarty->assign('console', $con);
                    $this->smarty->assign('genres', $gen->getGenres(Genres::CONSOLE_TYPE));
                    break;
            }
        }

        $content = $this->smarty->fetch('console-edit.tpl');

        $this->smarty->assign(compact('title', 'meta_title', 'content'));

        $this->adminrender();
    }
}
