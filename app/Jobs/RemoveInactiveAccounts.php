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
        $purgeDays = (int) config('nntmux.purge_inactive_users_days');
        $threshold = now()->subDays($purgeDays);

        User::query()->where('roles_id', 1)->where(function ($q) use ($threshold) {
            $q->where(function ($qq) use ($threshold) {
                $qq->whereNotNull('lastlogin')->where('lastlogin', '<', $threshold);
            })->orWhere(function ($qq) use ($threshold) {
                // Only treat null lastlogin as inactive if the account is older than threshold
                $qq->whereNull('lastlogin')->where('created_at', '<', $threshold);
            });
        })->where(function ($q) use ($threshold) {
            $q->where(function ($qq) use ($threshold) {
                $qq->whereNotNull('apiaccess')->where('apiaccess', '<', $threshold);
            })->orWhere(function ($qq) use ($threshold) {
                $qq->whereNull('apiaccess')->where('created_at', '<', $threshold);
            });
        });
    }
}
