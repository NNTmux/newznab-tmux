<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Forum;

use App\Support\Forum\CategoryTreeBuilder;
use TeamTeaTime\Forum\Models\Category;
use Tests\TestCase;

final class CategoryTreeBuilderTest extends TestCase
{
    public function test_it_builds_a_tree_with_root_categories_that_have_null_parent_ids(): void
    {
        $builder = new CategoryTreeBuilder;

        $general = $this->makeCategory(['id' => 1, 'title' => 'General', 'parent_id' => null, '_lft' => 1, '_rgt' => 4]);
        $generalChild = $this->makeCategory(['id' => 4, 'title' => 'Introductions', 'parent_id' => 1, '_lft' => 2, '_rgt' => 3]);
        $support = $this->makeCategory(['id' => 2, 'title' => 'Support', 'parent_id' => null, '_lft' => 5, '_rgt' => 6]);
        $news = $this->makeCategory(['id' => 3, 'title' => 'News', 'parent_id' => null, '_lft' => 7, '_rgt' => 8]);

        $categories = $general->newCollection([$general, $generalChild, $support, $news]);

        $tree = $builder->build($categories);

        $this->assertCount(3, $tree);
        $this->assertSame([1, 2, 3], $tree->pluck('id')->all());
        $this->assertCount(1, $tree->first()->children);
        $this->assertSame(4, $tree->first()->children->first()->id);
        $this->assertNull($tree->first()->getRelation('parent'));
    }

    /**
     * @param  array<string, int|string|null>  $attributes
     */
    private function makeCategory(array $attributes): Category
    {
        $category = new Category;
        $category->setRawAttributes($attributes, true);
        $category->exists = true;

        return $category;
    }
}
