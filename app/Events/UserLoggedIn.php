<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserLoggedIn
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public \App\Models\User $user;

    public string $ip;

    /**
     * Create a new event instance.
     */
    public function __construct(\App\Models\User $user, string $ip = '')
    {
        $this->user = $user;
        $this->ip = $ip;
    }
}
