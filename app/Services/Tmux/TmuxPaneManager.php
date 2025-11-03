<?php

namespace App\Services\Tmux;

use Illuminate\Support\Facades\Process;

/**
 * Service for managing individual tmux panes
 */
class TmuxPaneManager
{
    protected string $sessionName;

    public function __construct(string $sessionName)
    {
        $this->sessionName = $sessionName;
    }

    /**
     * Create a new window
     */
    public function createWindow(int $index, string $name): bool
    {
        $result = Process::timeout(10)->run(
            "tmux new-window -t {$this->sessionName}:{$index} -n {$name} 'printf \"\\033]2;{$name}\\033\"'"
        );

        return $result->successful();
    }

    /**
     * Split a pane horizontally
     */
    public function splitHorizontal(string $target, string $percentage, string $title = ''): bool
    {
        $titleCmd = $title ? "printf \"\\033]2;{$title}\\033\"" : '';

        $result = Process::timeout(10)->run(
            "tmux splitw -t {$this->sessionName}:{$target} -h -l {$percentage} '{$titleCmd}'"
        );

        return $result->successful();
    }

    /**
     * Split a pane vertically
     */
    public function splitVertical(string $target, string $percentage, string $title = ''): bool
    {
        $titleCmd = $title ? "printf \"\\033]2;{$title}\\033\"" : '';

        $result = Process::timeout(10)->run(
            "tmux splitw -t {$this->sessionName}:{$target} -v -l {$percentage} '{$titleCmd}'"
        );

        return $result->successful();
    }

    /**
     * Select a specific pane
     */
    public function selectPane(string $target): bool
    {
        $result = Process::timeout(5)->run(
            "tmux selectp -t {$this->sessionName}:{$target}"
        );

        return $result->successful();
    }

    /**
     * Select a specific window
     */
    public function selectWindow(int $window): bool
    {
        $result = Process::timeout(5)->run(
            "tmux select-window -t {$this->sessionName}:{$window}"
        );

        return $result->successful();
    }

    /**
     * Respawn a pane with a new command
     */
    public function respawnPane(string $target, string $command, bool $kill = false): bool
    {
        $killFlag = $kill ? '-k' : '';

        // Escape the command for tmux by replacing double quotes with escaped quotes
        $escapedCommand = str_replace('"', '\\"', $command);
        $escapedCommand = str_replace('$', '\\$', $escapedCommand);

        $result = Process::timeout(10)->run(
            "tmux respawnp {$killFlag} -t {$this->sessionName}:{$target} \"{$escapedCommand}\""
        );

        return $result->successful();
    }

    /**
     * Send keys to a pane
     */
    public function sendKeys(string $target, string $keys, bool $enter = true): bool
    {
        $enterKey = $enter ? ' Enter' : '';

        $result = Process::timeout(5)->run(
            "tmux send-keys -t {$this->sessionName}:{$target} '{$keys}'{$enterKey}"
        );

        return $result->successful();
    }

    /**
     * Kill a specific pane
     */
    public function killPane(string $target): bool
    {
        $result = Process::timeout(5)->run(
            "tmux kill-pane -t {$this->sessionName}:{$target}"
        );

        return $result->successful();
    }

    /**
     * Set pane title
     */
    public function setPaneTitle(string $target, string $title): bool
    {
        $result = Process::timeout(5)->run(
            "tmux select-pane -t {$this->sessionName}:{$target} -T '{$title}'"
        );

        return $result->successful();
    }

    /**
     * Get pane title
     */
    public function getPaneTitle(string $target): ?string
    {
        $result = Process::timeout(5)->run(
            "tmux display-message -p -t {$this->sessionName}:{$target} '#{pane_title}'"
        );

        return $result->successful() ? trim($result->output()) : null;
    }

    /**
     * Capture pane content
     */
    public function capturePane(string $target, int $lines = 100): string
    {
        $result = Process::timeout(10)->run(
            "tmux capture-pane -p -t {$this->sessionName}:{$target} -S -{$lines}"
        );

        return $result->successful() ? $result->output() : '';
    }
}
