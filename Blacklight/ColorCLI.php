<?php

namespace Blacklight;

use function Termwind\{render};

/**
 * Class ColorCLI.
 */
class ColorCLI
{
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
        return new class {
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
}
