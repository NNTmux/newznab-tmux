<?php

declare(strict_types=1);

namespace App\Observers;

use App\Jobs\SendNewRegisteredAccountMail;
use App\Jobs\SendWelcomeEmail;
use App\Models\TrustedDevice;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class UserServiceObserver
{
    /**
     * Handle the user "created" event.
     */
    public function created(User $user): void
    {
        $roleData = Role::query()->where('id', $user->roles_id);
        $rateLimit = $roleData->value('rate_limit');
        $roleName = $roleData->value('name');
        $user->syncRoles([$roleName]);
        $user->update(
            [
                'api_token' => md5(Str::random(40)),
                'rate_limit' => $rateLimit,
            ]
        );
        if (! empty(config('mail.from.address') && File::isFile(base_path().'/_install/install.lock'))) {
            SendNewRegisteredAccountMail::dispatch($user)->onQueue('newreg');
            SendWelcomeEmail::dispatch($user)->onQueue('welcomeemails');
            $user->sendEmailVerificationNotification();
        }
    }

    /**
     * A new password ends every "trust this device" 2FA skip issued under the old one.
     */
    public function updated(User $user): void
    {
        // A brand-new account has no trusted devices; skip the nested update made in created().
        if (! $user->wasRecentlyCreated && $user->wasChanged('password')) {
            TrustedDevice::query()->where('user_id', $user->id)->delete();
        }
    }
}
