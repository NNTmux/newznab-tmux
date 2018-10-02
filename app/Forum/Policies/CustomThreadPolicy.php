<?php
/**
 * Created by PhpStorm.
 * User: darius
 * Date: 2.10.18.
 * Time: 10.50.
 */

namespace App\Forum\Policies;

use Riari\Forum\Models\Thread;
use Illuminate\Support\Facades\Gate;
use Riari\Forum\Policies\ThreadPolicy;

class CustomThreadPolicy extends ThreadPolicy
{
    /**
     * Permission: Delete posts in thread.
     *
     * @param  object  $user
     * @param  Thread  $thread
     * @return bool
     */
    public function deletePosts($user, Thread $thread)
    {
        return $user->getKey() === $thread->author_id;
    }

    /**
     * Permission: Rename thread.
     *
     * @param  object  $user
     * @param  Thread  $thread
     * @return bool
     */
    public function rename($user, Thread $thread)
    {
        return $user->getKey() === $thread->author_id;
    }

    /**
     * Permission: Reply to thread.
     *
     * @param  object  $user
     * @param  Thread  $thread
     * @return bool
     */
    public function reply($user, Thread $thread)
    {
        return ! $thread->locked;
    }

    /**
     * Permission: Delete thread.
     *
     * @param  object  $user
     * @param  Thread  $thread
     * @return bool
     */
    public function delete($user, Thread $thread)
    {
        return Gate::allows('deleteThreads', $thread->category) || $user->getKey() === $thread->author_id;
    }
}
