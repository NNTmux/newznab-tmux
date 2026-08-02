<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\UsenetGroup;
use App\Support\ReleaseSearchIndexSync;
use Illuminate\Support\Facades\DB;

final class UsenetGroupObserver
{
    public function updated(UsenetGroup $group): void
    {
        if (! $group->isDirty('name')) {
            return;
        }

        DB::afterCommit(fn (): bool => $this->reindex($group));
    }

    private function reindex(UsenetGroup $group): bool
    {
        ReleaseSearchIndexSync::forQueryGroup((int) $group->id);

        return true;
    }
}
