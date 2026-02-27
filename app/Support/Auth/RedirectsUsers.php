<?php

declare(strict_types=1);

namespace App\Support\Auth;

trait RedirectsUsers
{
    /**
     * Get the post register / login redirect path.
     */
    public function redirectPath(): string
    {
        if (method_exists($this, 'redirectTo')) {
            return $this->redirectTo();
        }

        return isset($this->redirectTo) ? $this->redirectTo : '/home';
    }
}
