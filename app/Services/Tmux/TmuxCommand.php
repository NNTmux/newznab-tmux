<?php

declare(strict_types=1);

namespace App\Services\Tmux;

final class TmuxCommand
{
    /** @param list<string> $arguments
     * @return list<string>
     */
    public static function arguments(array $arguments): array
    {
        $socket = config('tmux.socket_name', '');

        return array_merge(['tmux'], $socket === '' ? [] : ['-L', $socket], $arguments);
    }

    /** @return list<string> */
    public static function monitor(string $session): array
    {
        return [PHP_BINARY, base_path('artisan'), 'tmux:monitor', '--session='.$session];
    }
}
