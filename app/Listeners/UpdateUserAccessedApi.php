<?php

namespace App\Listeners;

use App\Models\User;
use App\Events\UserAccessedApi;

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
     * @param  UserAccessedApi  $event
     * @return void
     */
    public function handle(UserAccessedApi $event)
    {
        User::find($event->user->id)->update(['apiaccess' => now()->format('Y-m-d h:m:s')]);
    }
}
