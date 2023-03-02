<?php

namespace App\Listeners;

use App\Events\UserAccessedApi;
use App\Models\User;

class UpdateUserAccessedApi
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
    public function handle(UserAccessedApi $event)
    {
        User::find($event->user->id)->update(['apiaccess' => now()]);
    }
}
