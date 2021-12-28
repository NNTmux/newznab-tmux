<?php

namespace App\Transformers;

use App\Models\RootCategory;
use League\Fractal\TransformerAbstract;

class CategoryTransformer extends TransformerAbstract
{
    /**
     * A Fractal transformer.
     *
     * @param  \App\Models\RootCategory  $category
     * @return array
     */
    public function transform(RootCategory $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->title,
            'subcategories' => $category->categories()->pluck('id', 'title'),
        ];
    }
}
