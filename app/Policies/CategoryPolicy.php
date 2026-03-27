<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
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
        return $this->isAdmin($user);
    }

    public function deleteThreads(mixed $user, Category $category): bool
    {
        return $this->isAdmin($user);
    }

    public function restoreThreads(mixed $user, Category $category): bool
    {
        return $this->isAdmin($user);
    }

    public function enableThreads(mixed $user, Category $category): bool
    {
        return $this->isAdmin($user);
    }

    public function moveThreadsFrom(mixed $user, Category $category): bool
    {
        return $this->isAdmin($user);
    }

    public function moveThreadsTo(mixed $user, Category $category): bool
    {
        return $this->isAdmin($user);
    }

    public function lockThreads(mixed $user, Category $category): bool
    {
        return $this->isAdmin($user);
    }

    public function pinThreads(mixed $user, Category $category): bool
    {
        return $this->isAdmin($user);
    }

    public function view(mixed $user, Category $category): bool
    {
        return $this->canViewCategory($user);
    }

    public function delete(mixed $user, Category $category): bool
    {
        return $this->isAdmin($user);
    }

    public function edit(mixed $user, Category $category): bool
    {
        return $this->isAdmin($user);
    }

    private function isAdmin(mixed $user): bool
    {
        return $user instanceof User && $user->hasRole('Admin');
    }

    private function canViewCategory(mixed $user): bool
    {
        // Forum v8 applies category view checks when filtering private category trees.
        // Allow any authenticated forum user to see categories while keeping category
        // management actions restricted to admins.
        return $user instanceof User;
    }
}
