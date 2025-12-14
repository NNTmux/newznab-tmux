<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: int
{
    case USER = 1;
    case ADMIN = 2;
    case DISABLED = 3;
    case MODERATOR = 4;

    public function label(): string
    {
        return match ($this) {
            self::USER => 'User',
            self::ADMIN => 'Admin',
            self::DISABLED => 'Disabled',
            self::MODERATOR => 'Moderator',
        };
    }

    public function isAdmin(): bool
    {
        return $this === self::ADMIN;
    }

    public function isModerator(): bool
    {
        return $this === self::MODERATOR;
    }

    public function isDisabled(): bool
    {
        return $this === self::DISABLED;
    }

    public function hasElevatedPrivileges(): bool
    {
        return in_array($this, [self::ADMIN, self::MODERATOR], true);
    }
}

