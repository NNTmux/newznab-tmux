<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReleaseNameFixed
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly int $releaseId,
        public readonly string $oldName,
        public readonly string $newName,
        public readonly int $oldCategoryId,
        public readonly int|string $groupId,
        public readonly string $poster = '',
    ) {}
}
