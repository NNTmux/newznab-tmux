<?php

namespace Blacklight;

/**
 * Class ConsoleTools.
 */
class ConsoleTools extends ColorCLI
{
    /**
     * @var int
     */
    public $lastMessageLength;

    /**
     * ConsoleTools constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->lastMessageLength = 0;
    }

    /**
     * @param  string  $message
     * @param  bool  $reset
     */
    public function overWriteHeader($message, $reset = false): void
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

    /**
     * @param  string  $message
     * @param  bool  $reset
     */
    public function overWritePrimary($message, $reset = false): void
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

    /**
     * @param  string  $message
     * @param  bool  $reset
     */
    public function overWrite($message, $reset = false): void
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

    /**
     * @param  string  $message
     */
    public function appendWrite($message): void
    {
        echo $message;
        $this->lastMessageLength += \strlen($message);
    }

    /**
     * @param  int  $cur
     * @param  int  $total
     * @return string
     */
    public function percentString($cur, $total): string
    {
        $percent = 100 * $cur / $total;
        $formatString = '% '.\strlen($total).'d/%d (% 2d%%)';

        return sprintf($formatString, $cur, $total, $percent);
    }

    /**
     * @param  int  $first
     * @param  int  $last
     * @param  int  $total
     * @return string
     */
    public function percentString2($first, $last, $total): string
    {
        $percent1 = 100 * ($first - 1) / $total;
        $percent2 = 100 * $last / $total;
        $formatString = '% '.\strlen($total).'d-% '.\strlen($total).'d/%d (% 2d%%-% 3d%%)';

        return sprintf($formatString, $first, $last, $total, $percent1, $percent2);
    }

    /**
     * Convert seconds to minutes or hours, appending type at the end.
     *
     * @param  int  $seconds
     * @return string
     */
    public function convertTime($seconds): string
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
     *
     * @param  int  $seconds
     * @return string
     */
    public function convertTimer($seconds): string
    {
        return ' '.sprintf('%02dh:%02dm:%02ds', floor($seconds / 3600), floor(($seconds / 60) % 60), $seconds % 60);
    }

    /**
     * Sleep for x seconds, printing timer on screen.
     *
     * @param  int  $seconds
     */
    public function showSleep($seconds): void
    {
        for ($i = $seconds; $i >= 0; $i--) {
            $this->overWriteHeader('Sleeping for '.$i.' seconds.');
            sleep(1);
        }
        echo PHP_EOL;
    }
}
