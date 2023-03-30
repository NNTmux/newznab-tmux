<?php

namespace App\Providers;

use App\Events\UserAccessedApi;
use App\Events\UserLoggedIn;
use App\Listeners\UpdateUserAccessedApi;
use App\Listeners\UpdateUserLoggedIn;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],

        UserLoggedIn::class => [
            UpdateUserLoggedIn::class,
        ],

        UserAccessedApi::class => [
            UpdateUserAccessedApi::class,
        ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot(): void
    {
        parent::boot();

        //
    }
}
