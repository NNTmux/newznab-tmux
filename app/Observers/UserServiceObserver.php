<?php

namespace App\Observers;

use App\Models\Settings;
use App\Models\User;
use Illuminate\Support\Str;
use App\Jobs\SendWelcomeEmail;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\File;
use App\Jobs\SendAccountDeletedEmail;
use Illuminate\Support\Facades\Password;
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
        $roleData = Role::query()->where('id', $user->roles_id);
        $rateLimit = $roleData->value('rate_limit');
        $roleName = $roleData->value('name');
        $user->assignRole($roleName);
        $user->update(
            [
                'api_token' => md5(Password::getRepository()->createNewToken()),
                'userseed' => md5(Str::uuid()->toString()),
                'rate_limit' => $rateLimit,
            ]
        );
        if (! empty(Settings::settingValue('site.main.email') && File::isFile(base_path().'/_install/install.lock'))) {
            SendNewRegisteredAccountMail::dispatch($user);
            SendWelcomeEmail::dispatch($user);
            UserVerification::generate($user);

            UserVerification::send($user, 'User email verification required');
        }
    }

    /**
     * @param \App\Models\User $user
     */
    public function deleting(User $user)
    {
        SendAccountDeletedEmail::dispatch($user);
    }
}
