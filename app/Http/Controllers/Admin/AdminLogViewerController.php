<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use App\Http\Requests\Admin\AdminLogFileRequest;
use App\Http\Requests\Admin\AdminLogSearchRequest;
use App\Http\Requests\Admin\AdminLogViewerRequest;
use App\Services\LogViewer\LogEntry;
use App\Services\LogViewer\LogEntryParser;
use App\Services\LogViewer\LogFile;
use App\Services\LogViewer\LogFileRepository;
use App\Services\LogViewer\LogReader;
use App\Services\LogViewer\LogSearcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AdminLogViewerController extends BasePageController
{
    private const int JSON_OPTIONS = JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    public function index(AdminLogViewerRequest $request, LogFileRepository $logs, LogSearcher $searcher): View|RedirectResponse
    {
        $this->setAdminPrefs();

        $selectedPath = (string) ($request->validated('file') ?? '');

        if ($selectedPath !== '' && $logs->find($selectedPath) === null) {
            return redirect()
                ->route('admin.logs.index')
                ->with('error', 'Selected log file is not available.');
        }

        $files = $this->fileList($logs);

        return view('admin.logs.index', [
            'files' => $files,
            'initialFile' => $selectedPath !== '' ? $selectedPath : ($files[0]['path'] ?? ''),
            'initialQuery' => (string) ($request->validated('q') ?? ''),
            'engine' => $searcher->engine(),
            'levels' => LogEntryParser::LEVELS,
            'limitOptions' => AdminLogFileRequest::LIMIT_OPTIONS,
            'defaultLimit' => AdminLogFileRequest::DEFAULT_LIMIT,
            'maxFilesPerSearch' => (int) config('nntmux.log_viewer.max_files_per_search', 25),
            'title' => 'Log Viewer',
            'page_title' => 'Log Viewer',
            'meta_title' => 'Log Viewer',
            'meta_description' => 'Browse and search application logs.',
        ]);
    }

    public function files(LogFileRepository $logs, LogSearcher $searcher): JsonResponse
    {
        return $this->json([
            'files' => $this->fileList($logs),
            'engine' => $searcher->engine(),
        ]);
    }

    public function entries(AdminLogFileRequest $request, LogFileRepository $logs, LogReader $reader): JsonResponse
    {
        $file = $this->resolve($request, $logs);
        $before = $request->validated('before');

        try {
            $result = $reader->latest(
                $file,
                $before === null ? null : (int) $before,
                (int) ($request->validated('limit') ?? AdminLogFileRequest::DEFAULT_LIMIT),
                $request->levels(),
            );
        } catch (RuntimeException $exception) {
            return $this->json(['message' => $exception->getMessage()], 409);
        }

        return $this->json([
            'file' => $file->toArray($this->guardMinutes()),
            'entries' => array_map(static fn (LogEntry $entry): array => $entry->toArray(), $result['entries']),
            'before' => $result['before'],
            'scanned_bytes' => $result['scanned_bytes'],
            'budget_exhausted' => $result['budget_exhausted'],
        ]);
    }

    public function entry(AdminLogFileRequest $request, LogFileRepository $logs, LogReader $reader): JsonResponse
    {
        $request->validate(['offset' => ['required', 'integer', 'min:0']]);
        $file = $this->resolve($request, $logs);

        try {
            $entry = $reader->entryAt($file, (int) $request->validated('offset'));
        } catch (RuntimeException $exception) {
            return $this->json(['message' => $exception->getMessage()], 409);
        }

        if ($entry === null) {
            return $this->json(['message' => 'The log file has changed; reload it to see this entry.'], 404);
        }

        return $this->json(['entry' => $entry->toArray()]);
    }

    public function search(AdminLogSearchRequest $request, LogFileRepository $logs, LogSearcher $searcher): JsonResponse
    {
        $files = [];

        foreach ((array) $request->validated('files') as $path) {
            $files[] = $logs->find((string) $path) ?? abort(404, 'Log file not found.');
        }

        $before = $request->validated('before');

        return $this->json($searcher->search(
            $request->searchQuery(),
            $files,
            $before === null ? null : (int) $before,
            max(1, (int) config('nntmux.log_viewer.max_results_per_file', 100)),
        ));
    }

    public function download(AdminLogFileRequest $request, LogFileRepository $logs): BinaryFileResponse
    {
        $file = $this->resolve($request, $logs);

        return response()->download($file->absolutePath, $file->name, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function truncate(AdminLogFileRequest $request, LogFileRepository $logs): JsonResponse
    {
        $file = $this->resolve($request, $logs);

        try {
            $logs->truncate($file);
        } catch (RuntimeException $exception) {
            return $this->json(['message' => $exception->getMessage()], 409);
        }

        $this->audit($request, 'Admin truncated log file', $file);

        return $this->json([
            'ok' => true,
            'file' => $logs->find($file->path)?->toArray($this->guardMinutes()),
        ]);
    }

    public function destroy(AdminLogFileRequest $request, LogFileRepository $logs): JsonResponse
    {
        $file = $this->resolve($request, $logs);
        $guardMinutes = $this->guardMinutes();

        if ($file->isActive($guardMinutes)) {
            return $this->json([
                'message' => "This log was written to in the last {$guardMinutes} minutes and is probably still open by a running process. Truncate it instead.",
            ], 409);
        }

        try {
            $logs->delete($file);
        } catch (RuntimeException $exception) {
            return $this->json(['message' => $exception->getMessage()], 409);
        }

        $this->audit($request, 'Admin deleted log file', $file);

        return $this->json(['ok' => true]);
    }

    private function resolve(AdminLogFileRequest $request, LogFileRepository $logs): LogFile
    {
        return $logs->find((string) $request->validated('file')) ?? abort(404, 'Log file not found.');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fileList(LogFileRepository $logs): array
    {
        $guardMinutes = $this->guardMinutes();

        return array_map(static fn (LogFile $file): array => $file->toArray($guardMinutes), $logs->all());
    }

    private function guardMinutes(): int
    {
        return max(0, (int) config('nntmux.log_viewer.delete_guard_minutes', 10));
    }

    private function audit(Request $request, string $message, LogFile $file): void
    {
        Log::channel('admin')->warning($message, [
            'user_id' => $request->user()?->getAuthIdentifier(),
            'username' => $request->user()?->username,
            'file' => $file->path,
            'size_before' => $file->size,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function json(array $data, int $status = 200): JsonResponse
    {
        return response()->json($data, $status, [], self::JSON_OPTIONS);
    }
}
