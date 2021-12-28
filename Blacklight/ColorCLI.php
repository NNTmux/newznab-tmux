<?php

namespace Blacklight;

use League\CLImate\CLImate;

/**
 * Class ColorCLI.
 */
class ColorCLI
{
    /**
     * @var \League\CLImate\CLImate
     */
    protected $climate;

    /**
     * ColorCLI constructor.
     */
    public function __construct()
    {
        $this->climate = new CLImate();
    }

    /**
     * @param  string  $str
     * @param  bool  $newline
     */
    public function debug(string $str, bool $newline = false): void
    {
        if ($newline) {
            $this->climate->br();
        }
        $this->climate->lightGray()->out($str);
    }

    /**
     * @param  string  $str
     * @param  bool  $newline
     */
    public function info(string $str, bool $newline = false): void
    {
        if ($newline) {
            $this->climate->br();
        }
        $this->climate->magenta()->out($str);
    }

    /**
     * @param  string  $str
     * @param  bool  $newline
     */
    public function notice(string $str, bool $newline = false): void
    {
        if ($newline) {
            $this->climate->br();
        }
        $this->climate->blue()->out($str);
    }

    /**
     * @param  string  $str
     * @param  bool  $newline
     */
    public function warning(string $str, bool $newline = false): void
    {
        if ($newline) {
            $this->climate->br();
        }
        $this->climate->yellow()->out($str);
    }

    /**
     * @param  string  $str
     * @param  bool  $newline
     */
    public function error(string $str, bool $newline = false): void
    {
        if ($newline) {
            $this->climate->br();
        }
        $this->climate->red()->out($str);
    }

    /**
     * @param  string  $str
     * @param  bool  $newline
     */
    public function primary(string $str, bool $newline = false): void
    {
        if ($newline) {
            $this->climate->br();
        }
        $this->climate->green()->out($str);
    }

    /**
     * @param  string  $str
     * @param  bool  $newline
     */
    public function header(string $str, bool $newline = false): void
    {
        if ($newline) {
            $this->climate->br();
        }
        $this->climate->yellow()->out($str);
    }

    /**
     * @param  string  $str
     * @param  bool  $newline
     */
    public function alternate(string $str, bool $newline = false): void
    {
        if ($newline) {
            $this->climate->br();
        }
        $this->climate->magenta()->bold()->out($str);
    }

    /**
     * @param  string  $str
     * @param  bool  $newline
     */
    public function tmuxOrange(string $str, bool $newline = false): void
    {
        if ($newline) {
            $this->climate->br();
        }
        $this->climate->yellow()->bold()->out($str);
    }

    /**
     * @param  string  $str
     */
    public function primaryOver(string $str): void
    {
        $this->climate->green()->inline($str);
    }

    /**
     * @param  string  $str
     */
    public function headerOver(string $str): void
    {
        $this->climate->yellow()->inline($str);
    }

    /**
     * @param  string  $str
     */
    public function alternateOver(string $str): void
    {
        $this->climate->magenta()->bold()->inline($str);
    }

    /**
     * @param  string  $str
     */
    public function warningOver(string $str): void
    {
        $this->climate->red()->inline($str);
    }
}
