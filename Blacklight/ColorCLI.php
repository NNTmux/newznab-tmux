<?php

namespace Blacklight;

class ColorCLI
{
    /**
     * @param $str
     *
     * @return string
     */
    public static function debug($str)
    {
        return color('Debug: '.$str)->light_gray().PHP_EOL;
    }

    /**
     * @param $str
     * @return string
     */
    public static function info($str): string
    {
        return color('Info: '.$str)->fg('magenta');
    }

    /**
     * @param $str
     * @return string
     */
    public static function notice($str): string
    {
        return color('Notice: '.$str)->blue();
    }

    /**
     * @param $str
     * @return string
     */
    public static function warning($str): string
    {
        return color('Warning: '.$str)->fg('yellow');
    }

    /**
     * @param $str
     * @return string
     */
    public static function error($str): string
    {
        return color('Error: '.$str)->fg('red');
    }

    /**
     * @param $str
     * @return string
     */
    public static function primary($str): string
    {
        return color($str)->green();
    }

    /**
     * @param $str
     * @return string
     */
    public static function header($str): string
    {
        return color($str)->yellow();
    }

    /**
     * @param $str
     * @return string
     */
    public static function alternate($str): string
    {
        return color($str)->magenta()->dark()->bold();
    }

    /**
     * @param $str
     * @return string
     */
    public static function tmuxOrange($str): string
    {
        return color($str)->yellow()->dark();
    }

    /**
     * @param $str
     * @return string
     */
    public static function primaryOver($str): string
    {
        return color($str)->green();
    }

    /**
     * @param $str
     * @return string
     */
    public static function headerOver($str): string
    {
        return color($str)->yellow();
    }

    /**
     * @param $str
     * @return string
     */
    public static function alternateOver($str): string
    {
        return color($str)->magenta()->dark();
    }

    /**
     * @param $str
     * @return string
     */
    public static function warningOver($str): string
    {
        return color($str)->red();
    }

    /**
     * Echo message to CLI.
     *
     * @param string $message The message.
     * @param bool $nl Add a new line?
     * @void
     */
    public static function doEcho($message, $nl = false): void
    {
        echo $message.($nl ? PHP_EOL : '');
    }
}
