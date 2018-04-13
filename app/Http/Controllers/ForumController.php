<?php

namespace App\Http\Controllers;

use App\Models\Settings;
use App\Models\Forumpost;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ForumController extends BasePageController
{
    /**
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     * @throws \Exception
     */
    public function forum(Request $request)
    {
        $this->setPrefs();
        if ($this->isPostBack() && $request->has('addMessage') && $request->has('addSubject')) {
            Forumpost::add(0, Auth::id(), $request->input('addSubject'), $request->input('addMessage'));

            return redirect('forum');
        }

        $lock = $unlock = null;

        if ($request->has('lock')) {
            $lock = $request->input('lock');
        }

        if ($request->has('unlock')) {
            $unlock = $request->input('unlock');
        }

        if ($lock !== null) {
            Forumpost::lockUnlockTopic($lock, 1);

            return redirect('forum');
        }

        if ($unlock !== null) {
            Forumpost::lockUnlockTopic($unlock, 0);

            return redirect('forum');
        }

        $results = Forumpost::getBrowseRange();

        $this->smarty->assign('privateprofiles', (int) Settings::settingValue('..privateprofiles') === 1);

        $this->smarty->assign('results', $results);

        $meta_title = 'Forum';
        $meta_keywords = 'forum,chat,posts';
        $meta_description = 'Forum';

        $content = $this->smarty->fetch('forum.tpl');

        $this->smarty->assign(
            [
                'content' => $content,
                'meta_title' => $meta_title,
                'meta_keywords' => $meta_keywords,
                'meta_description' => $meta_description,
            ]
        );
        $this->pagerender();
    }

    /**
     * @param $id
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     * @throws \Exception
     */
    public function getPosts($id, Request $request)
    {
        $this->setPrefs();

        if ($request->has('addMessage') && $this->isPostBack()) {
            Forumpost::add($id, Auth::id(), '', $request->input('addMessage'));

            return redirect('forumpost/'.$id.'#last');
        }

        $results = Forumpost::getPosts($id);
        if (\count($results) === 0) {
            return redirect('forum');
        }

        $meta_title = 'Forum Post';
        $meta_keywords = 'view,forum,post,thread';
        $meta_description = 'View forum post';

        $this->smarty->assign('results', $results);
        $this->smarty->assign('privateprofiles', (int) Settings::settingValue('..privateprofiles') === 1);

        $content = $this->smarty->fetch('forumpost.tpl');

        $this->smarty->assign(
            [
                'content' => $content,
                'meta_title' => $meta_title,
                'meta_keywords' => $meta_keywords,
                'meta_description' => $meta_description,
            ]
        );
        $this->pagerender();
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function deleteTopic(Request $request)
    {
        $id = $request->input('id') + 0;

        if ($id !== null) {
            Forumpost::deleteParent($id);
            return redirect('forum');
        }
        return redirect('forum');
    }
}
