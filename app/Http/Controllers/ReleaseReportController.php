<?php

declare(strict_types=1);

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
        $validated = $request->validate([
            'release_id' => 'required|integer',
            'reason' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        $releaseId = (int) $validated['release_id'];
        $userId = $this->authenticatedUserId();

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
            'reason' => $validated['reason'],
            'description' => $validated['description'] ?? null,
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
        $validated = $request->validate([
            'release_id' => 'required|integer',
        ]);

        $releaseId = (int) $validated['release_id'];
        $userId = $this->authenticatedUserId();

        $hasReported = ReleaseReport::hasUserReported($releaseId, $userId);

        return response()->json([
            'has_reported' => $hasReported,
        ]);
    }

    /**
     * Normalize the authenticated user identifier to an integer.
     */
    private function authenticatedUserId(): int
    {
        $userId = Auth::id();

        if (is_int($userId)) {
            return $userId;
        }

        if (is_string($userId) && ctype_digit($userId)) {
            return (int) $userId;
        }

        abort(401, 'Authentication required.');
    }
}
