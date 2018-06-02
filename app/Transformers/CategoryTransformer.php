<?php

namespace App\Transformers;

use App\Models\Category;
use League\Fractal\TransformerAbstract;

class CategoryTransformer extends TransformerAbstract
{
    /**
     * A Fractal transformer.
     *
     * @param \App\Models\Category $category
     * @return array
     */
    public function transform(Category $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->title,
            'subcategories' => $category->children->pluck('id', 'title'),
        ];
    }
}
