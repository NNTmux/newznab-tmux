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
}
