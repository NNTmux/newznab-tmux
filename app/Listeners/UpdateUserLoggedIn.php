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
    }
}
