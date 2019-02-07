<?php

namespace App\Observers;

use App\Models\User;
use Jrean\UserVerification\Facades\UserVerification;

class UserServiceObserver
{
    /**
     * Handle the user "created" event.
     *
     * @param  \App\Models\User $user
     *
     * @return void
     * @throws \Jrean\UserVerification\Exceptions\ModelNotCompliantException
     */
    public function created(User $user)
    {
        UserVerification::generate($user);

        UserVerification::send($user, 'User email verification required');
    }
}
