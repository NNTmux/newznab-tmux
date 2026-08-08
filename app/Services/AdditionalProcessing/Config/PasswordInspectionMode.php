<?php

declare(strict_types=1);

namespace App\Services\AdditionalProcessing\Config;

use App\Services\Releases\ReleaseBrowseService;

final class PasswordInspectionMode
{
    public static function isActive(): bool
    {
        return config('nntmux_settings.check_passworded_rars') === true
            && ! empty(config('nntmux_settings.unrar_path'));
    }

    public static function pendingReleaseStatus(): int
    {
        return self::isActive() ? -1 : ReleaseBrowseService::PASSWD_NONE;
    }
}
