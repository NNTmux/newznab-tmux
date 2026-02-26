<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\UserLoggedIn;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class UpdateUserLoggedIn
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(UserLoggedIn $event): void
    {
        User::find($event->user->id)->update(
            [
                'lastlogin' => now(),
                'host' => $event->ip,
            ]
        );

        // Log the user login event
        Log::channel('user_login')->info('User logged in', [
            'user_id' => $event->user->id,
            'username' => $event->user->username,
            'ip' => $event->ip,
            'time' => now(),
        ]);
    }
}
