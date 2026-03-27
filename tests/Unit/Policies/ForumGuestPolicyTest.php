<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\User;
use App\Policies\CategoryPolicy;
use App\Policies\PostPolicy;
use App\Policies\ThreadPolicy;
use TeamTeaTime\Forum\Models\Category;
use TeamTeaTime\Forum\Models\Post;
use TeamTeaTime\Forum\Models\Thread;
use Tests\TestCase;

final class ForumGuestPolicyTest extends TestCase
{
    public function test_guest_category_actions_are_denied_without_throwing(): void
    {
        $policy = new CategoryPolicy;
        $category = new Category;

        $this->assertFalse($policy->manageThreads(null, $category));
        $this->assertFalse($policy->deleteThreads(null, $category));
        $this->assertFalse($policy->restoreThreads(null, $category));
        $this->assertFalse($policy->lockThreads(null, $category));
        $this->assertFalse($policy->pinThreads(null, $category));
        $this->assertFalse($policy->moveThreadsFrom(null, $category));
        $this->assertFalse($policy->view(null, $category));
    }

    public function test_authenticated_non_staff_user_can_view_categories(): void
    {
        $policy = new CategoryPolicy;
        $category = new Category;
        $user = new User;

        $this->assertTrue($policy->view($user, $category));
    }

    public function test_guest_thread_actions_are_denied_but_threads_remain_viewable(): void
    {
        $policy = new ThreadPolicy;
        $thread = new Thread([
            'author_id' => 1,
            'locked' => false,
        ]);

        $this->assertTrue($policy->view(null, $thread));
        $this->assertFalse($policy->rename(null, $thread));
        $this->assertFalse($policy->reply(null, $thread));
        $this->assertFalse($policy->replyWithoutApproval(null, $thread));
        $this->assertFalse($policy->delete(null, $thread));
        $this->assertFalse($policy->restore(null, $thread));
        $this->assertFalse($policy->deletePosts(null, $thread));
        $this->assertFalse($policy->restorePosts(null, $thread));
    }

    public function test_guest_post_actions_are_denied_without_throwing(): void
    {
        $policy = new PostPolicy;
        $post = new Post([
            'author_id' => 1,
        ]);

        $this->assertFalse($policy->edit(null, $post));
        $this->assertFalse($policy->delete(null, $post));
        $this->assertFalse($policy->restore(null, $post));
    }
}
