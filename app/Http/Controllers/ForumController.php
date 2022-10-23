<?php

namespace App\Http\Controllers;

use App\Models\Forumpost;
use App\Models\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ForumController extends BasePageController
{
    /**
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     *
     * @throws \Exception
     */
    public function forum(Request $request): \Illuminate\Routing\Redirector|\Illuminate\Http\RedirectResponse
    {
        $this->setPrefs();
        if ($this->isPostBack() && $request->has('addMessage') && $request->has('addSubject')) {
            Forumpost::add(0, $this->userdata->id, $request->input('addSubject'), $request->input('addMessage'));

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
            compact('content', 'meta_title', 'meta_keywords', 'meta_description')
        );
        $this->pagerender();
    }

    /**
     * @param $id
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     *
     * @throws \Exception
     */
    public function getPosts($id, Request $request): \Illuminate\Routing\Redirector|\Illuminate\Http\RedirectResponse
    {
        $this->setPrefs();

        if ($request->has('addMessage') && $this->isPostBack()) {
            Forumpost::add($id, $this->userdata->id, '', $request->input('addMessage'));

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
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function deleteTopic(Request $request): \Illuminate\Routing\Redirector|\Illuminate\Http\RedirectResponse
    {
        $id = $request->input('id') + 0;

        if ($id !== null) {
            Forumpost::deleteParent($id);

            return redirect('forum');
        }

        return redirect('forum');
    }

    /**
     * @param  \Illuminate\Http\Request  $request
     *
     * @throws \Exception
     */
    public function edit(Request $request): void
    {
        $this->setPrefs();

        if ($request->has('id') && ! empty($request->input('addMessage'))) {
            $parent = Forumpost::getPost($request->input('id'));
            Forumpost::editPost($request->input('id'), $request->input('addMessage'), Auth::id());
            if ((int) $parent['parentid'] !== 0) {
                header('/forumpost/'.$parent['parentid'].'#last');
            } else {
                header('/forumpost/'.$request->input('id'));
            }
        }

        $result = Forumpost::getPost($request->input('id'));

        $meta_title = 'Edit forum Post';
        $meta_keywords = 'edit, view,forum,post,thread';
        $meta_description = 'Edit forum post';

        $this->smarty->assign('result', $result);

        $content = $this->smarty->fetch('post_edit.tpl');
        $this->smarty->assign(
            compact('content', 'meta_title', 'meta_keywords', 'meta_description')
        );
        $this->pagerender();
    }

    /**
     * @param $id
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     *
     * @throws \Exception
     */
    public function destroy($id): \Illuminate\Routing\Redirector|\Illuminate\Http\RedirectResponse
    {
        $this->setPrefs();

        if ($id) {
            Forumpost::deletePost($id);
        }

        return redirect()->back();
    }
}
