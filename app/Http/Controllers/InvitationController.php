<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use App\Models\Settings;
use App\Services\InvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvitationController extends BasePageController
{
    protected InvitationService $invitationService;

    public function __construct(InvitationService $invitationService)
    {
        parent::__construct();
        $this->invitationService = $invitationService;
        $this->middleware('auth')->except(['show', 'accept']);
    }

    /**
     * Display a listing of the user's invitations
     */
    public function index(Request $request): View
    {

        $inviteMode = (int) Settings::settingValue('registerstatus') === Settings::REGISTER_STATUS_INVITE;
        $status = $request->get('status');

        $this->viewData['meta_title'] = 'My Invitations';
        $this->viewData['meta_keywords'] = 'invitations,invite,users,manage';
        $this->viewData['meta_description'] = 'Manage your sent invitations and send new invitations to friends';
        $this->viewData['status'] = $status;

        if (! $inviteMode) {
            // Invitations disabled: show informational message only, no queries.
            $this->viewData['invite_mode'] = false;
            $this->viewData['stats'] = [];
            $this->viewData['invitations'] = [];
            $this->viewData['pagination_links'] = null;

            $this->viewData['meta_title'] = 'Invitations Disabled';
            $this->viewData['meta_keywords'] = 'invitations,disabled';
            $this->viewData['meta_description'] = 'Invitations are currently disabled on this site.';

            return view('invitations.index', $this->viewData);
        }

        $user = auth()->user();

        $invitations = $this->invitationService->getUserInvitations($user->id, $status);
        $stats = $this->invitationService->getUserInvitationStats($user->id);

        // Convert paginated results to array for Blade
        $invitationsArray = [];
        foreach ($invitations as $invitation) {
            $invitationData = $invitation->toArray();

            // Add related user data
            if ($invitation->usedBy) {
                $invitationData['used_by_user'] = $invitation->usedBy->toArray();
            }

            // Convert timestamps - use Carbon's timestamp property instead of strtotime
            $invitationData['created_at'] = $invitation->created_at?->timestamp;
            $invitationData['expires_at'] = $invitation->expires_at?->timestamp;
            if ($invitation->used_at) {
                $invitationData['used_at'] = $invitation->used_at->timestamp;
            }

            $invitationsArray[] = $invitationData;
        }

        $this->viewData['invite_mode'] = true;
        $this->viewData['invitations'] = $invitationsArray;
        $this->viewData['stats'] = $stats;
        $this->viewData['status'] = $status;
        $this->viewData['pagination_links'] = $invitations->links();

        return view('invitations.index', $this->viewData);
    }

    /**
     * Show the form for creating a new invitation
     */
    public function create(): View
    {

        $inviteMode = (int) Settings::settingValue('registerstatus') === Settings::REGISTER_STATUS_INVITE;

        $this->viewData['meta_title'] = 'Send New Invitation';
        $this->viewData['meta_keywords'] = 'invitation,invite,send,new,user';
        $this->viewData['meta_description'] = 'Send a new invitation to invite someone to join the site';

        if (! $inviteMode) {
            $this->viewData['invite_mode'] = false;
            $this->viewData['meta_title'] = 'Invitations Disabled';
            $this->viewData['meta_keywords'] = 'invitations,disabled';
            $this->viewData['meta_description'] = 'Invitations are currently disabled on this site.';

            return view('invitations.create', $this->viewData);
        }

        $user = auth()->user();

        // Calculate available invites (total - active pending invitations)
        $activeInvitations = Invitation::where('invited_by', $user->id)
            ->where('is_active', true)
            ->where('used_at', null)
            ->where('expires_at', '>', now())
            ->count();

        $availableInvites = $user->invites - $activeInvitations;

        $this->viewData['invite_mode'] = true;
        $this->viewData['user_roles'] = config('nntmux.user_roles', []);
        $this->viewData['user_invites_left'] = $availableInvites;
        $this->viewData['user_invites_total'] = $user->invites;
        $this->viewData['user_invites_pending'] = $activeInvitations;
        $this->viewData['can_send_invites'] = $availableInvites > 0;

        return view('invitations.create', $this->viewData);
    }

    /**
     * Store a newly created invitation
     */
    public function store(Request $request): RedirectResponse
    {
        if ((int) Settings::settingValue('registerstatus') !== Settings::REGISTER_STATUS_INVITE) {
            return redirect()->route('invitations.index')->with('error', 'Invitations are currently disabled.');
        }

        $request->validate([
            'email' => 'required|email|unique:users,email',
            'expiry_days' => 'sometimes|integer|min:1|max:30',
            'role' => 'sometimes|integer|in:'.implode(',', array_keys(config('nntmux.user_roles', []))),
        ]);

        try {
            $expiryDays = $request->get('expiry_days', Invitation::DEFAULT_INVITE_EXPIRY_DAYS);
            $metadata = [];

            if ($request->has('role')) {
                $metadata['role'] = $request->get('role');
            }

            $invitation = $this->invitationService->createAndSendInvitation(
                $request->email,
                auth()->id(),
                $expiryDays,
                $metadata
            );

            return redirect()->route('invitations.index')
                ->with('success', 'Invitation sent successfully to '.$request->email);

        } catch (\Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Display the specified invitation
     */
    public function show(string $token): View
    {

        $preview = $this->invitationService->getInvitationPreview($token);

        // Convert timestamps - use Carbon's timestamp property
        if ($preview && isset($preview['expires_at'])) {
            $preview['expires_at'] = $preview['expires_at']->timestamp;
        }

        // Add role name if role is set
        if ($preview && isset($preview['metadata']['role'])) {
            $roles = config('nntmux.user_roles', []);
            $preview['role_name'] = $roles[$preview['metadata']['role']] ?? 'Default';
        }

        $this->viewData['preview'] = $preview;
        $this->viewData['token'] = $token;

        // Set meta information
        $this->viewData['meta_title'] = $preview ? 'Invitation to Join' : 'Invalid Invitation';
        $this->viewData['meta_keywords'] = 'invitation,join,register,signup';
        $this->viewData['meta_description'] = $preview ? 'You have been invited to join our community' : 'This invitation link is invalid or expired';

        return view('invitations.show', $this->viewData);
    }

    /**
     * Resend an invitation
     */
    public function resend(int $id): RedirectResponse
    {
        if ((int) Settings::settingValue('registerstatus') !== Settings::REGISTER_STATUS_INVITE) {
            return redirect()->route('invitations.index')->with('error', 'Invitations are currently disabled.');
        }

        try {
            $invitation = Invitation::findOrFail($id);

            // Check if user owns this invitation
            if ($invitation->invited_by !== auth()->id()) {
                abort(403, 'Unauthorized');
            }

            $this->invitationService->resendInvitation($id);

            return redirect()->route('invitations.index')
                ->with('success', 'Invitation resent successfully');

        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Cancel an invitation
     */
    public function destroy(int $id): RedirectResponse
    {
        if ((int) Settings::settingValue('registerstatus') !== Settings::REGISTER_STATUS_INVITE) {
            return redirect()->route('invitations.index')->with('error', 'Invitations are currently disabled.');
        }

        try {
            $invitation = Invitation::findOrFail($id);

            // Check if user owns this invitation
            if ($invitation->invited_by !== auth()->id()) {
                abort(403, 'Unauthorized');
            }

            $this->invitationService->cancelInvitation($id);

            return redirect()->route('invitations.index')
                ->with('success', 'Invitation cancelled successfully');

        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Get invitation statistics (API endpoint)
     */
    public function stats(): JsonResponse
    {
        if ((int) Settings::settingValue('registerstatus') !== Settings::REGISTER_STATUS_INVITE) {
            return response()->json(['message' => 'Invitations are disabled'], 404);
        }

        $stats = $this->invitationService->getUserInvitationStats(auth()->id());

        return response()->json($stats);
    }

    /**
     * Clean up expired invitations (admin only)
     */
    public function cleanup(): JsonResponse
    {
        // Check if user is admin
        if (! auth()->user()->hasRole('admin')) {
            abort(403, 'Unauthorized');
        }

        $cleanedCount = $this->invitationService->cleanupExpiredInvitations();

        return response()->json([
            'message' => "Cleaned up {$cleanedCount} expired invitations",
            'count' => $cleanedCount,
        ]);
    }
}
