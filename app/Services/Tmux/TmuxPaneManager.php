<?php

declare(strict_types=1);

namespace App\Services\Tmux;

use App\Enums\TmuxPaneRole;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Service for managing individual tmux panes
 */
class TmuxPaneManager
{
    private const ROLE_OPTION = '@nntmux_role';

    protected string $sessionName;

    /**
     * @var array<string, string>|null
     */
    private ?array $roleTargets = null;

    private ?string $lastError = null;

    public function __construct(string $sessionName)
    {
        $this->sessionName = $sessionName;
    }

    /**
     * Create a new window
     */
    public function createWindow(int $index, string $name): ?string
    {
        $result = Process::timeout(10)->run(
            ['tmux', 'new-window', '-P', '-F', '#{pane_id}', '-t', "{$this->sessionName}:{$index}", '-n', $name, 'true']
        );

        return $this->createdPaneId($result->successful(), $result->output(), $result->errorOutput());
    }

    /**
     * Split a pane horizontally
     */
    public function splitHorizontal(string $target, int $percentage): ?string
    {
        $result = Process::timeout(10)->run(
            ['tmux', 'split-window', '-P', '-F', '#{pane_id}', '-t', $this->target($target), '-h', '-l', "{$percentage}%", 'true']
        );

        return $this->createdPaneId($result->successful(), $result->output(), $result->errorOutput());
    }

    /**
     * Split a pane vertically
     */
    public function splitVertical(string $target, int $percentage): ?string
    {
        $result = Process::timeout(10)->run(
            ['tmux', 'split-window', '-P', '-F', '#{pane_id}', '-t', $this->target($target), '-v', '-l', "{$percentage}%", 'true']
        );

        return $this->createdPaneId($result->successful(), $result->output(), $result->errorOutput());
    }

    /**
     * Select a specific pane
     */
    public function selectPane(string $target): bool
    {
        $result = Process::timeout(5)->run(
            ['tmux', 'select-pane', '-t', $this->target($target)]
        );

        return $this->recordResult($result);
    }

    /**
     * Select a specific window
     */
    public function selectWindow(int $window): bool
    {
        $result = Process::timeout(5)->run(
            ['tmux', 'select-window', '-t', "{$this->sessionName}:{$window}"]
        );

        return $this->recordResult($result);
    }

    /**
     * Respawn a pane with a new command
     */
    public function respawnPane(string $target, string $command, bool $kill = false): bool
    {
        $arguments = ['tmux', 'respawn-pane'];
        if ($kill) {
            $arguments[] = '-k';
        }
        array_push($arguments, '-t', $this->target($target), $command);

        $result = Process::timeout(10)->run(
            $arguments
        );

        return $this->recordResult($result);
    }

    /**
     * Send keys to a pane
     */
    public function sendKeys(string $target, string $keys, bool $enter = true): bool
    {
        $result = Process::timeout(5)->run(
            array_values(array_filter(
                ['tmux', 'send-keys', '-t', $this->target($target), '--', $keys, $enter ? 'Enter' : null],
                static fn (?string $argument): bool => $argument !== null,
            ))
        );

        return $this->recordResult($result);
    }

    /**
     * Kill a specific pane
     */
    public function killPane(string $target): bool
    {
        $result = Process::timeout(5)->run(
            ['tmux', 'kill-pane', '-t', $this->target($target)]
        );

        return $this->recordResult($result);
    }

    /**
     * Set pane title
     */
    public function setPaneTitle(string $target, string $title): bool
    {
        $result = Process::timeout(5)->run(
            ['tmux', 'select-pane', '-t', $this->target($target), '-T', $title]
        );

        return $this->recordResult($result);
    }

    /**
     * Get pane title
     */
    public function getPaneTitle(string $target): ?string
    {
        $result = Process::timeout(5)->run(
            ['tmux', 'display-message', '-p', '-t', $this->target($target), '#{pane_title}']
        );

        return $result->successful() ? trim($result->output()) : null;
    }

