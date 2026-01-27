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
        $updateData = ['apiaccess' => now()];

        if ($event->ip !== null) {
            $updateData['host'] = $event->ip;
        }

        User::find($event->user->id)->update($updateData);
    }
}
