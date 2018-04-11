<?php

namespace App\Http\Controllers;

use App\Models\Release;
use App\Models\UsersRelease;
use Illuminate\Support\Facades\Auth;

class CartController extends BasePageController
{
    /**
     * @throws \Exception
     */
    public function index()
    {
        $this->setPrefs();
        $meta_title = 'My Download Basket';
        $meta_keywords = 'search,add,to,cart,download,basket,nzb,description,details';
        $meta_description = 'Manage Your Download Basket';

        $results = UsersRelease::getCart(Auth::id());
        $this->smarty->assign('results', $results);

        $content = $this->smarty->fetch('cart.tpl');
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
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     * @throws \Exception
     */
    public function store($id)
    {
        $this->setPrefs();
        $guids = explode(',', $id);
        $data = Release::getByGuid($guids);

        if (! $data) {
            $this->show404();
        }

        foreach ($data as $d) {
            UsersRelease::addCart(Auth::id(), $d['id']);
        }

        return redirect('/cart/index');
    }

    /**
     * @param $id
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     * @throws \Exception
     */
    public function destroy($id)
    {
        $this->setPrefs();
        $ids = null;
        if (! empty($id)) {
            $ids = (array) $id;
        } elseif (\is_array($id)) {
            $ids = $id;
        }

        if ($ids !== null && UsersRelease::delCartByGuid($ids, Auth::id())) {
            return redirect('/cart/index');
        }

        if (! $id) {
            return redirect('/cart/index');
        }

        return redirect('/cart/index');
    }
}
