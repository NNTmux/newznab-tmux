<?php

namespace App\Transformers;

use App\Models\RootCategory;
use League\Fractal\TransformerAbstract;

class CategoryTransformer extends TransformerAbstract
{
    /**
     * Transform a root category into an array.
     */
    public function transform(RootCategory $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->title,
            'subcategories' => $category->categories()->pluck('title', 'id'),
        ];
    }
}
