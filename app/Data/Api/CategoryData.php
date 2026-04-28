<?php

declare(strict_types=1);

namespace App\Data\Api;

use App\Models\RootCategory;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * API v2 representation of a {@see RootCategory} with its sub-categories.
 *
 * Replaces the legacy `App\Transformers\CategoryTransformer`.
 */
#[TypeScript]
final class CategoryData extends Data
{
    /**
     * @param  array<int|string, string>  $subcategories  Map of sub-category ID → title.
     */
    public function __construct(
        public int $id,
        public string $name,
        public array $subcategories,
    ) {}

    public static function fromCategory(RootCategory $category): self
    {
        /** @var array<int|string, string> $subcategories */
        $subcategories = $category->categories()->pluck('title', 'id')->all();

        return new self(
            id: (int) $category->id,
            name: (string) $category->title,
            subcategories: $subcategories,
        );
    }
}
