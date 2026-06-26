<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Release;
use App\Models\UsersRelease;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CartController extends BasePageController
{
    /**
     * @throws \Exception
     */
    public function index(): mixed
    {
        $results = UsersRelease::getCart(Auth::id())
            ->filter(fn ($item) => $item->release !== null);

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
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $guids = collect(explode(',', (string) $request->input('id')))
            ->map(static fn (string $guid): string => trim($guid))
            ->filter()
            ->unique()
            ->values();

        $releaseIds = Release::query()
            ->whereIn('guid', $guids)
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        if ($releaseIds === []) {
            return $request->ajax() || $request->wantsJson()
                ? response()->json(['success' => false, 'message' => 'No releases found'], 404)
                : redirect()->to('/cart/index');
        }

        $existingReleaseIds = UsersRelease::query()
            ->where('users_id', $this->userdata->id)
            ->whereIn('releases_id', $releaseIds)
            ->pluck('releases_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $missingReleaseIds = array_values(array_diff($releaseIds, $existingReleaseIds));
        $addedCount = UsersRelease::addCartForReleases((int) $this->userdata->id, $missingReleaseIds);

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
     * @param  array<string, mixed>  $id
     *
     * @throws \Exception
     */
    public function destroy(array|string $id): RedirectResponse
    {
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
