<?php

declare(strict_types=1);

namespace App\Services\Tmux;

use App\Enums\TmuxPaneRole;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
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

    private TmuxSessionManager $sessionManager;

    /** @var array<string, array{role: string, dead: bool, exit_code: ?int, pid: int, pipe: bool, window: string}>|null */
    private ?array $snapshot = null;

    public function __construct(string $sessionName)
    {
        $this->sessionName = $sessionName;
        $this->sessionManager = new TmuxSessionManager($sessionName);
    }

    /**
     * Create a new window
     */
    public function createWindow(int $index, string $name): ?string
    {
        $result = Process::timeout(10)->run(
            TmuxCommand::arguments(['new-window', '-P', '-F', '#{pane_id}', '-t', $this->sessionManager->target().":{$index}", '-n', $name, '-c', base_path(), 'sleep', '86400'])
        );

        return $this->createdPaneId($result->successful(), $result->output(), $result->errorOutput());
    }

    /**
     * Split a pane horizontally
     */
    public function splitHorizontal(string $target, int $percentage): ?string
    {
        $result = Process::timeout(10)->run(
            TmuxCommand::arguments(['split-window', '-P', '-F', '#{pane_id}', '-t', $this->target($target), '-h', '-l', "{$percentage}%", '-c', base_path(), 'sleep', '86400'])
        );

        return $this->createdPaneId($result->successful(), $result->output(), $result->errorOutput());
    }

    /**
     * Split a pane vertically
     */
    public function splitVertical(string $target, int $percentage): ?string
    {
        $result = Process::timeout(10)->run(
            TmuxCommand::arguments(['split-window', '-P', '-F', '#{pane_id}', '-t', $this->target($target), '-v', '-l', "{$percentage}%", '-c', base_path(), 'sleep', '86400'])
        );

        return $this->createdPaneId($result->successful(), $result->output(), $result->errorOutput());
    }

    /**
     * Select a specific pane
     */
    public function selectPane(string $target): bool
    {
        $result = Process::timeout(5)->run(
            TmuxCommand::arguments(['select-pane', '-t', $this->target($target)])
        );

        return $this->recordResult($result);
    }

    /**
     * Select a specific window
     */
    public function selectWindow(int $window): bool
    {
        $target = $this->sessionManager->target().":{$window}";
        $resolved = Process::timeout(5)->run(TmuxCommand::arguments([
            'display-message', '-p', '-t', $target, '#{window_id}',
        ]));
        $windowId = trim($resolved->output());
        if (! $resolved->successful() || ! preg_match('/^@[0-9]+$/', $windowId)) {
            $this->lastError = 'Unable to resolve tmux window ID.';
            $this->refresh();

            return false;
        }
        $result = Process::timeout(5)->run(
            TmuxCommand::arguments(['select-window', '-t', $windowId])
        );

        return $this->recordResult($result);
    }

    /**
     * Respawn a pane with a new command
     *
     * @param  string|list<string>  $command
     * @param  array<string, string>  $environment
     */
    public function respawnPane(string $target, string|array $command, bool $kill = false, ?string $directory = null, array $environment = []): bool
    {
        $arguments = TmuxCommand::arguments(['respawn-pane']);
        if ($kill) {
            $arguments[] = '-k';
        }
        array_push($arguments, '-t', $this->target($target), '-c', $directory ?? base_path());
        foreach ($environment as $name => $value) {
            array_push($arguments, '-e', $name.'='.$value);
        }
        $workerArguments = is_array($command) ? $command : [$command];
        // tmux treats a trailing semicolon in an argv item as a command separator.
        $workerArguments = array_map(static fn (string $argument): string => str_ends_with($argument, ';')
            ? substr($argument, 0, -1).'\\;' : $argument, $workerArguments);
        array_push($arguments, ...$workerArguments);

        $result = Process::timeout(10)->run(
            $arguments
        );

        return $this->recordResult($result);
    }

    public function heartbeat(): bool
    {
        return $this->recordResult(Process::timeout(5)->run(TmuxCommand::arguments([
            'set-option', '-t', $this->sessionManager->target(), '@nntmux_heartbeat', (string) time(),
        ])));
    }

    public function heartbeatTime(): ?int
    {
        $result = Process::timeout(5)->run(TmuxCommand::arguments([
            'show-options', '-v', '-t', $this->sessionManager->target(), '@nntmux_heartbeat',
        ]));

        return $result->successful() && ctype_digit(trim($result->output())) ? (int) trim($result->output()) : null;
    }

    public function installExitHook(): bool
    {
        $signal = implode(' ', array_map('escapeshellarg', TmuxCommand::arguments([
            'wait-for', '-S', $this->eventChannel(),
        ])));

        return $this->recordResult(Process::timeout(5)->run(TmuxCommand::arguments([
            'set-hook', '-t', $this->sessionManager->target(), 'pane-died', 'run-shell '.escapeshellarg($signal),
        ])));
    }

    private function eventChannel(): string
    {
        return 'nntmux-pane-exit-'.hash('sha256', $this->sessionName);
    }

    public function waitForExit(int $seconds): void
    {
        try {
            Process::timeout($seconds)->run(TmuxCommand::arguments(['wait-for', $this->eventChannel()]));
        } catch (ProcessTimedOutException) {
            // Periodic reconciliation also detects topology changes and hangs.
        }
    }

    /**
     * Send keys to a pane
     */
    public function sendKeys(string $target, string $keys, bool $enter = true): bool
    {
        $result = Process::timeout(5)->run(
            TmuxCommand::arguments(array_values(array_filter(
                ['send-keys', '-t', $this->target($target), '--', $keys, $enter ? 'Enter' : null],
                static fn (?string $argument): bool => $argument !== null,
            )))
        );

        return $this->recordResult($result);
    }

    /**
     * Kill a specific pane
     */
    public function killPane(string $target): bool
    {
        $result = Process::timeout(5)->run(
            TmuxCommand::arguments(['kill-pane', '-t', $this->target($target)])
        );

        return $this->recordResult($result);
    }

    /**
     * Set pane title
     */
    public function setPaneTitle(string $target, string $title): bool
    {
        $result = Process::timeout(5)->run(
            TmuxCommand::arguments(['select-pane', '-t', $this->target($target), '-T', $title])
        );

        return $this->recordResult($result);
    }

    /**
     * Get pane title
     */
    public function getPaneTitle(string $target): ?string
    {
        $result = Process::timeout(5)->run(
            TmuxCommand::arguments(['display-message', '-p', '-t', $this->target($target), '#{pane_title}'])
        );

        return $result->successful() ? trim($result->output()) : null;
    }

    /**
     * Capture pane content
     */
    public function capturePane(string $target, int $lines = 100): string
    {
        $result = Process::timeout(10)->run(
            TmuxCommand::arguments(['capture-pane', '-p', '-t', $this->target($target), '-S', "-{$lines}"])
        );

        return $result->successful() ? $result->output() : '';
    }

    public function setPaneRole(string $target, TmuxPaneRole $role): bool
    {
        $result = Process::timeout(5)->run(
            TmuxCommand::arguments(['set-option', '-p', '-t', $this->target($target), self::ROLE_OPTION, $role->value])
        );

        if ($this->recordResult($result)) {
            $this->refresh();

            return true;
        }

        return false;
    }

    public function retainPane(string $pane): bool
    {
        return $this->recordResult(Process::timeout(5)->run(TmuxCommand::arguments([
            'set-option', '-p', '-t', $pane, 'remain-on-exit', 'on',
        ])));
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

        $targets = [];
        foreach ($this->paneSnapshot() as $paneId => $state) {
            $role = $state['role'];
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
        return str_starts_with($target, '%') ? $target : $this->sessionManager->target().":{$target}";
    }

    private function tagLegacyPane(TmuxPaneRole $role, string $legacyTarget): string
    {
        $result = Process::timeout(5)->run(
            TmuxCommand::arguments(['display-message', '-p', '-t', $this->target($legacyTarget), "#{pane_id}|#{".self::ROLE_OPTION.'}'])
        );
        [$paneId, $existingRole] = array_pad(preg_split('/[|\t]/', trim($result->output()), 2), 2, '');

        if (! $result->successful() || ! preg_match('/^%[0-9]+$/', $paneId)) {
            throw new RuntimeException(
                "Tmux pane role '{$role->value}' and legacy target '{$legacyTarget}' were not found in session '{$this->sessionName}'."
            );
        }

        if ($existingRole !== '' && $existingRole !== $role->value) {
            throw new RuntimeException("Legacy tmux pane '{$legacyTarget}' already has role '{$existingRole}'.");
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
        $this->refresh();
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

        if (! $this->releasePlaceholder($paneId)) {
            return null;
        }

        return $paneId;
    }

    public function releasePlaceholder(string $pane): bool
    {
        if (! $this->retainPane($pane) || ! $this->respawnPane($pane, ['true'], kill: true)) {
            return false;
        }
        $deadline = microtime(true) + 3;
        do {
            $result = Process::timeout(5)->run(TmuxCommand::arguments([
                'display-message', '-p', '-t', $pane, '#{pane_dead}',
            ]));
            if ($result->successful() && trim($result->output()) === '1') {
                return true;
            }
            usleep(20000);
        } while (microtime(true) < $deadline);
        $this->lastError = 'Tmux placeholder did not exit.';

        return false;
    }

    private function recordResult(ProcessResult $result): bool
    {
        if ($result->successful()) {
            $this->lastError = null;

            return true;
        }

        $this->lastError = trim($result->errorOutput());
        $this->refresh();

        return false;
    }

    public function refresh(): void
    {
        $this->snapshot = null;
        $this->roleTargets = null;
    }

    /** @return array<string, array{role: string, dead: bool, exit_code: ?int, pid: int, pipe: bool, window: string}> */
    public function paneSnapshot(): array
    {
        if ($this->snapshot !== null) {
            return $this->snapshot;
        }
        $result = Process::timeout(10)->run(TmuxCommand::arguments([
            'list-panes', '-s', '-t', $this->sessionManager->target(), '-F',
            "#{pane_id}|#{".self::ROLE_OPTION."}|#{pane_dead}|#{pane_dead_status}|#{pane_pid}|#{pane_pipe}|#{window_id}",
        ]));
        if (! $result->successful()) {
            throw new RuntimeException("Unable to list panes for tmux session '{$this->sessionName}'.");
        }
        $panes = [];
        foreach (preg_split('/\R/', trim($result->output())) ?: [] as $line) {
            [$id, $role, $dead, $status, $pid, $pipe, $window] = array_pad(preg_split('/[|\t]/', $line), 7, '');
            if (! preg_match('/^%[0-9]+$/', $id)) {
                continue;
            }
            $panes[$id] = ['role' => $role, 'dead' => $dead === '1', 'exit_code' => $status === '' ? null : (int) $status, 'pid' => (int) $pid, 'pipe' => $pipe === '1', 'window' => $window];
        }

        return $this->snapshot = $panes;
    }

    public function isAlive(string $pane): bool
    {
        return isset($this->paneSnapshot()[$pane]) && ! $this->paneSnapshot()[$pane]['dead'];
    }

    public function logPane(string $pane, string $file): bool
    {
        if ($this->paneSnapshot()[$pane]['pipe'] ?? false) {
            return true;
        }

        $successful = $this->recordResult(Process::timeout(10)->run(TmuxCommand::arguments([
            'pipe-pane', '-O', '-t', $pane, 'cat >> '.escapeshellarg($file),
        ])));
        if ($successful && isset($this->snapshot[$pane])) {
            $this->snapshot[$pane]['pipe'] = true;
        }

        return $successful;
    }

    /** @param list<string> $command */
    public function respawnLoggedPane(string $pane, array $command, string $file): bool
    {
        $channel = 'nntmux-log-'.bin2hex(random_bytes(12));
        $wait = implode(' ', array_map('escapeshellarg', TmuxCommand::arguments(['wait-for', $channel])));
        $worker = implode(' ', array_map('escapeshellarg', $command));
        // pipe-pane needs a live pane in tmux 3.5. Hold this owned worker until logging is attached.
        if (! $this->respawnPane($pane, ['bash', '-c', 'timeout 10 '.$wait.' || exit 125; exec '.$worker])) {
            return false;
        }
        if (! $this->logPane($pane, $file)) {
            return false;
        }

        return $this->recordResult(Process::timeout(5)->run(TmuxCommand::arguments(['wait-for', '-S', $channel])));
    }
}
