<?php

namespace App\Transformers;

use Conner\Tagging\Model\Tagged;
use League\Fractal\TransformerAbstract;

class TagsTransformer extends TransformerAbstract
{
    /**
     * A Fractal transformer.
     */
    public function transform(Tagged $tagged): array
    {
        return [
            'name' => [
                'normal' => $tagged->name,
                'slug' => $tagged->slug,
            ],
        ];
    }
}
