<?php

namespace App\Policies;

use TeamTeaTime\Forum\Models\Thread;

class ThreadPolicy extends \TeamTeaTime\Forum\Policies\ThreadPolicy
{
    public function view($user, Thread $thread): bool
    {
        // Everyone (including Admin) can view by default
        return parent::view($user, $thread);
    }

    public function rename($user, Thread $thread): bool
    {
        // Admins can rename any thread; users can rename their own
        return $user->hasRole('Admin') || ($user->getKey() === $thread->author_id);
    }

    public function reply($user, Thread $thread): bool
    {
        // Admins can reply even if locked; otherwise respect lock state
        return $user->hasRole('Admin') || (! $thread->locked);
    }

    public function delete($user, Thread $thread): bool
    {
        // Admins can delete any thread; users can delete their own
        return $user->hasRole('Admin') || ($user->getKey() === $thread->author_id);
    }

    public function restore($user, Thread $thread): bool
    {
        // Admins can restore any thread; users can restore their own
        return $user->hasRole('Admin') || ($user->getKey() === $thread->author_id);
    }

    public function deletePosts($user, Thread $thread): bool
    {
        // Admins can delete posts in any thread; otherwise fall back to default (true)
        return $user->hasRole('Admin') || parent::deletePosts($user, $thread);
    }

    public function restorePosts($user, Thread $thread): bool
    {
        // Admins can restore posts in any thread; otherwise fall back to default (true)
        return $user->hasRole('Admin') || parent::restorePosts($user, $thread);
    }
}
