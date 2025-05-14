<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RemoveInactiveAccounts implements ShouldQueue
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
     */
    public function handle(): void
    {
        $purgeDays = config('nntmux.purge_inactive_users_days');
        User::query()->where('roles_id', '=', 1)
            ->where(function ($query) use ($purgeDays) {
                $query->where('lastlogin', '<', now()->subDays($purgeDays))
                      ->orWhereNull('lastlogin');
            })
            ->where(function ($query) use ($purgeDays) {
                $query->where('apiaccess', '<', now()->subDays($purgeDays))
                      ->orWhereNull('apiaccess');
            })
            ->delete();
    }
}
