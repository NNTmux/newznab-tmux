<?php

namespace App\Observers;

use App\Models\User;
use App\Models\UserActivity;

class UserActivityObserver
{
    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        UserActivity::create([
            'user_id' => $user->id,
            'username' => $user->username,
            'activity_type' => 'registered',
            'description' => "New user registered: {$user->username}",
            'metadata' => [
                'email' => $user->email,
                'ip_address' => request()->ip(),
            ],
        ]);
    }

    /**
     * Handle the User "updated" event.
     */
    public function updated(User $user): void
    {
        // Check if the role was changed
        if ($user->isDirty('roles_id')) {
            $oldRoleId = $user->getOriginal('roles_id');
            $newRoleId = $user->roles_id;

            // Get role names
            $oldRoleName = $this->getRoleName($oldRoleId);
            $newRoleName = $this->getRoleName($newRoleId);

            UserActivity::create([
                'user_id' => $user->id,
                'username' => $user->username,
                'activity_type' => 'role_updated',
                'description' => "User role updated: {$user->username} → {$newRoleName}",
                'metadata' => [
                    'old_role_id' => $oldRoleId,
                    'new_role_id' => $newRoleId,
                    'old_role_name' => $oldRoleName,
                    'new_role_name' => $newRoleName,
                    'updated_by' => auth()->user()?->username ?? 'System',
                ],
            ]);
        }
    }

    /**
     * Handle the User "deleted" event (soft delete).
     */
    public function deleted(User $user): void
    {
        // Only log if it's a soft delete (not force delete)
        if ($user->trashed()) {
            UserActivity::create([
                'user_id' => $user->id,
                'username' => $user->username,
                'activity_type' => 'deleted',
                'description' => "User deleted: {$user->username}",
                'metadata' => [
                    'email' => $user->email,
                    'deleted_by' => auth()->user()?->username ?? 'System',
                ],
            ]);
        }
    }

    /**
     * Handle the User "restored" event.
     */
    public function restored(User $user): void
    {
        UserActivity::create([
            'user_id' => $user->id,
            'username' => $user->username,
            'activity_type' => 'registered',
            'description' => "User restored: {$user->username}",
            'metadata' => [
                'restored_by' => auth()->user()?->username ?? 'System',
            ],
        ]);
    }

    /**
     * Handle the User "force deleted" event.
     */
    public function forceDeleted(User $user): void
    {
        // Log permanent deletion
        UserActivity::create([
            'user_id' => null, // User no longer exists
            'username' => $user->username,
            'activity_type' => 'deleted',
            'description' => "User permanently deleted: {$user->username}",
            'metadata' => [
                'email' => $user->email,
                'deleted_by' => auth()->user()?->username ?? 'System',
                'permanent' => true,
            ],
        ]);
    }

    /**
     * Get role name by ID
     */
    private function getRoleName(?int $roleId): string
    {
        if (! $roleId) {
            return 'None';
        }

        try {
            $role = \Spatie\Permission\Models\Role::find($roleId);

            return $role ? $role->name : "Role #{$roleId}";
        } catch (\Exception $e) {
            return "Role #{$roleId}";
        }
    }
}
