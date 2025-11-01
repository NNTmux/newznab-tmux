<?php

namespace App\Services\Tmux;

use App\Models\Settings;
use Illuminate\Support\Facades\Process;

/**
 * Service for managing tmux sessions and panes
 */
class TmuxSessionManager
{
    protected string $sessionName;

    protected string $configFile;

    public function __construct(?string $sessionName = null)
    {
        $this->sessionName = $sessionName ?? $this->getSessionName();
        $this->configFile = config('tmux.config_file');
    }

    /**
     * Get the tmux session name from settings or config
     */
    public function getSessionName(): string
    {
        return Settings::settingValue('tmux_session')
            ?? config('tmux.session.name')
            ?? config('tmux.session.default_name', 'nntmux');
    }

    /**
     * Check if tmux session exists
     */
    public function sessionExists(): bool
    {
        $result = Process::timeout(10)
            ->run("tmux list-sessions 2>/dev/null | grep -q '^{$this->sessionName}:'");

        return $result->successful();
    }

    /**
     * Create a new tmux session
     */
    public function createSession(string $windowName = 'Monitor'): bool
    {
        if ($this->sessionExists()) {
            return false;
        }

        $configOption = file_exists($this->configFile) ? "-f {$this->configFile}" : '';

        $result = Process::timeout(30)->run(
            "tmux {$configOption} new-session -d -s {$this->sessionName} -n {$windowName} 'printf \"\\033]2;{$windowName}\\033\"'"
        );

        return $result->successful();
    }

    /**
     * Kill the tmux session
     */
    public function killSession(): bool
    {
        if (! $this->sessionExists()) {
            return true;
        }

        $result = Process::timeout(30)->run("tmux kill-session -t {$this->sessionName}");

        return $result->successful();
    }

    /**
     * Attach to the tmux session
     */
    public function attachSession(): bool
    {
        if (! $this->sessionExists()) {
            return false;
        }

        $result = Process::timeout(5)->run(
            "tmux select-window -t {$this->sessionName}:0; tmux attach-session -d -t {$this->sessionName}"
        );

        return $result->successful();
    }

    /**
     * List all panes in the session
     */
    public function listPanes(): array
    {
        if (! $this->sessionExists()) {
            return [];
        }

        $result = Process::timeout(10)->run(
            "tmux list-panes -s -t {$this->sessionName} -F '#{window_index}:#{pane_index} #{pane_title}'"
        );

        if (! $result->successful()) {
            return [];
        }

        $panes = [];
        $lines = explode("\n", trim($result->output()));

        foreach ($lines as $line) {
            if (empty($line)) {
                continue;
            }

            [$position, $title] = explode(' ', $line, 2);
            $panes[$position] = $title;
        }

        return $panes;
    }

    /**
     * Get pane status
     */
    public function getPaneStatus(string $window, string $pane): ?string
    {
        $result = Process::timeout(5)->run(
            "tmux display-message -p -t {$this->sessionName}:{$window}.{$pane} '#{pane_current_command}'"
        );

        return $result->successful() ? trim($result->output()) : null;
    }

    /**
     * Check if a pane is running a command
     */
    public function isPaneActive(string $window, string $pane): bool
    {
        $status = $this->getPaneStatus($window, $pane);

        return $status !== null && $status !== 'bash' && $status !== 'sh';
    }
}
