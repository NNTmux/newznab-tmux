<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Facades\Search;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ReindexReleaseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly int $releaseId,
    ) {}

    public function handle(): void
    {
        Search::updateRelease($this->releaseId);
    }
}
