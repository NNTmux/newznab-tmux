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
     *
     * @return void
     */
    public function handle(UserLoggedIn $event)
    {
        User::find($event->user->id)->update(
            [
                'lastlogin' => now(),
                'host' => $event->ip,
            ]
        );
    }
}
