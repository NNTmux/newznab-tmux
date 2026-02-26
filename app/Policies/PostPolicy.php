<?php

declare(strict_types=1);

namespace App\Policies;

use TeamTeaTime\Forum\Models\Post;

class PostPolicy extends \TeamTeaTime\Forum\Policies\PostPolicy
{
    public function edit(mixed $user, Post $post): bool
    {
        // Admins can edit any post; users can edit their own
        return $user->hasRole('Admin') || ($user->getKey() === $post->author_id); // @phpstan-ignore property.notFound
    }

    public function delete(mixed $user, Post $post): bool
    {
        // Admins can delete any post; users can delete their own
        return $user->hasRole('Admin') || ($user->getKey() === $post->author_id); // @phpstan-ignore property.notFound
    }

    public function restore(mixed $user, Post $post): bool
    {
        // Admins can restore any post; users can restore their own
        return $user->hasRole('Admin') || ($user->getKey() === $post->author_id); // @phpstan-ignore property.notFound
    }
}
