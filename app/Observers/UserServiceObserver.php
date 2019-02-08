<?php

namespace App\Observers;

use App\Models\User;
use App\Jobs\SendWelcomeEmail;
use App\Jobs\SendAccountDeletedEmail;
use App\Jobs\SendNewRegisteredAccountMail;
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
        SendNewRegisteredAccountMail::dispatch($user);
        SendWelcomeEmail::dispatch($user);
        UserVerification::generate($user);

        UserVerification::send($user, 'User email verification required');
    }

    /**
     * @param \App\Models\User $user
     */
    public function deleting(User $user)
    {
        SendAccountDeletedEmail::dispatch($user);
    }
}
