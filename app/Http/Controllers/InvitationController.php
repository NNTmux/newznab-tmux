<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use App\Services\InvitationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

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
    public function index(Request $request): void
    {
        $this->setPreferences();

        $status = $request->get('status');
        $user = auth()->user();

        $invitations = $this->invitationService->getUserInvitations($user->id, $status);
        $stats = $this->invitationService->getUserInvitationStats($user->id);

        // Convert paginated results to array for Smarty
        $invitationsArray = [];
        foreach ($invitations as $invitation) {
            $invitationData = $invitation->toArray();

            // Add related user data
            if ($invitation->usedBy) {
                $invitationData['used_by_user'] = $invitation->usedBy->toArray();
            }

            // Convert timestamps for Smarty
            $invitationData['created_at'] = strtotime($invitation->created_at);
            $invitationData['expires_at'] = strtotime($invitation->expires_at);
            if ($invitation->used_at) {
                $invitationData['used_at'] = strtotime($invitation->used_at);
            }

            $invitationsArray[] = $invitationData;
        }

        $this->smarty->assign([
            'invitations' => $invitationsArray,
            'stats' => $stats,
            'status' => $status,
            'pagination_links' => $invitations->links(),
            'csrf_token' => csrf_token(),
        ]);

        // Set meta information
        $meta_title = 'My Invitations';
        $meta_keywords = 'invitations,invite,users,manage';
        $meta_description = 'Manage your sent invitations and send new invitations to friends';

        // Fetch the template content
        $content = $this->smarty->fetch('invitations_index.tpl');

        // Assign content and meta data for final rendering
        $this->smarty->assign([
            'content' => $content,
            'meta_title' => $meta_title,
            'meta_keywords' => $meta_keywords,
            'meta_description' => $meta_description,
        ]);

        // Render the page with proper styling
        $this->pagerender();
    }

    /**
     * Show the form for creating a new invitation
     */
    public function create(): void
    {
        $this->setPreferences();

        $user = auth()->user();

        // Calculate available invites (total - active pending invitations)
        $activeInvitations = Invitation::where('invited_by', $user->id)
            ->where('is_active', true)
            ->where('used_at', null)
            ->where('expires_at', '>', now())
            ->count();

        $availableInvites = $user->invites - $activeInvitations;

        $this->smarty->assign([
            'user_roles' => config('nntmux.user_roles', []),
            'csrf_token' => csrf_token(),
            'old' => old(),
            'errors' => session('errors') ? session('errors')->getBag('default')->getMessages() : [],
            'user_invites_left' => $availableInvites,
            'user_invites_total' => $user->invites,
            'user_invites_pending' => $activeInvitations,
            'can_send_invites' => $availableInvites > 0,
        ]);

        // Set meta information
        $meta_title = 'Send New Invitation';
        $meta_keywords = 'invitation,invite,send,new,user';
        $meta_description = 'Send a new invitation to invite someone to join the site';

        // Fetch the template content
        $content = $this->smarty->fetch('invitations_create.tpl');

        // Assign content and meta data for final rendering
        $this->smarty->assign([
            'content' => $content,
            'meta_title' => $meta_title,
            'meta_keywords' => $meta_keywords,
            'meta_description' => $meta_description,
        ]);

        // Render the page with proper styling
        $this->pagerender();
    }

    /**
     * Store a newly created invitation
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email|unique:users,email',
            'expiry_days' => 'sometimes|integer|min:1|max:30',
            'role' => 'sometimes|integer|in:' . implode(',', array_keys(config('nntmux.user_roles', []))),
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
                ->with('success', 'Invitation sent successfully to ' . $request->email);

        } catch (\Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Display the specified invitation
     */
    public function show(string $token): void
    {
        // For public invitation page, we need to handle both logged in and guest users
        if (auth()->check()) {
            $this->setPreferences();
        } else {
            // Set up basic Smarty configuration for guests
            $this->smarty->setTemplateDir([
                'user' => config('ytake-laravel-smarty.template_path').'/Gentele',
                'shared' => config('ytake-laravel-smarty.template_path').'/shared',
                'default' => config('ytake-laravel-smarty.template_path').'/Gentele',
            ]);

            $this->smarty->assign([
                'isadmin' => false,
                'ismod' => false,
                'loggedin' => false,
                'theme' => 'Gentele',
                'site' => $this->settings,
            ]);
        }

        $preview = $this->invitationService->getInvitationPreview($token);

        // Convert timestamps for Smarty
        if ($preview && isset($preview['expires_at'])) {
            $preview['expires_at'] = strtotime($preview['expires_at']);
        }

        // Add role name if role is set
        if ($preview && isset($preview['metadata']['role'])) {
            $roles = config('nntmux.user_roles', []);
            $preview['role_name'] = $roles[$preview['metadata']['role']] ?? 'Default';
        }

        $this->smarty->assign([
            'preview' => $preview,
            'token' => $token,
        ]);

        // Set meta information
        $meta_title = $preview ? 'Invitation to Join' : 'Invalid Invitation';
        $meta_keywords = 'invitation,join,register,signup';
        $meta_description = $preview ? 'You have been invited to join our community' : 'This invitation link is invalid or expired';

        // Fetch the template content
        $content = $this->smarty->fetch('invitations_show.tpl');

        // Assign content and meta data for final rendering
        $this->smarty->assign([
            'content' => $content,
            'meta_title' => $meta_title,
            'meta_keywords' => $meta_keywords,
            'meta_description' => $meta_description,
        ]);

        // Render the page with proper styling
        $this->pagerender();
    }

    /**
     * Resend an invitation
     */
    public function resend(int $id): RedirectResponse
    {
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
        $stats = $this->invitationService->getUserInvitationStats(auth()->id());
        return response()->json($stats);
    }

    /**
     * Clean up expired invitations (admin only)
     */
    public function cleanup(): JsonResponse
    {
        // Check if user is admin
        if (!auth()->user()->hasRole('admin')) {
            abort(403, 'Unauthorized');
        }

        $cleanedCount = $this->invitationService->cleanupExpiredInvitations();

        return response()->json([
            'message' => "Cleaned up {$cleanedCount} expired invitations",
            'count' => $cleanedCount
        ]);
    }
}
