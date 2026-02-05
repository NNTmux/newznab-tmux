<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use App\Models\Release;
use App\Models\ReleaseReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AdminReleaseReportController extends BasePageController
{
    /**
     * Display a listing of release reports.
     */
    public function index(Request $request): View
    {
        $this->setAdminPrefs();

        $status = $request->input('status', 'pending');
        $reportsList = ReleaseReport::getReportsRange($status, 50);
        $statusCounts = ReleaseReport::getCountByStatus();

        $meta_title = $title = 'Release Reports';

        return view('admin.release-reports.index', compact(
            'reportsList',
            'statusCounts',
            'status',
            'title',
            'meta_title'
        ));
    }

    /**
     * Update report status.
     */
    public function updateStatus(Request $request, int $id): RedirectResponse
    {
        $request->validate([
            'status' => 'required|in:pending,reviewed,resolved,dismissed',
        ]);

        $report = ReleaseReport::findOrFail($id);
        $report->update([
            'status' => $request->input('status'),
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Report status updated successfully.');
    }

    /**
     * Delete the reported release and resolve the report.
     */
    public function deleteRelease(int $id): RedirectResponse
    {
        $report = ReleaseReport::with('release')->findOrFail($id);

        if ($report->release) {
            $releaseName = $report->release->searchname;
            $releaseId = $report->releases_id;

            // Delete the release
            Release::where('id', $releaseId)->delete();

            // Update all reports for this release to resolved
            ReleaseReport::where('releases_id', $releaseId)
                ->update([
                    'status' => 'resolved',
                    'reviewed_by' => Auth::id(),
                    'reviewed_at' => now(),
                ]);

            return redirect()->back()->with('success', "Release '{$releaseName}' deleted and all related reports resolved.");
        }

        return redirect()->back()->with('error', 'Release not found or already deleted.');
    }

    /**
     * Dismiss a report.
     */
    public function dismiss(int $id): RedirectResponse
    {
        $report = ReleaseReport::findOrFail($id);
        $report->update([
            'status' => 'dismissed',
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Report dismissed successfully.');
    }

    /**
     * Revert a resolved or dismissed report back to reviewed status.
     */
    public function revert(int $id): RedirectResponse
    {
        $report = ReleaseReport::findOrFail($id);

        if (! in_array($report->status, ['resolved', 'dismissed'])) {
            return redirect()->back()->with('error', 'Only resolved or dismissed reports can be reverted.');
        }

        $report->update([
            'status' => 'reviewed',
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Report reverted to reviewed status successfully.');
    }

    /**
     * Bulk update report statuses.
     */
    public function bulkAction(Request $request): RedirectResponse
    {
        $request->validate([
            'action' => 'required|in:dismiss,resolve,reviewed,delete,revert',
            'report_ids' => 'required|array',
            'report_ids.*' => 'integer',
        ]);

        $action = $request->input('action');
        $reportIds = $request->input('report_ids');

        $count = 0;

        foreach ($reportIds as $reportId) {
            $report = ReleaseReport::with('release')->find($reportId);
            if (! $report) {
                continue;
            }

            if ($action === 'delete' && $report->release) {
                $releaseId = $report->releases_id;
                Release::where('id', $releaseId)->delete();
                ReleaseReport::where('releases_id', $releaseId)
                    ->update([
                        'status' => 'resolved',
                        'reviewed_by' => Auth::id(),
                        'reviewed_at' => now(),
                    ]);
            } elseif ($action === 'dismiss') {
                $report->update([
                    'status' => 'dismissed',
                    'reviewed_by' => Auth::id(),
                    'reviewed_at' => now(),
                ]);
            } elseif ($action === 'resolve') {
                $report->update([
                    'status' => 'resolved',
                    'reviewed_by' => Auth::id(),
                    'reviewed_at' => now(),
                ]);
            } elseif ($action === 'reviewed') {
                $report->update([
                    'status' => 'reviewed',
                    'reviewed_by' => Auth::id(),
                    'reviewed_at' => now(),
                ]);
            } elseif ($action === 'revert' && in_array($report->status, ['resolved', 'dismissed'])) {
                $report->update([
                    'status' => 'reviewed',
                    'reviewed_by' => Auth::id(),
                    'reviewed_at' => now(),
                ]);
            }

            $count++;
        }

        $actionLabel = match ($action) {
            'delete' => 'deleted',
            'dismiss' => 'dismissed',
            'resolve' => 'resolved',
            'reviewed' => 'marked as reviewed',
            'revert' => 'reverted to reviewed',
            default => 'processed',
        };

        return redirect()->back()->with('success', "{$count} report(s) {$actionLabel} successfully.");
    }
}
