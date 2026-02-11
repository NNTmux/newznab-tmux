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

    public function createCategories(mixed $user): bool
    {
        return $user->hasRole('Admin');
    }

    public function manageCategories(mixed $user): bool
    {
        return $user->hasRole('Admin');
    }

    public function moveCategories(mixed $user): bool
    {
        return $user->hasRole('Admin');
    }

    public function renameCategories(mixed $user): bool
    {
        return $user->hasRole('Admin');
    }

    public function viewTrashedThreads(mixed $user): bool
    {
        return $user->hasRole('Admin');
    }

    public function viewTrashedPosts(mixed $user): bool
    {
        return $user->hasRole('Admin');
    }

    public function deleteCategories(mixed $user): bool
    {
        return $user->hasRole('Admin');
    }

    public function deletePosts(mixed $user): bool
    {
        return $user->hasRole('Admin');
    }

    public function deleteThreads(mixed $user): bool
    {
        return $user->hasRole('Admin');
    }

    public function restorePosts(mixed $user): bool
    {
        return $user->hasRole('Admin');
    }

    public function restoreThreads(mixed $user): bool
    {
        return $user->hasRole('Admin');
    }

    public function purgePosts(mixed $user): bool
    {
        return $user->hasRole('Admin');
    }

    public function purgeThreads(mixed $user): bool
    {
        return $user->hasRole('Admin');
    }

    public function deleteThrashedPosts(mixed $user): bool
    {
        return $user->hasRole('Admin');
    }
}
