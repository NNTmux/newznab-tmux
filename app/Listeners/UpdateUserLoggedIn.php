<?php

namespace App\Listeners;

use App\Events\UserLoggedIn;
use App\Models\User;

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
        \Log::channel('user_login')->info('User logged in', [
            'user_id' => $event->user->id,
            'username' => $event->user->username,
            'ip' => $event->ip,
            'time' => now(),
        ]);
    }
}
