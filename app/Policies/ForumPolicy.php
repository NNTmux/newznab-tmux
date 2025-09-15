<?php

namespace App\Policies;

class ForumPolicy extends \TeamTeaTime\Forum\Policies\ForumPolicy
{
    /**
     * Create a new policy instance.
     */
    public function __construct()
    {
        //
    }

    public function createCategories($user): bool
    {
        return $user->hasRole('Admin');
    }

    public function manageCategories($user): bool
    {
        return $user->hasRole('Admin');
    }

    public function moveCategories($user): bool
    {
        return $user->hasRole('Admin');
    }

    public function renameCategories($user): bool
    {
        return $user->hasRole('Admin');
    }

    public function viewTrashedThreads($user): bool
    {
        return $user->hasRole('Admin');
    }

    public function viewTrashedPosts($user): bool
    {
        return $user->hasRole('Admin');
    }

    public function deleteCategories($user): bool
    {
        return $user->hasRole('Admin');
    }

    public function deletePosts($user): bool
    {
        return $user->hasRole('Admin');
    }

    public function deleteThreads($user): bool
    {
        return $user->hasRole('Admin');
    }

    public function restorePosts($user): bool
    {
        return $user->hasRole('Admin');
    }

    public function restoreThreads($user): bool
    {
        return $user->hasRole('Admin');
    }

    public function purgePosts($user): bool
    {
        return $user->hasRole('Admin');
    }

    public function purgeThreads($user): bool
    {
        return $user->hasRole('Admin');
    }

    public function deleteThrashedPosts($user): bool
    {
        return $user->hasRole('Admin');
    }
}
