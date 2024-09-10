<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ReleaseStat extends Model
{
    use HasFactory;

    protected $guarded = [];

    public static function insertRecentlyAdded(): void
    {
        $categories = Category::query()->with('parent')->where('r.adddate', '>', now()->subWeek())->select([
            'root_categories_id', DB::raw('COUNT(r.id) as count'), 'title',
        ])->join('releases as r', 'r.categories_id', '=',
            'categories.id')->groupBy('title')->orderByDesc('count')->get();

        foreach ($categories as $category) {
            self::updateOrCreate([
                'category' => $category->title,
                'count' => $category->count,
            ]);
        }
    }

    public static function getRecentlyAdded(): array
    {
        return self::query()->select(['category', 'count'])->get()->toArray();
    }
}
