<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\RootCategory;
use App\Support\ReleaseSearchIndexSync;
use Illuminate\Support\Facades\DB;

final class RootCategoryObserver
{
    public function updated(RootCategory $category): void
    {
        if (! $category->isDirty('title')) {
            return;
        }

        DB::afterCommit(fn (): bool => $this->reindex($category));
    }

    private function reindex(RootCategory $category): bool
    {
        ReleaseSearchIndexSync::forRootCategory((int) $category->id);

        return true;
    }
}
