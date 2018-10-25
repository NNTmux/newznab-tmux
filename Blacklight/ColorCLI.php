<?php

namespace Blacklight;

use League\CLImate\CLImate;

class ColorCLI
{
    /**
     * @return \League\CLImate\CLImate
     */
    protected static function climate()
    {
        return new CLImate();
    }

    /**
     * @param $str
     */
    public static function debug($str): void
    {
        self::climate()->lightGray()->out($str);
    }

    /**
     * @param $str
     */
    public static function info($str): void
    {
        self::climate()->magenta()->out($str);
    }

    /**
     * @param $str
     */
    public static function notice($str): void
    {
        self::climate()->blue()->out($str);
    }

    /**
     * @param $str
     */
    public static function warning($str): void
    {
        self::climate()->yellow()->out($str);
    }

    /**
     * @param $str
     */
    public static function error($str): void
    {
        self::climate()->red()->out($str);
    }

    /**
     * @param $str
     */
    public static function primary($str): void
    {
        self::climate()->green()->out($str);
    }

    /**
     * @param $str
     */
    public static function header($str): void
    {
        self::climate()->yellow()->out($str);
    }

    /**
     * @param $str
     */
    public static function alternate($str): void
    {
        self::climate()->magenta()->bold()->out($str);
    }

    /**
     * @param $str
     */
    public static function tmuxOrange($str): void
    {
        self::climate()->yellow()->bold()->out($str);
    }

    /**
     * @param $str
     */
    public static function primaryOver($str): void
    {
        self::climate()->green()->inline($str);
    }

    /**
     * @param $str
     */
    public static function headerOver($str): void
    {
        self::climate()->yellow()->inline($str);
    }

    /**
     * @param $str
     */
    public static function alternateOver($str): void
    {
        self::climate()->magenta()->bold()->inline($str);
    }

    /**
     * @param $str
     */
    public static function warningOver($str): void
    {
        self::climate()->red()->inline($str);
    }
}
