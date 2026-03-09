<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use TeamTeaTime\Forum\Models\Post;

class PostPolicy extends \TeamTeaTime\Forum\Policies\PostPolicy
{
    public function edit(mixed $user, Post $post): bool
    {
        // Admins can edit any post; users can edit their own
        return $this->isAdmin($user) || $this->isPostAuthor($user, $post);
    }

    public function delete(mixed $user, Post $post): bool
    {
        // Admins can delete any post; users can delete their own
        return $this->isAdmin($user) || $this->isPostAuthor($user, $post);
    }

    public function restore(mixed $user, Post $post): bool
    {
        // Admins can restore any post; users can restore their own
        return $this->isAdmin($user) || $this->isPostAuthor($user, $post);
    }

    private function isAdmin(mixed $user): bool
    {
        return $user instanceof User && $user->hasRole('Admin');
    }

    private function isPostAuthor(mixed $user, Post $post): bool
    {
        return $user instanceof User && $user->getKey() === $post->author_id;
    }
}
