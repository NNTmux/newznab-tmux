<?php

namespace Blacklight;

use League\CLImate\CLImate;

/**
 * Class ColorCLI.
 */
class ColorCLI
{
    /**
     * @var CLImate
     */
    protected CLImate $climate;

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
     * @return void
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
     * @return void
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
     * @return void
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
     * @return void
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
     * @return void
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
     * @return void
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
     * @return void
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
     * @return void
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
     * @return void
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
     * @return void
     */
    public function primaryOver(string $str): void
    {
        $this->climate->green()->inline($str);
    }

    /**
     * @param  string  $str
     * @return void
     */
    public function headerOver(string $str): void
    {
        $this->climate->yellow()->inline($str);
    }

    /**
     * @param  string  $str
     * @return void
     */
    public function alternateOver(string $str): void
    {
        $this->climate->magenta()->bold()->inline($str);
    }

    /**
     * @param  string  $str
     * @return void
     */
    public function warningOver(string $str): void
    {
        $this->climate->red()->inline($str);
    }
}
