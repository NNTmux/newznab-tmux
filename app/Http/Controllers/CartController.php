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
    public function index()
    {
        $this->setPreferences();

        $results = UsersRelease::getCart(Auth::id());

        $this->viewData = array_merge($this->viewData, [
            'results' => $results,
            'meta_title' => 'My Download Basket',
            'meta_keywords' => 'search,add,to,cart,download,basket,nzb,description,details',
            'meta_description' => 'Manage Your Download Basket',
        ]);

        return view('cart.index', $this->viewData);
    }

    /**
     * @throws \Exception
     */
    public function store(Request $request): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $this->setPreferences();
        $guids = explode(',', $request->input('id'));

        $data = Release::query()->whereIn('guid', $guids)->select(['id'])->get();

        if ($data->isEmpty()) {
            return $request->ajax() || $request->wantsJson()
                ? response()->json(['success' => false, 'message' => 'No releases found'], 404)
                : redirect()->to('/cart/index');
        }

        $addedCount = 0;
        foreach ($data as $d) {
            $check = UsersRelease::whereReleasesId($d['id'])->first();
            if (empty($check)) {
                UsersRelease::addCart($this->userdata->id, $d['id']);
                $addedCount++;
            }
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $addedCount > 0 ? "{$addedCount} item(s) added to cart" : 'Items already in cart',
                'cartCount' => UsersRelease::where('users_id', $this->userdata->id)->count(),
            ]);
        }

        return redirect()->to('/cart/index');
    }

    /**
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
