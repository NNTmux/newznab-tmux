<?php

namespace App\Listeners;

use App\Events\UserAccessedApi;
use App\Models\User;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

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
