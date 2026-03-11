<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\UserInactivityEvaluator;
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
        $evaluator = new UserInactivityEvaluator;

        User::query()
            ->where('roles_id', UserRole::USER->value)
            ->where('created_at', '<', $threshold)
            ->chunkById(100, static function ($users) use ($evaluator, $threshold): void {
                $users
                    ->filter(
                        static fn (User $user): bool => $evaluator->shouldPurge(
                            createdAt: $user->created_at,
                            updatedAt: $user->updated_at,
                            lastLoginAt: $user->lastlogin,
                            apiAccessAt: $user->apiaccess,
                            lastDownloadAt: $user->lastdownload,
                            grabs: $user->grabs,
                            threshold: $threshold,
                        )
                    )
                    ->each(static fn (User $user) => $user->delete());
            });
    }
}
