<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserAccessedApi
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @var \App\Models\User
     */
    public $user;

    public ?string $ip;

    /**
     * Create a new event instance.
     */
    public function __construct(mixed $user, ?string $ip = null)
    {
        $this->user = $user;
        $this->ip = $ip;
    }
}
