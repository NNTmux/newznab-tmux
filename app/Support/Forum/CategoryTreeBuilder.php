<?php

declare(strict_types=1);

namespace App\Support\Forum;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use TeamTeaTime\Forum\Models\Category;
use TeamTeaTime\Forum\Support\Access\CategoryAccess;

final class CategoryTreeBuilder
{
    /**
     * @param  EloquentCollection<int, Category>  $categories
     * @return EloquentCollection<int, Category>
     */
    public function build(EloquentCollection $categories): EloquentCollection
    {
        if ($categories->isEmpty()) {
            return new EloquentCollection;
        }

        $rootParentId = $this->resolveRootParentId($categories);
        $categories->linkNodes();

        /** @var EloquentCollection<int, Category> $tree */
        $tree = $categories
            ->filter(static fn (Category $category): bool => $category->getParentId() === $rootParentId)
            ->values();

        return $this->removeParentRelationships($tree);
    }

    /**
     * @return EloquentCollection<int, Category>
     */
    public function buildAccessibleTo(mixed $user): EloquentCollection
    {
        return $this->build(CategoryAccess::getFilteredTreeFor($user));
    }

    /**
     * @return EloquentCollection<int, Category>
     */
    public function buildThreadDestinations(): EloquentCollection
    {
        return $this->build(Category::query()->threadDestinations()->get());
    }

    /**
     * @param  EloquentCollection<int, Category>  $categories
     */
    public function hideManageColumns(EloquentCollection $categories): void
    {
        $categories->each(function (Category $category): void {
            $category->makeHidden(['_lft', '_rgt', 'thread_count', 'post_count']);

            if ($category->relationLoaded('children') && $category->children->isNotEmpty()) {
                /** @var EloquentCollection<int, Category> $children */
                $children = $category->children;
                $this->hideManageColumns($children);
            }
        });
    }

    /**
     * @param  EloquentCollection<int, Category>  $categories
     */
    private function resolveRootParentId(EloquentCollection $categories): int|string|null
    {
        $rootParentId = null;
        $lowestLeft = null;

        foreach ($categories as $category) {
            $left = $category->getLft();

            if ($lowestLeft === null || ($left !== null && $left < $lowestLeft)) {
                $lowestLeft = $left;
                $rootParentId = $category->getParentId();
            }
        }

        return $rootParentId;
    }

    /**
     * @param  EloquentCollection<int, Category>  $categories
     * @return EloquentCollection<int, Category>
     */
    private function removeParentRelationships(EloquentCollection $categories): EloquentCollection
    {
        $categories->each(function (Category $category): void {
            $category->setRelation('parent', null);

            if (! $category->relationLoaded('children')) {
                return;
            }

            /** @var EloquentCollection<int, Category> $children */
            $children = $category->children;

            $category->setRelation('children', $this->removeParentRelationships($children));
        });

        return $categories;
    }
}
