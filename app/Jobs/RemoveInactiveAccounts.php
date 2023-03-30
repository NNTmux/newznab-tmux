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
     *
     * @return void
     */
    public function handle(): void
    {
        User::query()->where('lastlogin', '<', now()->subMonths(6))->where('apiaccess', '<', now()->subMonths(6))->where('roles_id', '=', 1)->delete();
        User::query()->where('lastlogin', '<', now()->subMonths(6))->whereNull('apiaccess')->where('roles_id', '=', 1)->delete();
    }
}
