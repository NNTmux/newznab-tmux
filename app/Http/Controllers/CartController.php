<?php

namespace App\Http\Controllers;

use App\Models\Release;
use App\Models\UsersRelease;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CartController extends BasePageController
{
    /**
     * @throws \Exception
     */
    public function index(): void
    {
        $this->setPreferences();
        $meta_title = 'My Download Basket';
        $meta_keywords = 'search,add,to,cart,download,basket,nzb,description,details';
        $meta_description = 'Manage Your Download Basket';

        $results = UsersRelease::getCart(Auth::id());
        $this->smarty->assign('results', $results);

        $content = $this->smarty->fetch('cart.tpl');
        $this->smarty->assign(compact('content', 'meta_title', 'meta_keywords', 'meta_description'));
        $this->pagerender();
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Exception
     */
    public function store(Request $request): RedirectResponse
    {
        $this->setPreferences();
        $guids = explode(',', $request->input('id'));

        $data = Release::query()->whereIn('guid', $guids)->select(['id'])->get();

        if (empty($data)) {
            return redirect()->to('/cart/index');
        }

        foreach ($data as $d) {
            $check = UsersRelease::whereReleasesId($d['id'])->first();
            if (empty($check)) {
                UsersRelease::addCart($this->userdata->id, $d['id']);
            }
        }

        return redirect()->to('/cart/index');
    }

    /**
     * @param array|string $id
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Exception
     */
    public function destroy(array|string $id): RedirectResponse
    {
        $this->setPreferences();
        $ids = null;
        if (! empty($id) && ! \is_array($id)) {
            $ids = explode(',', $id);
        } elseif (\is_array($id)) {
            $ids = $id;
        }

        if (! empty($ids) && UsersRelease::delCartByGuid($ids, $this->userdata->id)) {
            return redirect()->to('/cart/index');
        }

        if (! $id) {
            return redirect()->to('/cart/index');
        }

        return redirect()->to('/cart/index');
    }
}
