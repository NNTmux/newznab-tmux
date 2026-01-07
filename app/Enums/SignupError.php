<?php

declare(strict_types=1);

namespace App\Enums;

enum SignupError: int
{
    case BAD_USERNAME = -1;
    case BAD_PASSWORD = -2;
    case BAD_EMAIL = -3;
    case USERNAME_IN_USE = -4;
    case EMAIL_IN_USE = -5;
    case BAD_INVITE_CODE = -6;
    case SUCCESS = 1;

    public function message(): string
    {
        return match ($this) {
            self::BAD_USERNAME => 'Invalid username provided.',
            self::BAD_PASSWORD => 'Invalid password provided.',
            self::BAD_EMAIL => 'Invalid email address provided.',
            self::USERNAME_IN_USE => 'Username is already in use.',
            self::EMAIL_IN_USE => 'Email address is already in use.',
            self::BAD_INVITE_CODE => 'Invalid or expired invite code.',
            self::SUCCESS => 'Registration successful.',
        };
    }

    public function isError(): bool
    {
        return $this->value < 0;
    }

    public function isSuccess(): bool
    {
        return $this === self::SUCCESS;
    }
}
