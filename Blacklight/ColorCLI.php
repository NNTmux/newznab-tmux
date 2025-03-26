<?php

namespace Blacklight;

use League\CLImate\CLImate;

/**
 * Class ColorCLI.
 */
class ColorCLI
{
    protected CLImate $climate;

    /**
     * ColorCLI constructor.
     */
    public function __construct()
    {
        $this->climate = new CLImate;
    }

    public function debug(string $str, bool $newline = false): void
    {
        if ($newline) {
            $this->climate->br();
        }
        $this->climate->lightGray()->out($str);
    }

    public function info(string $str, bool $newline = false): void
    {
        if ($newline) {
            $this->climate->br();
        }
        $this->climate->magenta()->out($str);
    }

    public function notice(string $str, bool $newline = false): void
    {
        if ($newline) {
            $this->climate->br();
        }
        $this->climate->blue()->out($str);
    }

    public function warning(string $str, bool $newline = false): void
    {
        if ($newline) {
            $this->climate->br();
        }
        $this->climate->yellow()->out($str);
    }

    public function error(string $str, bool $newline = false): void
    {
        if ($newline) {
            $this->climate->br();
        }
        $this->climate->red()->out($str);
    }

    public function primary(string $str, bool $newline = false): void
    {
        if ($newline) {
            $this->climate->br();
        }
        $this->climate->green()->out($str);
    }

    public function header(string $str, bool $newline = false): void
    {
        if ($newline) {
            $this->climate->br();
        }
        $this->climate->yellow()->out($str);
    }

    public function alternate(string $str, bool $newline = false): void
    {
        if ($newline) {
            $this->climate->br();
        }
        $this->climate->magenta()->bold()->out($str);
    }

    public function tmuxOrange(string $str, bool $newline = false): void
    {
        if ($newline) {
            $this->climate->br();
        }
        $this->climate->yellow()->bold()->out($str);
    }

    public function primaryOver(string $str): void
    {
        $this->climate->green()->inline($str);
    }

    public function headerOver(string $str): void
    {
        $this->climate->yellow()->inline($str);
    }

    public function alternateOver(string $str): void
    {
        $this->climate->magenta()->bold()->inline($str);
    }

    public function warningOver(string $str): void
    {
        $this->climate->red()->inline($str);
    }

    public function progress(): mixed
    {
        return $this->climate->progress();
    }

    public function climate(): CLImate
    {
        return $this->climate;
    }
}
