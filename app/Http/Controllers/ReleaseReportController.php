<?php

namespace App\Http\Controllers;

use App\Models\Release;
use App\Models\ReleaseReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReleaseReportController extends BasePageController
{
    /**
     * Submit a report for a release.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'release_id' => 'required|integer',
            'reason' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        $releaseId = $request->input('release_id');
        $userId = Auth::id();

        // Check if release exists
        $release = Release::find($releaseId);
        if (! $release) {
            return response()->json([
                'success' => false,
                'message' => 'Release not found.',
            ], 404);
        }

        // Check if user already reported this release
        if (ReleaseReport::hasUserReported($releaseId, $userId)) {
            return response()->json([
                'success' => false,
                'message' => 'You have already reported this release.',
            ], 409);
        }

        // Create the report
        ReleaseReport::create([
            'releases_id' => $releaseId,
            'users_id' => $userId,
            'reason' => $request->input('reason'),
            'description' => $request->input('description'),
            'status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Report submitted successfully. Thank you for helping improve our content.',
        ]);
    }

    /**
     * Get report reasons for the form.
     */
    public function getReasons(): JsonResponse
    {
        return response()->json([
            'reasons' => ReleaseReport::REASONS,
        ]);
    }

    /**
     * Check if user has already reported a release.
     */
    public function checkReported(Request $request): JsonResponse
    {
        $releaseId = $request->input('release_id');
        $userId = Auth::id();

        $hasReported = ReleaseReport::hasUserReported($releaseId, $userId);

        return response()->json([
            'has_reported' => $hasReported,
        ]);
    }
}
