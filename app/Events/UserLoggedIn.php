<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserLoggedIn
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public User $user;

    public string $ip;

    /**
     * Create a new event instance.
     */
    public function __construct(User $user, string $ip = '')
    {
        $this->user = $user;
        $this->ip = $ip;
    }
}
