<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserLoggedIn
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @var \App\Models\User
     */
    public $user;

    public $ip;

    /**
     * Create a new event instance.
     */
    public function __construct($user, $ip = '')
    {
        $this->user = $user;
        $this->ip = $ip;
    }
}
