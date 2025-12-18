<?php

namespace Blacklight;

use function Termwind\render;

/**
 * Class ColorCLI.
 */
class ColorCLI
{
    public int $lastMessageLength = 0;

    public function debug(string $str, bool $newline = false): void
    {
        if ($newline) {
            render('<br/>');
        }
        render("<div class='text-gray'>{$str}</div>");
    }

    public function info(string $str, bool $newline = false): void
    {
        if ($newline) {
            render('<br/>');
        }
        render("<div class='text-magenta'>{$str}</div>");
    }

    public function notice(string $str, bool $newline = false): void
    {
        if ($newline) {
            render('<br/>');
        }
        render("<div class='text-blue'>{$str}</div>");
    }

    public function warning(string $str, bool $newline = false): void
    {
        if ($newline) {
            render('<br/>');
        }
        render("<div class='text-yellow'>{$str}</div>");
    }

    public function error(string $str, bool $newline = false): void
    {
        if ($newline) {
            render('<br/>');
        }
        render("<div class='text-red'>{$str}</div>");
    }

    public function primary(string $str, bool $newline = false): void
    {
        if ($newline) {
            render('<br/>');
        }
        render("<div class='text-green'>{$str}</div>");
    }

    public function header(string $str, bool $newline = false): void
    {
        if ($newline) {
            render('<br/>');
        }
        render("<div class='text-yellow'>{$str}</div>");
    }

    public function alternate(string $str, bool $newline = false): void
    {
        if ($newline) {
            render('<br/>');
        }
        render("<div class='text-magenta font-bold'>{$str}</div>");
    }

    public function tmuxOrange(string $str, bool $newline = false): void
    {
        if ($newline) {
            render('<br/>');
        }
        render("<div class='text-yellow font-bold'>{$str}</div>");
    }

    public function primaryOver(string $str): void
    {
        echo "\033[32m{$str}\033[0m";
    }

    public function headerOver(string $str): void
    {
        echo "\033[33m{$str}\033[0m";
    }

    public function alternateOver(string $str): void
    {
        echo "\033[1;35m{$str}\033[0m";
    }

    public function warningOver(string $str): void
    {
        echo "\033[31m{$str}\033[0m";
    }

    public function progress(): object
    {
        return new class
        {
            private int $total = 0;

            private int $current = 0;

            private string $label = '';

            public function total(int $total): self
            {
                $this->total = $total;

                return $this;
            }

            public function current(int $current, ?string $label = null): void
            {
                $this->current = $current;
                if ($label !== null) {
                    $this->label = $label;
                }
                $this->display();
            }

            public function advance(int $step = 1, ?string $label = null): void
            {
                $this->current += $step;
                if ($label !== null) {
                    $this->label = $label;
                }
                $this->display();
            }

            private function display(): void
            {
                $percentage = $this->total > 0 ? (int) (($this->current / $this->total) * 100) : 0;
                $bar = str_repeat('=', (int) ($percentage / 2));
                $spaces = str_repeat(' ', 50 - (int) ($percentage / 2));
                $label = $this->label ? " {$this->label}" : '';
                echo "\r[{$bar}{$spaces}] {$percentage}%{$label}";
                if ($this->current >= $this->total) {
                    echo "\n";
                }
            }
        };
    }

    /**
     * Apply ANSI color code to a string and return it (does not render)
     *
     * @param  string  $string  The string to colorize
     * @param  string  $color  The color name
     * @return string The colored string with ANSI codes
     */
    public function ansiString(string $string, string $color): string
    {
        $colors = [
            'black' => '0;30',
            'red' => '0;31',
            'green' => '0;32',
            'yellow' => '0;33',
            'blue' => '0;34',
            'magenta' => '0;35',
            'cyan' => '0;36',
            'white' => '0;37',
        ];

        $code = $colors[$color] ?? '0;37';

        return "\033[{$code}m{$string}\033[0m";
    }

    public function overWriteHeader(string $message, bool $reset = false): void
    {
        if ($reset) {
            $this->lastMessageLength = 0;
        }

        echo str_repeat(\chr(8), $this->lastMessageLength);
        echo str_repeat(' ', $this->lastMessageLength);
        echo str_repeat(\chr(8), $this->lastMessageLength);

        $this->lastMessageLength = \strlen($message);
        $this->headerOver($message);
    }

    public function overWritePrimary(string $message, bool $reset = false): void
    {
        if ($reset) {
            $this->lastMessageLength = 0;
        }

        echo str_repeat(\chr(8), $this->lastMessageLength);
        echo str_repeat(' ', $this->lastMessageLength);
        echo str_repeat(\chr(8), $this->lastMessageLength);

        $this->lastMessageLength = \strlen($message);
        $this->primaryOver($message);
    }

    public function overWrite(string $message, bool $reset = false): void
    {
        if ($reset) {
            $this->lastMessageLength = 0;
        }

        echo str_repeat(\chr(8), $this->lastMessageLength);
        echo str_repeat(' ', $this->lastMessageLength);
        echo str_repeat(\chr(8), $this->lastMessageLength);

        $this->lastMessageLength = \strlen($message);
        echo $message;
    }

    public function appendWrite(string $message): void
    {
        echo $message;
        $this->lastMessageLength += \strlen($message);
    }

    public function percentString(int $cur, int $total): string
    {
        $percent = 100 * $cur / $total;
        $formatString = '% '.\strlen($total).'d/%d (% 2d%%)';

        return sprintf($formatString, $cur, $total, $percent);
    }

    public function percentString2(int $first, int $last, int $total): string
    {
        $percent1 = 100 * ($first - 1) / $total;
        $percent2 = 100 * $last / $total;
        $formatString = '% '.\strlen($total).'d-% '.\strlen($total).'d/%d (% 2d%%-% 3d%%)';

        return sprintf($formatString, $first, $last, $total, $percent1, $percent2);
    }

    /**
     * Convert seconds to minutes or hours, appending type at the end.
     */
    public function convertTime(int $seconds): string
    {
        if ($seconds > 3600) {
            return round($seconds / 3600).' hour(s)';
        }
        if ($seconds > 60) {
            return round($seconds / 60).' minute(s)';
        }

        return $seconds.' second(s)';
    }

    /**
     * Convert seconds to a timer, 00h:00m:00s.
     */
    public function convertTimer(int $seconds): string
    {
        return ' '.sprintf('%02dh:%02dm:%02ds', floor($seconds / 3600), floor(($seconds / 60) % 60), $seconds % 60);
    }

    /**
     * Sleep for x seconds, printing timer on screen.
     */
    public function showSleep(int $seconds): void
    {
        for ($i = $seconds; $i >= 0; $i--) {
            $this->overWriteHeader('Sleeping for '.$i.' seconds.');
            sleep(1);
        }
        echo PHP_EOL;
    }
}
