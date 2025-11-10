<?php

namespace Blacklight;

/**
 * Class ConsoleTools.
 */
class ConsoleTools extends ColorCLI
{
    public int $lastMessageLength;

    /**
     * ConsoleTools constructor.
     */
    public function __construct()
    {
        $this->lastMessageLength = 0;
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
