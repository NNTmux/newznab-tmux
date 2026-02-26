<?php

declare(strict_types=1);

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

    public function manageThreads(mixed $user, Category $category): bool
    {
        return $user->hasRole('Admin');
    }

    public function deleteThreads(mixed $user, Category $category): bool
    {
        return $user->hasRole('Admin');
    }

    public function restoreThreads(mixed $user, Category $category): bool
    {
        return $user->hasRole('Admin');
    }

    public function enableThreads(mixed $user, Category $category): bool
    {
        return $user->hasRole('Admin');
    }

    public function moveThreadsFrom(mixed $user, Category $category): bool
    {
        return $user->hasRole('Admin');
    }

    public function moveThreadsTo(mixed $user, Category $category): bool
    {
        return $user->hasRole('Admin');
    }

    public function lockThreads(mixed $user, Category $category): bool
    {
        return $user->hasRole('Admin');
    }

    public function pinThreads(mixed $user, Category $category): bool
    {
        return $user->hasRole('Admin');
    }

    public function view(mixed $user, Category $category): bool
    {
        return $user->hasAnyRole(['Admin', 'Moderator']);
    }

    public function delete(mixed $user, Category $category): bool
    {
        return $user->hasRole('Admin');
    }

    public function edit(User $user, Category $category): bool
    {
        /** @var \App\Models\User $user */
        return $user->hasRole('Admin');
    }
}
