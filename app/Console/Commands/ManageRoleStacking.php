<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\UserRoleHistory;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

class ManageRoleStacking extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'role:manage
                          {action : Action to perform (list-pending, cancel-pending, history, activate-pending)}
                          {user? : User ID or username}
                          {--all : Apply to all users}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage role stacking for users';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $action = $this->argument('action');

        return match ($action) {
            'list-pending' => $this->listPendingRoles(),
            'cancel-pending' => $this->cancelPendingRole(),
            'history' => $this->showHistory(),
            'activate-pending' => $this->activatePendingRoles(),
            default => $this->handleUnknownAction($action),
        };
    }

    /**
     * Handle unknown action
     */
    protected function handleUnknownAction(string $action): int
    {
        $this->error("Unknown action: {$action}");

        return 1;
    }

    /**
     * List all users with pending roles
     */
    protected function listPendingRoles(): int
    {
        $users = User::whereNotNull('pending_roles_id')
            ->whereNotNull('pending_role_start_date')
            ->get();

        if ($users->isEmpty()) {
            $this->info('No users with pending roles found.');

            return 0;
        }

        $this->info('Users with Pending Roles:');
        $this->newLine();

        $headers = ['User ID', 'Username', 'Current Role', 'Pending Role', 'Activation Date', 'Time Until'];
        $rows = [];

        foreach ($users as $user) {
            /** @var Role|null $currentRole */
            $currentRole = $user->roles->first();
            $pendingRole = $user->getPendingRole();
            $activationDate = Carbon::parse($user->pending_role_start_date);

            $rows[] = [
                $user->id,
                $user->username,
                $currentRole ? $currentRole->name : 'None',
                $pendingRole ? $pendingRole->name : 'Unknown',
                $activationDate->format('Y-m-d H:i'),
                $activationDate->diffForHumans(),
            ];
        }

        $this->table($headers, $rows);
        $this->info(sprintf('Total: %d users', count($rows)));

        return 0;
    }

    /**
     * Cancel pending role for user(s)
     */
    protected function cancelPendingRole(): int
    {
        if ($this->option('all')) {
            if (! $this->confirm('Are you sure you want to cancel ALL pending roles?')) {
                $this->info('Operation cancelled.');

                return 0;
            }

            $users = User::whereNotNull('pending_roles_id')->get();
            $count = 0;

            foreach ($users as $user) {
                if ($user->cancelPendingRole()) {
                    $count++;
                    $this->info("Cancelled pending role for user: {$user->username}");
                }
            }

            $this->info("Cancelled pending roles for {$count} users.");

            return 0;
        }

        $userId = $this->argument('user');
        if (! $userId) {
            $this->error('User ID or username required. Use --all to cancel for all users.');

            return 1;
        }

        $user = $this->findUser($userId);
        if (! $user) {
            return 1;
        }

        if (! $user->hasPendingRole()) {
            $this->info("User {$user->username} has no pending role.");

            return 0;
        }

        $pendingRole = $user->getPendingRole();
        if (! $this->confirm("Cancel pending role '{$pendingRole->name}' for user '{$user->username}'?")) {
            $this->info('Operation cancelled.');

            return 0;
        }

        if ($user->cancelPendingRole()) {
            $this->info("Successfully cancelled pending role for user: {$user->username}");

            return 0;
        }

        $this->error('Failed to cancel pending role.');

        return 1;
    }

    /**
     * Show role change history for user(s)
     */
    protected function showHistory(): int
    {
        $userId = $this->argument('user');
        if (! $userId) {
            $this->error('User ID or username required.');

            return 1;
        }

        $user = $this->findUser($userId);
        if (! $user) {
            return 1;
        }

        $history = UserRoleHistory::getUserHistory($user->id);

        if ($history->isEmpty()) {
            $this->info("No role change history found for user: {$user->username}");

            return 0;
        }

        $this->info("Role Change History for: {$user->username} (ID: {$user->id})");
        $this->newLine();

        $headers = ['Date', 'From Role', 'To Role', 'Type', 'Reason', 'Changed By'];
        $rows = [];

        foreach ($history as $change) {
            $oldRole = $change->old_role_id ? Role::find($change->old_role_id) : null;
            $newRole = Role::find($change->new_role_id);
            $changedBy = $change->changed_by ? User::find($change->changed_by) : null;

            $rows[] = [
                $change->effective_date->format('Y-m-d H:i'),
                $oldRole ? $oldRole->name : 'None',
                $newRole ? $newRole->name : 'Unknown',
                $change->is_stacked ? 'Stacked' : 'Immediate',
                $change->change_reason ?? 'N/A',
                $changedBy ? $changedBy->username : 'System',
            ];
        }

        $this->table($headers, $rows);
        $this->info(sprintf('Total changes: %d', count($rows)));

        // Show stacked changes summary
        $stackedCount = $history->where('is_stacked', true)->count();
        if ($stackedCount > 0) {
            $this->newLine();
            $this->info("Stacked role changes: {$stackedCount}");
        }

        return 0;
    }

    /**
     * Manually activate pending roles (normally done automatically)
     */
    protected function activatePendingRoles(): int
    {
        $this->warn('This command manually activates pending roles that are scheduled for activation.');
        $this->warn('Normally, this is done automatically by the scheduled task.');
        $this->newLine();

        if (! $this->confirm('Do you want to continue?')) {
            $this->info('Operation cancelled.');

            return 0;
        }

        $now = Carbon::now();
        $users = User::whereNotNull('pending_roles_id')
            ->whereNotNull('pending_role_start_date')
            ->where('pending_role_start_date', '<=', $now)
            ->get();

        if ($users->isEmpty()) {
            $this->info('No pending roles ready for activation.');

            return 0;
        }

        $this->info(sprintf('Found %d pending roles ready for activation:', $users->count()));
        $this->newLine();

        $activated = 0;
        $failed = 0;

        foreach ($users as $user) {
            $pendingRole = $user->getPendingRole();
            /** @var Role|null $oldRole */
            $oldRole = $user->roles->first();

            try {
                // Activate the pending role
                $user->update([
                    'roles_id' => $user->pending_roles_id,
                    'pending_roles_id' => null,
                    'pending_role_start_date' => null,
                ]);

                if ($pendingRole) {
                    $user->syncRoles([$pendingRole->name]);
                }

                // Record in history
                UserRoleHistory::recordRoleChange(
                    userId: $user->id,
                    oldRoleId: $oldRole ? $oldRole->id : null,
                    newRoleId: $pendingRole ? $pendingRole->id : $user->roles_id,
                    oldExpiryDate: null,
                    newExpiryDate: null,
                    effectiveDate: $now,
                    isStacked: true,
                    changeReason: 'manual_activation',
                    changedBy: null
                );

                $this->info(sprintf(
                    '✓ Activated %s -> %s for user: %s',
                    $oldRole ? $oldRole->name : 'None',
                    $pendingRole ? $pendingRole->name : 'Unknown',
                    $user->username
                ));

                $activated++;
            } catch (\Exception $e) {
                $this->error(sprintf(
                    '✗ Failed to activate role for user %s: %s',
                    $user->username,
                    $e->getMessage()
                ));
                $failed++;
            }
        }

        $this->newLine();
        $this->info('Activation complete:');
        $this->info("  Successful: {$activated}");
        if ($failed > 0) {
            $this->error("  Failed: {$failed}");
        }

        return $failed > 0 ? 1 : 0;
    }

    /**
     * Find user by ID or username
     */
    protected function findUser(string|int $identifier): ?User
    {
        $user = is_numeric($identifier)
            ? User::find($identifier)
            : User::where('username', $identifier)->first();

        if (! $user) {
            $this->error("User not found: {$identifier}");

            return null;
        }

        return $user;
    }
}
