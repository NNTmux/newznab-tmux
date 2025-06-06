<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PurgeDeletedAccounts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * Find users that were soft-deleted 6 months ago and permanently delete them.
     */
    public function handle(): void
    {
        // Find users that were soft-deleted 6 months ago
        User::onlyTrashed()
            ->where('deleted_at', '<', now()->subDays(config('nntmux.purge_inactive_users_days')))
            ->get()
            ->each(function ($user) {
                $user->forceDelete(); // Permanently delete the user
            });
    }
}
