<?php

namespace App\Policies;

use Illuminate\Foundation\Auth\User;
use TeamTeaTime\Forum\Models\Category;

class CategoryPolicy extends \TeamTeaTime\Forum\Policies\CategoryPolicy
{
    /**
     * Create a new policy instance.
     */
    public function __construct()
    {
        //
    }

    public function manageThreads($user, Category $category): bool
    {
        return $user->hasRole('Admin');
    }

    public function deleteThreads($user, Category $category): bool
    {
        return $user->hasRole('Admin');
    }

    public function restoreThreads($user, Category $category): bool
    {
        return $user->hasRole('Admin');
    }

    public function enableThreads($user, Category $category): bool
    {
        return $user->hasRole('Admin');
    }

    public function moveThreadsFrom($user, Category $category): bool
    {
        return $user->hasRole('Admin');
    }

    public function moveThreadsTo($user, Category $category): bool
    {
        return $user->hasRole('Admin');
    }

    public function lockThreads($user, Category $category): bool
    {
        return $user->hasRole('Admin');
    }

    public function pinThreads($user, Category $category): bool
    {
        return $user->hasRole('Admin');
    }

    public function view($user, Category $category): bool
    {
        return $user->hasAnyRole(['Admin', 'Moderator']);
    }

    public function delete($user, Category $category): bool
    {
        return $user->hasRole('Admin');
    }
    
    public function edit(User $user, Category $category): bool
    {
        return $user->hasRole('Admin');
    }
}
