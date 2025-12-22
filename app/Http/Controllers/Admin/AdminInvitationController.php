<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use App\Models\Invitation;
use App\Models\User;
use App\Services\InvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminInvitationController extends BasePageController
{
    protected InvitationService $invitationService;

    public function __construct(InvitationService $invitationService)
    {
        parent::__construct();
        $this->invitationService = $invitationService;
    }

    /**
     * Display all invitations statistics and management page
     */
    public function index(Request $request): View
    {
        $this->setAdminPrefs();

        $meta_title = $title = 'Invitation Management';

        // Get filter parameters
        $status = $request->get('status', '');
        $invited_by = $request->get('invited_by', '');
        $email = $request->get('email', '');
        $orderBy = $request->get('ob', 'created_at_desc');

        // Build query
        $query = Invitation::with(['invitedBy', 'usedBy']);

        // Apply filters
        if ($status) {
            switch ($status) {
                case 'pending':
                    $query->valid(); // Active, not expired, not used
                    break;
                case 'used':
                    $query->used(); // Has used_at timestamp
                    break;
                case 'expired':
                    $query->expired(); // expires_at is in the past
                    break;
                case 'cancelled':
                    $query->where('is_active', false)
                        ->whereNull('used_at'); // Inactive but not used = cancelled
                    break;
            }
        }

        if ($invited_by) {
            $user = User::where('username', 'like', '%'.$invited_by.'%')->first();
            if ($user) {
                $query->where('invited_by', $user->id);
            }
        }

        if ($email) {
            $query->where('email', 'like', '%'.$email.'%');
        }

        // Apply ordering
        switch ($orderBy) {
            case 'created_at_asc':
                $query->orderBy('created_at', 'asc');
                break;
            case 'created_at_desc':
                $query->orderBy('created_at', 'desc');
                break;
            case 'expires_at_asc':
                $query->orderBy('expires_at', 'asc');
                break;
            case 'expires_at_desc':
                $query->orderBy('expires_at', 'desc');
                break;
            case 'email_asc':
                $query->orderBy('email', 'asc');
                break;
            case 'email_desc':
                $query->orderBy('email', 'desc');
                break;
            default:
                $query->orderBy('created_at', 'desc');
        }

        // Paginate results
        $invitations = $query->paginate(config('nntmux.items_per_page', 25))->withQueryString();

        // Get overall statistics
        $stats = $this->getOverallStats();

        // Get user statistics (top inviters)
        $topInviters = $this->getTopInviters();

        $statusOptions = [
            '' => 'All',
            'pending' => 'Pending',
            'used' => 'Used',
            'expired' => 'Expired',
            'cancelled' => 'Cancelled',
        ];

        return view('admin.invitations.index', compact(
            'invitations',
            'stats',
            'topInviters',
            'status',
            'invited_by',
            'email',
            'orderBy',
            'statusOptions',
            'title',
            'meta_title'
        ));
    }

    /**
     * Get overall invitation statistics with caching
     */
    private function getOverallStats(): array
    {
        return \Illuminate\Support\Facades\Cache::remember('admin_invitation_stats', 300, function () {
            return [
                'total' => Invitation::count(),
                'pending' => Invitation::valid()->count(),
                'used' => Invitation::used()->count(),
                'expired' => Invitation::expired()->count(),
                'cancelled' => Invitation::where('is_active', false)->whereNull('used_at')->count(),
                'today' => Invitation::whereDate('created_at', today())->count(),
                'this_week' => Invitation::whereBetween('created_at', [
                    now()->startOfWeek(),
                    now()->endOfWeek(),
                ])->count(),
                'this_month' => Invitation::whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)
                    ->count(),
            ];
        });
    }

    /**
     * Get top inviters statistics with caching
     */
    private function getTopInviters(int $limit = 10): array
    {
        return \Illuminate\Support\Facades\Cache::remember('admin_top_inviters', 300, function () use ($limit) {
            return User::select('users.*')
                ->selectRaw('COUNT(invitations.id) as total_invitations')
                ->selectRaw('COUNT(CASE WHEN invitations.used_at IS NOT NULL THEN 1 END) as successful_invitations')
                ->leftJoin('invitations', 'users.id', '=', 'invitations.invited_by')
                ->groupBy('users.id')
                ->having('total_invitations', '>', 0)
                ->orderBy('total_invitations', 'desc')
                ->limit($limit)
                ->get()
                ->toArray();
        });
    }

    /**
     * Cancel an invitation
     */
    public function cancel(Request $request): RedirectResponse
    {
        try {
            $id = $request->route('id');
            $this->invitationService->cancelInvitation($id);

            return redirect()->back()->with('success', 'Invitation cancelled successfully');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to cancel invitation: '.$e->getMessage());
        }
    }

    /**
     * Resend an invitation
     */
    public function resend(Request $request): RedirectResponse
    {
        try {
            $id = $request->route('id');
            $this->invitationService->resendInvitation($id);

            return redirect()->back()->with('success', 'Invitation resent successfully');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to resend invitation: '.$e->getMessage());
        }
    }

    /**
     * Cleanup expired invitations
     */
    public function cleanup(Request $request): RedirectResponse
    {
        try {
            $cleanedCount = $this->invitationService->cleanupExpiredInvitations();

            return redirect()->back()->with('success', "Cleaned up {$cleanedCount} expired invitations");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to cleanup invitations: '.$e->getMessage());
        }
    }

    /**
     * View detailed invitation
     */
    public function show(Request $request): View
    {
        $this->setAdminPrefs();

        $id = $request->route('id');
        $invitation = Invitation::with(['invitedBy', 'usedBy'])->findOrFail($id);

        $meta_title = $title = 'Invitation Details';

        return view('admin.invitations.show', compact('invitation', 'title', 'meta_title'));
    }

    /**
     * Bulk actions on invitations
     */
    public function bulkAction(Request $request): RedirectResponse
    {
        $action = $request->get('bulk_action');
        $invitationIds = $request->get('invitation_ids', []);

        if (empty($invitationIds)) {
            return redirect()->back()->with('error', 'No invitations selected');
        }

        try {
            $count = 0;
            switch ($action) {
                case 'cancel':
                    foreach ($invitationIds as $id) {
                        $this->invitationService->cancelInvitation($id);
                        $count++;
                    }

                    return redirect()->back()->with('success', "Cancelled {$count} invitations");

                case 'resend':
                    foreach ($invitationIds as $id) {
                        $this->invitationService->resendInvitation($id);
                        $count++;
                    }

                    return redirect()->back()->with('success', "Resent {$count} invitations");

                case 'delete':
                    Invitation::whereIn('id', $invitationIds)->delete();
                    $count = count($invitationIds);

                    return redirect()->back()->with('success', "Deleted {$count} invitations");

                default:
                    return redirect()->back()->with('error', 'Invalid bulk action');
            }
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Bulk action failed: '.$e->getMessage());
        }
    }
}
