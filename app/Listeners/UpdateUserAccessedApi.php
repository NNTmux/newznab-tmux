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
     */
    public function handle(UserAccessedApi $event): void
    {
        User::find($event->user->id)->update(['apiaccess' => now()]);
    }
}
