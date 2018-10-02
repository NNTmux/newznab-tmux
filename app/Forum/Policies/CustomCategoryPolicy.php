<?php
/**
 * Created by PhpStorm.
 * User: darius
 * Date: 2.10.18.
 * Time: 10.45.
 */

namespace App\Forum\Policies;

use Riari\Forum\Models\Category;
use Riari\Forum\Policies\CategoryPolicy;

class CustomCategoryPolicy extends CategoryPolicy
{
    /**
     * Permission: Create threads in category.
     *
     * @param  object  $user
     * @param  Category  $category
     * @return bool
     */
    public function createThreads($user, Category $category)
    {
        return true;
    }

    /**
     * Permission: Manage threads in category.
     *
     * @param  object  $user
     * @param  Category  $category
     * @return bool
     */
    public function manageThreads($user, Category $category)
    {
        return $this->deleteThreads($user, $category) ||
            $this->enableThreads($user, $category) ||
            $this->moveThreadsFrom($user, $category) ||
            $this->lockThreads($user, $category) ||
            $this->pinThreads($user, $category);
    }

    /**
     * Permission: Delete threads in category.
     *
     * @param  object  $user
     * @param  Category  $category
     * @return bool
     */
    public function deleteThreads($user, Category $category)
    {
        return true;
    }

    /**
     * Permission: Enable threads in category.
     *
     * @param  object  $user
     * @param  Category  $category
     * @return bool
     */
    public function enableThreads($user, Category $category)
    {
        if ($user->hasAnyRole(['Admin', 'Moderator'])) {
            return true;
        }

        return false;
    }

    /**
     * Permission: Move threads from category.
     *
     * @param  object  $user
     * @param  Category  $category
     * @return bool
     */
    public function moveThreadsFrom($user, Category $category)
    {
        if ($user->hasAnyRole(['Admin', 'Moderator'])) {
            return true;
        }

        return false;
    }

    /**
     * Permission: Move threads to category.
     *
     * @param  object  $user
     * @param  Category  $category
     * @return bool
     */
    public function moveThreadsTo($user, Category $category)
    {
        if ($user->hasAnyRole(['Admin', 'Moderator'])) {
            return true;
        }

        return false;
    }

    /**
     * Permission: Lock threads in category.
     *
     * @param  object  $user
     * @param  Category  $category
     * @return bool
     */
    public function lockThreads($user, Category $category)
    {
        if ($user->hasAnyRole(['Admin', 'Moderator'])) {
            return true;
        }

        return false;
    }

    /**
     * Permission: Pin threads in category.
     *
     * @param  object  $user
     * @param  Category  $category
     * @return bool
     */
    public function pinThreads($user, Category $category)
    {
        if ($user->hasAnyRole(['Admin', 'Moderator'])) {
            return true;
        }

        return false;
    }

    /**
     * Permission: View category. Only takes effect for 'private' categories.
     *
     * @param  object  $user
     * @param  Category  $category
     * @return bool
     */
    public function view($user, Category $category)
    {
        if ($user->hasAnyRole(['Admin', 'Moderator'])) {
            return true;
        }

        return false;
    }

    /**
     * Permission: Delete category.
     *
     * @param  object  $user
     * @param  Category  $category
     * @return bool
     */
    public function delete($user, Category $category)
    {
        if ($user->hasAnyRole(['Admin', 'Moderator'])) {
            return true;
        }

        return false;
    }
}
