<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use App\Models\RolePromotion;
use App\Models\RolePromotionStat;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class AdminPromotionController extends BasePageController
{
    /**
     * Display a listing of promotions.
     */
    public function index(): View
    {
        $promotions = RolePromotion::orderBy('is_active', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('admin.promotions.index', [
            'promotions' => $promotions,
        ]);
    }

    /**
     * Show the form for creating a new promotion.
     */
    public function create(): View
    {
        $customRoles = RolePromotion::getCustomRoles();

        return view('admin.promotions.create', [
            'customRoles' => $customRoles,
        ]);
    }

    /**
     * Store a newly created promotion in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'applicable_roles' => 'nullable|array',
            'applicable_roles.*' => 'exists:roles,id',
            'additional_days' => 'required|integer|min:0',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        RolePromotion::create([
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'applicable_roles' => $request->input('applicable_roles', []),
            'additional_days' => $request->input('additional_days'),
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'is_active' => $request->has('is_active'),
        ]);

        return redirect()->route('admin.promotions.index')
            ->with('success', 'Promotion created successfully.');
    }

    /**
     * Show the form for editing the specified promotion.
     */
    public function edit(int $id): View
    {
        $promotion = RolePromotion::findOrFail($id);
        $customRoles = RolePromotion::getCustomRoles();

        return view('admin.promotions.edit', [
            'promotion' => $promotion,
            'customRoles' => $customRoles,
        ]);
    }

    /**
     * Update the specified promotion in storage.
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $promotion = RolePromotion::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'applicable_roles' => 'nullable|array',
            'applicable_roles.*' => 'exists:roles,id',
            'additional_days' => 'required|integer|min:0',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $promotion->update([
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'applicable_roles' => $request->input('applicable_roles', []),
            'additional_days' => $request->input('additional_days'),
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'is_active' => $request->has('is_active'),
        ]);

        return redirect()->route('admin.promotions.index')
            ->with('success', 'Promotion updated successfully.');
    }

    /**
     * Remove the specified promotion from storage.
     */
    public function destroy(int $id): RedirectResponse
    {
        $promotion = RolePromotion::findOrFail($id);
        $promotion->delete();

        return redirect()->route('admin.promotions.index')
            ->with('success', 'Promotion deleted successfully.');
    }

    /**
     * Toggle the active status of the specified promotion.
     */
    public function toggle(int $id): RedirectResponse
    {
        $promotion = RolePromotion::findOrFail($id);
        $promotion->update(['is_active' => ! $promotion->is_active]);

        $status = $promotion->is_active ? 'activated' : 'deactivated';

        return redirect()->route('admin.promotions.index')
            ->with('success', "Promotion {$status} successfully.");
    }

    /**
     * Display overall promotion statistics.
     */
    public function statistics(Request $request): View
    {
        // Get date range filter (default to last 30 days)
        $endDate = Carbon::now();
        $startDate = Carbon::now()->subDays(30);

        if ($request->has('start_date') && $request->has('end_date')) {
            $startDate = Carbon::parse($request->input('start_date'));
            $endDate = Carbon::parse($request->input('end_date'));
        } elseif ($request->has('period')) {
            switch ($request->input('period')) {
                case '7days':
                    $startDate = Carbon::now()->subDays(7);
                    break;
                case '30days':
                    $startDate = Carbon::now()->subDays(30);
                    break;
                case '90days':
                    $startDate = Carbon::now()->subDays(90);
                    break;
                case 'year':
                    $startDate = Carbon::now()->subYear();
                    break;
                case 'all':
                    $startDate = null;
                    break;
            }
        }

        // Get all promotions with their statistics
        $promotions = RolePromotion::withCount(['statistics' => function ($query) use ($startDate, $endDate) {
            if ($startDate) {
                $query->whereBetween('applied_at', [$startDate, $endDate]);
            }
        }])
            ->with(['statistics' => function ($query) use ($startDate, $endDate) {
                if ($startDate) {
                    $query->whereBetween('applied_at', [$startDate, $endDate]);
                }
                $query->with(['user', 'role']);
            }])
            ->get();

        // Calculate overall statistics
        $overallStats = [
            'total_promotions' => $promotions->count(),
            'active_promotions' => $promotions->where('is_active', true)->count(),
            'total_applications' => $promotions->sum('statistics_count'),
            'unique_users' => RolePromotionStat::query()
                ->when($startDate, fn ($q) => $q->whereBetween('applied_at', [$startDate, $endDate]))
                ->distinct('user_id')
                ->count('user_id'),
            'total_days_added' => RolePromotionStat::query()
                ->when($startDate, fn ($q) => $q->whereBetween('applied_at', [$startDate, $endDate]))
                ->sum('days_added'),
        ];

        // Get top promotions by usage
        $topPromotions = $promotions->sortByDesc('statistics_count')->take(5);

        // Get recent activity
        $recentActivity = RolePromotionStat::with(['user', 'promotion', 'role'])
            ->when($startDate, fn ($q) => $q->whereBetween('applied_at', [$startDate, $endDate]))
            ->latest('applied_at')
            ->limit(10)
            ->get();

        // Get statistics by role
        $statsByRole = RolePromotionStat::query()
            ->selectRaw('role_id, COUNT(*) as total_upgrades, SUM(days_added) as total_days, COUNT(DISTINCT user_id) as unique_users')
            ->when($startDate, fn ($q) => $q->whereBetween('applied_at', [$startDate, $endDate]))
            ->groupBy('role_id')
            ->with('role')
            ->get();

        return view('admin.promotions.statistics', [
            'promotions' => $promotions,
            'overallStats' => $overallStats,
            'topPromotions' => $topPromotions,
            'recentActivity' => $recentActivity,
            'statsByRole' => $statsByRole,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'selectedPeriod' => $request->input('period', '30days'),
        ]);
    }

    /**
     * Display statistics for a specific promotion.
     */
    public function showStatistics(int $id, Request $request): View
    {
        $promotion = RolePromotion::findOrFail($id);

        // Get date range filter
        $endDate = Carbon::now();
        $startDate = Carbon::now()->subDays(30);

        if ($request->has('start_date') && $request->has('end_date')) {
            $startDate = Carbon::parse($request->input('start_date'));
            $endDate = Carbon::parse($request->input('end_date'));
        } elseif ($request->has('period')) {
            switch ($request->input('period')) {
                case '7days':
                    $startDate = Carbon::now()->subDays(7);
                    break;
                case '30days':
                    $startDate = Carbon::now()->subDays(30);
                    break;
                case '90days':
                    $startDate = Carbon::now()->subDays(90);
                    break;
                case 'year':
                    $startDate = Carbon::now()->subYear();
                    break;
                case 'all':
                    $startDate = null;
                    break;
            }
        }

        // Get promotion statistics
        $stats = $promotion->getStatisticsForPeriod($startDate ?? Carbon::createFromTimestamp(0), $endDate);
        $statsByRole = $promotion->getStatisticsByRole();

        // Get applications with users
        $applications = RolePromotionStat::forPromotion($id)
            ->when($startDate, fn ($q) => $q->whereBetween('applied_at', [$startDate, $endDate]))
            ->with(['user', 'role'])
            ->latest('applied_at')
            ->paginate(20);

        // Get daily statistics for chart
        $dailyStats = RolePromotionStat::forPromotion($id)
            ->when($startDate, fn ($q) => $q->whereBetween('applied_at', [$startDate, $endDate]))
            ->selectRaw('DATE(applied_at) as date, COUNT(*) as count, SUM(days_added) as days')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return view('admin.promotions.show-statistics', [
            'promotion' => $promotion,
            'stats' => $stats,
            'statsByRole' => $statsByRole,
            'applications' => $applications,
            'dailyStats' => $dailyStats,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'selectedPeriod' => $request->input('period', '30days'),
        ]);
    }
}
