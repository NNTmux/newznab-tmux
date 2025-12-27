<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use App\Services\ConsoleService;
use App\Services\GenreService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class AdminConsoleController extends BasePageController
{
    protected ConsoleService $consoleService;

    public function __construct(ConsoleService $consoleService)
    {
        parent::__construct();
        $this->consoleService = $consoleService;
    }

    /**
     * Display a listing of console games
     */
    public function index(): View
    {
        $this->setAdminPrefs();

        $meta_title = $title = 'Console List';

        $consoleList = getRange('consoleinfo');

        return view('admin.console.index', compact('consoleList', 'title', 'meta_title'));
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
        $action = $request->input('action', 'view');

        if ($request->has('id')) {
            $id = $request->input('id');
            $con = $this->consoleService->getConsoleInfo($id);

            if (! $con) {
                abort(404);
            }

            switch ($action) {
                case 'submit':
                    $coverLoc = storage_path('covers/console/'.$id.'.jpg');

                    if ($request->hasFile('cover') && $request->file('cover')->isValid()) {
                        $uploadedFile = $request->file('cover');
                        $file_info = getimagesize($uploadedFile->getRealPath());
                        if (! empty($file_info)) {
                            $uploadedFile->move(storage_path('covers/console'), $id.'.jpg');
                        }
                    }

                    $hasCover = file_exists($coverLoc) ? 1 : 0;
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
