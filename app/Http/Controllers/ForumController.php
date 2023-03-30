<?php

namespace App\Http\Controllers;

use App\Models\Forumpost;
use App\Models\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ForumController extends BasePageController
{
    /**
     * @return \Illuminate\Http\RedirectResponse|void
     *
     * @throws \Exception
     */
    public function forum(Request $request)
    {
        $this->setPreferences();
        if ($this->isPostBack($request) && $request->has('addMessage') && $request->has('addSubject')) {
            Forumpost::add(0, $this->userdata->id, $request->input('addSubject'), $request->input('addMessage'));

            return redirect()->to('forum');
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

            return redirect()->to('forum');
        }

        if ($unlock !== null) {
            Forumpost::lockUnlockTopic($unlock, 0);

            return redirect()->to('forum');
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
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector|void
     *
     * @throws \Exception
     */
    public function getPosts($id, Request $request): RedirectResponse
    {
        $this->setPreferences();

        if ($request->has('addMessage') && $this->isPostBack($request)) {
            Forumpost::add($id, $this->userdata->id, '', $request->input('addMessage'));

            return redirect('forumpost/'.$id.'#last');
        }

        $results = Forumpost::getPosts($id);
        if (\count($results) === 0) {
            return redirect()->to('forum');
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

    public function deleteTopic(Request $request): \Illuminate\Routing\Redirector|RedirectResponse|\Illuminate\Contracts\Foundation\Application
    {
        $id = $request->input('id') + 0;

        if ($id !== null) {
            Forumpost::deleteParent($id);

            return redirect()->to('forum');
        }

        return redirect()->to('forum');
    }

    /**
     * @throws \Exception
     */
    public function edit(Request $request): void
    {
        $this->setPreferences();

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
     * @throws \Exception
     */
    public function destroy($id): RedirectResponse
    {
        $this->setPreferences();

        if ($id) {
            Forumpost::deletePost($id);
        }

        return redirect()->back();
    }
}
