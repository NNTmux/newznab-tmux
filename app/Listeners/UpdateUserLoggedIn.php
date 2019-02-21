<?php

namespace App\Listeners;

use App\Models\User;
use App\Events\UserLoggedIn;

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
     * @param  UserLoggedIn  $event
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
