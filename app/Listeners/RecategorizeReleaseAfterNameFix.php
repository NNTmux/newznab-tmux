<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ReleaseNameFixed;
use App\Models\Release;
use App\Services\Categorization\CategorizationService;
use Illuminate\Support\Facades\Log;

class RecategorizeReleaseAfterNameFix
{
    public function __construct(private readonly CategorizationService $categorization) {}

    public function handle(ReleaseNameFixed $event): void
    {
        $release = Release::query()->find($event->releaseId, [
            'id',
            'groups_id',
            'fromname',
            'categories_id',
            'iscategorized',
            'searchname',
        ]);

        if ($release === null) {
            return;
        }

        $result = $this->categorization->determineCategory(
            $release->groups_id,
            $event->newName,
            (string) ($release->fromname ?? $event->poster)
        );

        $newCategoryId = (int) ($result['categories_id'] ?? $release->categories_id);

        if ((int) $release->categories_id === $newCategoryId && (int) $release->iscategorized === 1) {
            return;
        }

        Release::query()
            ->where('id', $release->id)
            ->update([
                'categories_id' => $newCategoryId,
                'iscategorized' => 1,
            ]);

        if (config('nntmux.categorization.log', false)) {
            Log::info('categorization.rename_recategorized', [
                'release_id' => $release->id,
                'old_name' => $event->oldName,
                'new_name' => $event->newName,
                'old_category_id' => $event->oldCategoryId,
                'new_category_id' => $newCategoryId,
            ]);
        }
    }
}
