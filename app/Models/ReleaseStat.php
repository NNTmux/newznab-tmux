<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ReleaseStat extends Model
{
    use HasFactory; // @phpstan-ignore missingType.generics

    protected $guarded = [];

    public static function insertRecentlyAdded(): void
    {
        $categories = Category::query()
            ->with('parent')
            ->where('r.adddate', '>', now()->subWeek())
            ->select([
                'categories.id',
                'root_categories_id',
                DB::raw('COUNT(r.id) as count'),
                'categories.title',
            ])
            ->join('releases as r', 'r.categories_id', '=', 'categories.id')
            ->whereNotIn('categories.id', [10, 20]) // Exclude OTHER_MISC and OTHER_HASHED
            ->groupBy('categories.id', 'root_categories_id', 'categories.title')
            ->orderByDesc('count')
            ->get();

        foreach ($categories as $category) {
            // Build the category display name with root category prefix
            $categoryDisplay = $category->parent
                ? $category->parent->title.' > '.$category->title
                : $category->title;

            // Check if we already have the information and if we do just update the count
            if (self::query()->where('category', $categoryDisplay)->exists()) {
                self::query()->where('category', $categoryDisplay)->update(['count' => $category->count]);

                continue;
            }
            self::query()->create(['category' => $categoryDisplay, 'count' => $category->count]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function getRecentlyAdded(): array
    {
        return self::query()->select(['category', 'count'])->get()->toArray();
    }
}
