<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class Admin extends Model
{
    use Notifiable;

    protected $admin;
    protected $email;

    /**
     * Admin constructor.
     *
     * @throws \Exception
     */
    public function __construct()
    {
        $this->admin = env('ADMIN_USER');
        $this->email = Settings::settingValue('site.main.email');
    }
}
