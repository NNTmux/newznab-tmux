<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use TeamTeaTime\Forum\Models\Thread;

class ThreadPolicy extends \TeamTeaTime\Forum\Policies\ThreadPolicy
{
    public function view(mixed $user, Thread $thread): bool
    {
        // Guests can still view threads, but all mutating actions require auth.
        return $user === null || parent::view($user, $thread);
    }

    public function rename(mixed $user, Thread $thread): bool
    {
        // Admins can rename any thread; users can rename their own
        return $this->isAdmin($user) || $this->isThreadAuthor($user, $thread);
    }

    public function reply(mixed $user, Thread $thread): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        // Admins can reply even if locked; otherwise respect lock state
        return $this->isAdmin($user) || (! $thread->locked);
    }

    public function replyWithoutApproval(mixed $user, Thread $thread): bool
    {
        // Admins can reply to unapproved threads; otherwise only thread author
        return $this->isAdmin($user) || $this->isThreadAuthor($user, $thread);
    }

    public function delete(mixed $user, Thread $thread): bool
    {
        // Admins can delete any thread; users can delete their own
        return $this->isAdmin($user) || $this->isThreadAuthor($user, $thread);
    }

    public function restore(mixed $user, Thread $thread): bool
    {
        // Admins can restore any thread; users can restore their own
        return $this->isAdmin($user) || $this->isThreadAuthor($user, $thread);
    }

    public function deletePosts(mixed $user, Thread $thread): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        // Admins can delete posts in any thread; otherwise fall back to default (true)
        return $this->isAdmin($user) || parent::deletePosts($user, $thread);
    }

    public function restorePosts(mixed $user, Thread $thread): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        // Admins can restore posts in any thread; otherwise fall back to default (true)
        return $this->isAdmin($user) || parent::restorePosts($user, $thread);
    }

    private function isAdmin(mixed $user): bool
    {
        return $user instanceof User && $user->hasRole('Admin');
    }

    private function isThreadAuthor(mixed $user, Thread $thread): bool
    {
        return $user instanceof User && $user->getKey() === $thread->author_id;
    }
}
