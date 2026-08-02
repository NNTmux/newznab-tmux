<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Category;
use App\Support\ReleaseSearchIndexSync;
use Illuminate\Support\Facades\DB;

final class CategoryObserver
{
    public function updated(Category $category): void
    {
        if (! $category->isDirty(['title', 'parentid', 'root_categories_id'])) {
            return;
        }

        DB::afterCommit(fn (): bool => $this->reindex($category));
    }

    private function reindex(Category $category): bool
    {
        ReleaseSearchIndexSync::forCategory((int) $category->id);

        return true;
    }
}
