<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\Forum\CategoryTreeBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use TeamTeaTime\Forum\Models\Category;
use TeamTeaTime\Forum\Models\Post;

class ForumServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Add global scope to eager load author with role for all post queries
        Post::addGlobalScope('withAuthorRole', function (Builder $builder) {
            $builder->with(['author.role']);
        });

        $categoryTreeBuilder = $this->app->make(CategoryTreeBuilder::class);

        View::composer('forum::category.index', function ($view) use ($categoryTreeBuilder): void {
            $view->with('categories', $categoryTreeBuilder->buildAccessibleTo(request()->user()));
        });

        View::composer('forum::category.manage', function ($view) use ($categoryTreeBuilder): void {
            $categories = Category::defaultOrder()->get();
            $tree = $categoryTreeBuilder->build($categories);
            $categoryTreeBuilder->hideManageColumns($tree);

            $view->with('categories', $tree);
        });

        View::composer('forum::thread.show', function ($view) use ($categoryTreeBuilder): void {
            $data = $view->getData();

            if (! empty($data['categories'])) {
                return;
            }

            $view->with('categories', $categoryTreeBuilder->buildThreadDestinations());
        });
    }
}
