<?php

namespace App\Observers;

use App\Jobs\SendNewRegisteredAccountMail;
use App\Jobs\SendWelcomeEmail;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Password;
use Jrean\UserVerification\Facades\UserVerification;
use Spatie\Permission\Models\Role;

class UserServiceObserver
{
    /**
     * Handle the user "created" event.
     *
     *
     * @throws \Jrean\UserVerification\Exceptions\ModelNotCompliantException
     */
    public function created(User $user): void
    {
        $roleData = Role::query()->where('id', $user->roles_id);
        $rateLimit = $roleData->value('rate_limit');
        $roleName = $roleData->value('name');
        $user->syncRoles([$roleName]);
        $user->update(
            [
                'api_token' => md5(Password::getRepository()->createNewToken()),
                'rate_limit' => $rateLimit,
            ]
        );
        if (! empty(config('mail.from.address') && File::isFile(base_path().'/_install/install.lock'))) {
            SendNewRegisteredAccountMail::dispatch($user)->onQueue('newreg');
            SendWelcomeEmail::dispatch($user)->onQueue('welcomeemails');
            UserVerification::generate($user);

            UserVerification::send($user, 'User email verification required', config('mail.from.address'));
        }
    }
}
