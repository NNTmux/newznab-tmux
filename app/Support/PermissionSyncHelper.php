<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Permission\Contracts\Role as RoleContract;
use Spatie\Permission\Models\Role;

class PermissionSyncHelper
{
    private const VIEW_PERMISSIONS = [
        'viewconsole' => 'view console',
        'viewmovies' => 'view movies',
        'viewaudio' => 'view audio',
        'viewpc' => 'view pc',
        'viewtv' => 'view tv',
        'viewadult' => 'view adult',
        'viewbooks' => 'view books',
        'viewother' => 'view other',
    ];

    private const ROLE_EXTRA_PERMISSIONS = [
        'canpreview' => 'preview',
        'hideads' => 'hideads',
        'editrelease' => 'edit release',
    ];

    /**
     * Sync view permissions for a user based on request checkboxes.
     * Grants permission if checkbox is present, revokes if absent.
     */
    public static function syncUserPermissions(User $entity, Request $request): void
    {
        foreach (self::VIEW_PERMISSIONS as $inputKey => $permission) {
            if ($request->has($inputKey)) {
                if (! $entity->hasDirectPermission($permission)) {
                    $entity->givePermissionTo($permission);
                }
            } elseif ($entity->hasPermissionTo($permission)) {
                $entity->revokePermissionTo($permission);
            }
        }
    }

    /**
     * Grant permissions to a newly created role based on request inputs.
     * Only grants (no revoke needed for new roles).
     */
    public static function grantRolePermissions(Role|RoleContract $role, Request $request): void
    {
        $allPermissions = array_merge(self::ROLE_EXTRA_PERMISSIONS, self::VIEW_PERMISSIONS);

        foreach ($allPermissions as $inputKey => $permission) {
            if ((int) $request->input($inputKey) === 1) {
                $role->givePermissionTo($permission);
            }
        }
    }

    /**
     * Sync permissions on an existing role based on request inputs.
     * Grants if input is 1, revokes if input is 0.
     */
    public static function syncRolePermissions(Role|RoleContract $role, Request $request): void
    {
        $allPermissions = array_merge(self::ROLE_EXTRA_PERMISSIONS, self::VIEW_PERMISSIONS);

        foreach ($allPermissions as $inputKey => $permission) {
            $value = (int) $request->input($inputKey);

            if ($value === 1 && ! $role->hasPermissionTo($permission)) {
                $role->givePermissionTo($permission);
            } elseif ($value === 0 && $role->hasPermissionTo($permission)) {
                $role->revokePermissionTo($permission);
            }
        }
    }

    /**
     * Grant a user direct permissions for all view permissions their role allows.
     * Used during registration to copy role-level permissions to direct user permissions.
     */
    public static function grantInheritedPermissions(User $user): void
    {
        foreach (self::VIEW_PERMISSIONS as $permission) {
            if ($user->can($permission)) {
                $user->givePermissionTo($permission);
            }
        }
    }
}
