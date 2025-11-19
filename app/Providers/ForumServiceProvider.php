<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\ServiceProvider;
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
    }
}
