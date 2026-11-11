<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use App\Services\ConsoleService;
use App\Services\GenreService;
use App\Services\ReleaseImageService;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminConsoleController extends BasePageController
{
    protected ConsoleService $consoleService;

    protected ReleaseImageService $imageService;

    public function __construct(ConsoleService $consoleService, ReleaseImageService $imageService)
    {
        parent::__construct();
        $this->consoleService = $consoleService;
        $this->imageService = $imageService;
    }

    /**
     * Display a listing of console games
     */
    public function index(Request $request): View
    {
        $this->setAdminPrefs();

        $meta_title = $title = 'Console List';

        $lastSearch = trim($this->scalarInput($request, 'search'));
        $consoleList = DB::table('consoleinfo')
            ->when($lastSearch !== '', static function (Builder $query) use ($lastSearch): void {
                $query->where(static function (Builder $query) use ($lastSearch): void {
                    $query->whereLike('title', '%'.$lastSearch.'%')
                        ->orWhereLike('platform', '%'.$lastSearch.'%');
                });
            })
            ->orderByDesc('created_at')
            ->paginate(config('nntmux.items_per_page'))
            ->withQueryString();

        return view('admin.console.index', compact('consoleList', 'title', 'meta_title', 'lastSearch'));
    }

    /**
     * Show the form for editing a console game
     */
    public function edit(Request $request): View|RedirectResponse
    {
        $this->setAdminPrefs();
        $gen = new GenreService;
        $meta_title = $title = 'Console Edit';

        // set the current action
        $action = $this->formAction($request);

        if ($request->has('id')) {
            $id = $request->input('id');
            $con = $this->consoleService->getConsoleInfo($id);

            if (! $con) {
                abort(404);
            }

            switch ($action) {
                case 'submit':
                    $coverDirectory = storage_path('covers/console/');

                    if ($request->hasFile('cover') && $request->file('cover')->isValid()) {
                        $this->imageService->saveUploadedImage((string) $id, $request->file('cover'), $coverDirectory);
                    }

                    $hasCover = (int) $this->imageService->imageExists($coverDirectory, (string) $id);
                    $salesrank = (empty($request->input('salesrank')) || ! ctype_digit($request->input('salesrank'))) ? null : $request->input('salesrank');
                    $releasedate = (empty($request->input('releasedate')) || ! strtotime($request->input('releasedate')))
                        ? $con['releasedate']
                        : Carbon::parse($request->input('releasedate'))->timestamp;

                    $this->consoleService->update(
                        $id,
                        $request->input('title'),
                        $request->input('asin'),
                        $request->input('url'),
                        $salesrank,
                        $request->input('platform'),
                        $request->input('publisher'),
                        $releasedate,
                        $request->input('esrb'),
                        $hasCover,
                        $request->input('genre')
                    );

                    return redirect()->route('admin.console-list')->with('success', 'Console game updated successfully');

                case 'view':
                default:
                    $genres = $gen->getGenres((string) GenreService::CONSOLE_TYPE);

                    return view('admin.console.edit', compact('con', 'genres', 'title', 'meta_title'));
            }
        }

        return redirect()->route('admin.console-list');
    }
}
