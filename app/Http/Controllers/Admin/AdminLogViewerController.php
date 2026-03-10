<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use App\Http\Requests\Admin\AdminLogViewerRequest;
use App\Services\LogViewerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use RuntimeException;

class AdminLogViewerController extends BasePageController
{
    public function index(AdminLogViewerRequest $request, LogViewerService $logViewer): View|RedirectResponse
    {
        $this->setAdminPrefs();

        $validated = $request->validated();
        $availableLogs = $logViewer->availableLogs();
        $selectedPath = $validated['file'] ?? null;

        if (($selectedPath === null || $selectedPath === '') && $availableLogs !== []) {
            $selectedPath = $availableLogs[0]['path'];
        }

        if ($selectedPath !== null && $selectedPath !== '' && ! $logViewer->isAvailableLog($selectedPath)) {
            return redirect()
                ->route('admin.logs.index')
                ->with('error', 'Selected log file is not available.');
        }

        $selectedLog = $selectedPath ? $logViewer->findLog($selectedPath) : null;
        $search = trim((string) ($validated['search'] ?? ''));
        $lineLimit = (int) ($validated['lines'] ?? LogViewerService::DEFAULT_LINES);
        $tailView = null;
        $searchResults = null;
        $searchMatchCount = 0;

        if ($selectedLog !== null) {
            try {
                if ($search !== '') {
                    $searchData = $logViewer->searchLog(
                        $selectedLog['path'],
                        $search,
                        $request->integer('page', 1),
                        $lineLimit,
                        $request->query()
                    );

                    $searchResults = $searchData['paginator'];
                    $searchMatchCount = $searchData['total_matches'];
                } else {
                    $tailView = $logViewer->readLatestLines($selectedLog['path'], $lineLimit);
                }
            } catch (RuntimeException $exception) {
                return redirect()
                    ->route('admin.logs.index')
                    ->with('error', $exception->getMessage());
            }
        }

        return view('admin.logs.index', [
            'availableLogs' => $availableLogs,
            'selectedFile' => $selectedLog['path'] ?? '',
            'selectedLog' => $selectedLog,
            'search' => $search,
            'lines' => $lineLimit,
            'lineOptions' => LogViewerService::DISPLAY_LINE_OPTIONS,
            'tailView' => $tailView,
            'searchResults' => $searchResults,
            'searchMatchCount' => $searchMatchCount,
            'isSearchMode' => $search !== '',
            'title' => 'Log Viewer',
            'page_title' => 'Log Viewer',
            'meta_title' => 'Log Viewer',
            'meta_description' => 'Browse and search application logs.',
        ]);
    }
}
