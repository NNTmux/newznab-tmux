<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use Blacklight\Console;
use Blacklight\Genres;
use Blacklight\utility\Utility;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class AdminConsoleController extends BasePageController
{
    /**
     * Display a listing of console games
     */
    public function index(): View
    {
        $this->setAdminPrefs();

        $meta_title = $title = 'Console List';

        $consoleList = Utility::getRange('consoleinfo');

        return view('admin.console.index', compact('consoleList', 'title', 'meta_title'));
    }

    /**
     * Show the form for editing a console game
     */
    public function edit(Request $request): View|RedirectResponse
    {
        $this->setAdminPrefs();
        $console = new Console(['Settings' => null]);
        $gen = new Genres;
        $meta_title = $title = 'Console Edit';

        // set the current action
        $action = $request->input('action', 'view');

        if ($request->has('id')) {
            $id = $request->input('id');
            $con = $console->getConsoleInfo($id);

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

                    $console->update(
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
                    $genres = $gen->getGenres(Genres::CONSOLE_TYPE);

                    return view('admin.console.edit', compact('con', 'genres', 'title', 'meta_title'));
            }
        }

        return redirect()->route('admin.console-list');
    }
}
