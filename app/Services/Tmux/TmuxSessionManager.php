<?php

declare(strict_types=1);

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

    private ?string $lastError = null;

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

    public function sessionName(): string
    {
        return $this->sessionName;
    }

    /**
     * Check if tmux session exists
     */
    public function sessionExists(): bool
    {
        $result = Process::timeout(10)
            ->run(['tmux', 'has-session', '-t', $this->sessionName]);

        return $result->successful();
    }

    /**
     * Create a new tmux session
     */
    public function createSession(string $windowName = 'Monitor'): ?string
    {
        if ($this->sessionExists()) {
            $this->lastError = "Tmux session '{$this->sessionName}' already exists.";

            return null;
        }

        $arguments = ['tmux'];
        if (file_exists($this->configFile)) {
            array_push($arguments, '-f', $this->configFile);
        }
        array_push(
            $arguments,
            'new-session',
            '-d',
            '-P',
            '-F',
            '#{pane_id}',
            '-s',
            $this->sessionName,
            '-n',
            $windowName,
            'true',
        );

        $result = Process::timeout(30)->run($arguments);

        if (! $result->successful()) {
            $this->lastError = trim($result->errorOutput());

            return null;
        }

        $paneId = trim($result->output());
        if (! preg_match('/^%[0-9]+$/', $paneId)) {
            $this->lastError = "Tmux returned an invalid pane ID: '{$paneId}'.";

            return null;
        }

        $this->lastError = null;

        return $paneId;
    }

    /**
     * Kill the tmux session
     */
    public function killSession(): bool
    {
        if (! $this->sessionExists()) {
            return true;
        }

        $result = Process::timeout(30)->run(['tmux', 'kill-session', '-t', $this->sessionName]);

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

        passthru('tmux attach-session -t '.escapeshellarg($this->sessionName), $exitCode);

        return $exitCode === 0;
    }

    /**
     * List all panes in the session
     *
     * @return array<string, mixed>
     */
    public function listPanes(): array
    {
        if (! $this->sessionExists()) {
            return [];
        }

        $result = Process::timeout(10)->run(
            ['tmux', 'list-panes', '-s', '-t', $this->sessionName, '-F', "#{window_index}:#{pane_index}\t#{pane_title}"]
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

            [$position, $title] = array_pad(explode("\t", $line, 2), 2, '');
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
            ['tmux', 'display-message', '-p', '-t', "{$this->sessionName}:{$window}.{$pane}", '#{pane_current_command}']
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

    public function lastError(): ?string
    {
        return $this->lastError;
    }
}
