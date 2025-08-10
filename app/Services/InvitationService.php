<?php

namespace App\Services;

use App\Models\Invitation;
use App\Models\User;
use App\Mail\InvitationMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class InvitationService
{
    /**
     * Create and send an invitation
     */
    public function createAndSendInvitation(
        string $email,
        int $invitedBy,
        int $expiryDays = Invitation::DEFAULT_INVITE_EXPIRY_DAYS,
        array $metadata = []
    ): Invitation {
        // Get the user sending the invitation
        $user = User::find($invitedBy);
        if (!$user) {
            throw new \Exception('User not found.');
        }

        // Calculate how many invites are currently "in use" (pending/active)
        $activeInvitations = Invitation::where('invited_by', $invitedBy)
            ->where('is_active', true)
            ->where('used_at', null)
            ->where('expires_at', '>', now())
            ->count();

        // Check if user has invites available (total invites - active pending invitations)
        $availableInvites = $user->invites - $activeInvitations;
        if ($availableInvites <= 0) {
            throw new \Exception('You have no invitations available. You have ' . $activeInvitations . ' pending invitation(s). Contact an administrator if you need more invitations.');
        }

        // Validate email
        $validator = Validator::make(['email' => $email], [
            'email' => 'required|email|unique:users,email'
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        // Check if there's already a valid invitation for this email
        $existingInvitation = Invitation::where('email', strtolower(trim($email)))
            ->valid()
            ->first();

        if ($existingInvitation) {
            throw new \Exception('A valid invitation already exists for this email address.');
        }

        // Create the invitation (don't decrement invites here - only when used)
        $invitation = Invitation::createInvitation($email, $invitedBy, $expiryDays, $metadata);

        // Send the invitation email
        $this->sendInvitationEmail($invitation);

        return $invitation;
    }

    /**
     * Send invitation email
     */
    public function sendInvitationEmail(Invitation $invitation): void
    {
        Mail::to($invitation->email)->send(new InvitationMail($invitation));
    }

    /**
     * Resend an invitation
     */
    public function resendInvitation(int $invitationId): Invitation
    {
        $invitation = Invitation::findOrFail($invitationId);

        if (!$invitation->isValid()) {
            throw new \Exception('Cannot resend an invalid invitation.');
        }

        $this->sendInvitationEmail($invitation);

        return $invitation;
    }

    /**
     * Cancel an invitation
     */
    public function cancelInvitation(int $invitationId): bool
    {
        $invitation = Invitation::findOrFail($invitationId);

        if ($invitation->isUsed()) {
            throw new \Exception('Cannot cancel a used invitation.');
        }

        $invitation->is_active = false;
        return $invitation->save();
    }

    /**
     * Accept an invitation (use it for registration)
     */
    public function acceptInvitation(string $token, array $userData): User
    {
        $invitation = Invitation::findValidByToken($token);

        if (!$invitation) {
            throw new \Exception('Invalid or expired invitation token.');
        }

        // Get the user who sent the invitation
        $inviter = User::find($invitation->invited_by);

        // Create the user
        $user = User::create(array_merge($userData, [
            'email' => $invitation->email,
            'invited_by' => $invitation->invited_by,
        ]));

        // Mark invitation as used
        $invitation->markAsUsed($user->id);

        // Now decrement the inviter's available invites (only when actually used)
        if ($inviter) {
            $inviter->decrement('invites');
        }

        return $user;
    }

    /**
     * Get invitation statistics for a user
     */
    public function getUserInvitationStats(int $userId): array
    {
        return [
            'sent' => Invitation::where('invited_by', $userId)->count(),
            'pending' => Invitation::where('invited_by', $userId)->valid()->count(),
            'used' => Invitation::where('invited_by', $userId)->used()->count(),
            'expired' => Invitation::where('invited_by', $userId)->expired()->count(),
        ];
    }

    /**
     * Get all invitations for a user
     */
    public function getUserInvitations(int $userId, ?string $status = null)
    {
        $query = Invitation::where('invited_by', $userId)
            ->with(['usedBy'])
            ->orderBy('created_at', 'desc');

        return match($status) {
            'valid' => $query->valid()->paginate(15),
            'used' => $query->used()->paginate(15),
            'expired' => $query->expired()->paginate(15),
            'pending' => $query->unused()->paginate(15),
            default => $query->paginate(15),
        };
    }

    /**
     * Clean up expired invitations
     */
    public function cleanupExpiredInvitations(): int
    {
        return Invitation::cleanupExpired();
    }

    /**
     * Check if a user can send more invitations
     */
    public function canUserSendInvitation(int $userId, int $maxInvitations = null): bool
    {
        if ($maxInvitations === null) {
            return true; // No limit set
        }

        $sentCount = Invitation::where('invited_by', $userId)->count();
        return $sentCount < $maxInvitations;
    }

    /**
     * Get invitation by token for preview
     */
    public function getInvitationPreview(string $token): ?array
    {
        $invitation = Invitation::findByToken($token);

        if (!$invitation) {
            return null;
        }

        return [
            'email' => $invitation->email,
            'invited_by' => $invitation->invitedBy->username ?? 'Unknown',
            'expires_at' => $invitation->expires_at,
            'is_valid' => $invitation->isValid(),
            'is_expired' => $invitation->isExpired(),
            'is_used' => $invitation->isUsed(),
            'metadata' => $invitation->metadata,
        ];
    }

    /**
     * Get detailed invitation information for a user (for debugging)
     */
    public function getUserInvitationDetails(int $userId): array
    {
        $user = User::find($userId);
        if (!$user) {
            return [];
        }

        $totalInvites = $user->invites;

        $activeInvitations = Invitation::where('invited_by', $userId)
            ->where('is_active', true)
            ->where('used_at', null)
            ->where('expires_at', '>', now())
            ->count();

        $usedInvitations = Invitation::where('invited_by', $userId)
            ->whereNotNull('used_at')
            ->count();

        $expiredInvitations = Invitation::where('invited_by', $userId)
            ->where('expires_at', '<=', now())
            ->where('used_at', null)
            ->count();

        $cancelledInvitations = Invitation::where('invited_by', $userId)
            ->where('is_active', false)
            ->where('used_at', null)
            ->count();

        $availableInvites = $totalInvites - $activeInvitations;

        return [
            'user_id' => $userId,
            'username' => $user->username,
            'total_invites' => $totalInvites,
            'active_pending' => $activeInvitations,
            'used_invitations' => $usedInvitations,
            'expired_invitations' => $expiredInvitations,
            'cancelled_invitations' => $cancelledInvitations,
            'calculated_available' => $availableInvites,
            'can_send_invite' => $availableInvites > 0
        ];
    }
}