    /**
     * Capture pane content
     */
    public function capturePane(string $target, int $lines = 100): string
    {
        $result = Process::timeout(10)->run(
            ['tmux', 'capture-pane', '-p', '-t', $this->target($target), '-S', "-{$lines}"]
        );

        return $result->successful() ? $result->output() : '';
    }

    public function setPaneRole(string $target, TmuxPaneRole $role): bool
    {
        $result = Process::timeout(5)->run(
            ['tmux', 'set-option', '-p', '-t', $this->target($target), self::ROLE_OPTION, $role->value]
        );

        if ($this->recordResult($result)) {
            $this->roleTargets = null;

            return true;
        }

        return false;
    }

    public function paneForRole(TmuxPaneRole $role, ?string $legacyTarget = null): string
    {
        $targets = $this->roleTargets();

        if (isset($targets[$role->value])) {
            return $targets[$role->value];
        }

        if ($legacyTarget !== null) {
            return $this->tagLegacyPane($role, $legacyTarget);
        }

        throw new RuntimeException("Tmux pane role '{$role->value}' was not found in session '{$this->sessionName}'.");
    }

    public function lastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * @return array<string, string>
     */
    private function roleTargets(): array
    {
        if ($this->roleTargets !== null) {
            return $this->roleTargets;
        }

        $result = Process::timeout(10)->run(
            ['tmux', 'list-panes', '-s', '-t', $this->sessionName, '-F', "#{pane_id}\t#{".self::ROLE_OPTION.'}']
        );

        if (! $result->successful()) {
            throw new RuntimeException(
                "Unable to list panes for tmux session '{$this->sessionName}': ".trim($result->errorOutput())
            );
        }

        $targets = [];
        foreach (preg_split('/\R/', trim($result->output())) ?: [] as $line) {
            if ($line === '') {
                continue;
            }

            [$paneId, $role] = array_pad(explode("\t", $line, 2), 2, '');
            if ($role === '') {
                continue;
            }

            if (isset($targets[$role])) {
                throw new RuntimeException(
                    "Tmux pane role '{$role}' is assigned more than once in session '{$this->sessionName}'."
                );
            }

            $targets[$role] = $paneId;
        }

        return $this->roleTargets = $targets;
    }

    private function target(string $target): string
    {
        return str_starts_with($target, '%') ? $target : "{$this->sessionName}:{$target}";
    }

    private function tagLegacyPane(TmuxPaneRole $role, string $legacyTarget): string
    {
        $result = Process::timeout(5)->run(
            ['tmux', 'display-message', '-p', '-t', $this->target($legacyTarget), '#{pane_id}']
        );
        $paneId = trim($result->output());

        if (! $result->successful() || ! preg_match('/^%[0-9]+$/', $paneId)) {
            throw new RuntimeException(
                "Tmux pane role '{$role->value}' and legacy target '{$legacyTarget}' were not found in session '{$this->sessionName}'."
            );
        }

        if (! $this->setPaneRole($paneId, $role)) {
            throw new RuntimeException(
                "Unable to tag legacy tmux pane '{$legacyTarget}' as '{$role->value}': {$this->lastError}."
            );
        }

        return $paneId;
    }

    private function createdPaneId(bool $successful, string $output, string $errorOutput): ?string
    {
        if (! $successful) {
            $this->lastError = trim($errorOutput);

            return null;
        }

        $paneId = trim($output);
        if (! preg_match('/^%[0-9]+$/', $paneId)) {
            $this->lastError = "Tmux returned an invalid pane ID: '{$paneId}'.";

            return null;
        }

        $this->lastError = null;

        return $paneId;
    }

    private function recordResult(ProcessResult $result): bool
    {
        if ($result->successful()) {
            $this->lastError = null;

            return true;
        }

        $this->lastError = trim($result->errorOutput());

        return false;
    }
}
