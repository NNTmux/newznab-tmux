<?php

declare(strict_types=1);

namespace App\Services\Gdpr;

use App\Models\GdprRequest;
use App\Models\User;
use App\Services\AdminDashboardSnapshotService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class GdprErasureService
{
    public function __construct(
        private readonly GdprDataInventory $inventory = new GdprDataInventory,
        private readonly GdprAuditService $audit = new GdprAuditService,
        private readonly GdprNotificationService $notifications = new GdprNotificationService,
    ) {}

    public function eraseForAccountDeletion(User $user, ?User $actor = null, ?GdprRequest $request = null, bool $forceDelete = false): void
    {
        DB::transaction(function () use ($user, $actor, $request, $forceDelete): void {
            $freshUser = User::withTrashed()->findOrFail($user->id);
            $original = [
                'id' => (int) $freshUser->id,
                'username' => (string) $freshUser->username,
                'email' => (string) $freshUser->email,
            ];
            $anonymous = $this->anonymousIdentity((int) $freshUser->id);

            $this->deleteErasableRecords((int) $freshUser->id);
            $this->anonymizeCommunityRecords((int) $freshUser->id, $anonymous['username']);
            $this->minimizeInvitations((int) $freshUser->id, $original['email'], $anonymous['email']);
            $this->anonymizeRetainedPaymentRecords($original, $anonymous);
            $this->anonymizeRetainedAuditRecords($original, $anonymous);
            $this->anonymizeUser($freshUser, $anonymous);

            $this->audit->record(
                event: $forceDelete ? 'erasure_force_delete' : 'erasure_soft_delete',
                description: 'GDPR erasure workflow completed; retained payment and audit records were anonymized/minimized.',
                subject: $freshUser,
                actor: $actor,
                request: $request,
                metadata: [
                    'retained_records' => $this->inventory->retainedRecords(),
                    'force_delete' => $forceDelete,
                ]
            );

            if ($forceDelete) {
                $freshUser->forceDelete();
            } elseif (! $freshUser->trashed()) {
                $freshUser->delete();
            }

            if ($request !== null) {
                $request->update([
                    'status' => GdprRequest::STATUS_COMPLETED,
                    'completed_at' => now(),
                    'processed_by' => $actor?->id,
                    'response_payload' => [
                        'erasure' => 'completed',
                        'force_delete' => $forceDelete,
                        'retained_records' => $this->inventory->retainedRecords(),
                    ],
                ]);
            }
        }, 3);

        if ($request !== null) {
            $this->notifications->erasureCompleted($request->fresh() ?? $request);
        }

        Cache::forget(AdminDashboardSnapshotService::CACHE_KEY);
    }

    public function forceDeleteWithErasure(User $user, ?User $actor = null, ?GdprRequest $request = null): void
    {
        $this->eraseForAccountDeletion($user, $actor, $request, true);
    }

    /**
     * @return array{username: string, email: string, name: string}
     */
    private function anonymousIdentity(int $userId): array
    {
        return [
            'username' => 'deleted_user_'.$userId,
            'email' => 'deleted-user-'.$userId.'@deleted.invalid',
            'name' => 'Deleted user '.$userId,
        ];
    }

    /**
     * @param  array{username: string, email: string, name: string}  $anonymous
     */
    private function anonymizeUser(User $user, array $anonymous): void
    {
        $updates = [
            'username' => $anonymous['username'],
            'email' => $anonymous['email'],
            'password' => Hash::make(Str::random(64)),
            'remember_token' => null,
            'session_token' => null,
            'api_token' => hash('sha256', 'deleted-'.$user->id.'-'.Str::random(32)),
            'resetguid' => null,
            'verification_token' => null,
            'firstname' => null,
            'lastname' => null,
            'name' => $anonymous['name'],
            'host' => '',
            'notes' => null,
            'saburl' => null,
            'sabapikey' => null,
            'nzbgeturl' => null,
            'nzbgetusername' => null,
            'nzbgetpassword' => null,
            'nzbvortex_api_key' => null,
            'nzbvortex_server_url' => null,
            'cp_url' => null,
            'cp_api' => null,
            'email_verified_at' => null,
            'verified' => false,
            'can_post' => false,
            'invites' => 0,
        ];

        $updates = $this->filterColumns('users', $updates);

        User::withoutEvents(function () use ($user, $updates): void {
            User::withTrashed()->where('id', $user->id)->update($updates);
        });

        $user->forceFill($updates);
    }

    private function deleteErasableRecords(int $userId): void
    {
        foreach ($this->inventory->erasableTables() as $table => $definition) {
            $userColumn = $definition['user_column'];
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $userColumn)) {
                continue;
            }

            $query = DB::table($table)->where($userColumn, $userId);

            foreach (($definition['extra'] ?? []) as $column => $value) {
                if (Schema::hasColumn($table, (string) $column)) {
                    $query->where((string) $column, $value);
                }
            }

            $query->delete();
        }
    }

    private function anonymizeCommunityRecords(int $userId, string $anonymousUsername): void
    {
        foreach ($this->inventory->anonymizedTables() as $table => $definition) {
            $userColumn = $definition['user_column'];
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $userColumn)) {
                continue;
            }

            $updates = $this->filterColumns($table, $definition['updates'] ?? []);
            if ($updates === []) {
                continue;
            }

            if (Schema::hasColumn($table, 'username')) {
                $updates['username'] = $anonymousUsername;
            }

            DB::table($table)->where($userColumn, $userId)->update($updates);
        }
    }

    /**
     * @param  array{id: int, username: string, email: string}  $original
     * @param  array{username: string, email: string, name: string}  $anonymous
     */
    private function anonymizeRetainedPaymentRecords(array $original, array $anonymous): void
    {
        if (! Schema::hasTable('payments')) {
            return;
        }

        $updates = $this->filterColumns('payments', [
            'email' => $anonymous['email'],
            'username' => $anonymous['username'],
        ]);

        if ($updates === []) {
            return;
        }

        DB::table('payments')
            ->where(function ($query) use ($original): void {
                if (Schema::hasColumn('payments', 'email')) {
                    $query->orWhere('email', $original['email']);
                }

                if (Schema::hasColumn('payments', 'username')) {
                    $query->orWhere('username', $original['username']);
                }
            })
            ->update($updates);
    }

    private function minimizeInvitations(int $userId, string $originalEmail, string $anonymousEmail): void
    {
        if (! Schema::hasTable('invitations')) {
            return;
        }

        if (Schema::hasColumn('invitations', 'email')) {
            DB::table('invitations')
                ->where('email', $originalEmail)
                ->update(['email' => $anonymousEmail]);
        }

        if (Schema::hasColumn('invitations', 'used_by')) {
            DB::table('invitations')
                ->where('used_by', $userId)
                ->update(['used_by' => null]);
        }

        if (Schema::hasColumn('invitations', 'invited_by') && Schema::hasColumn('invitations', 'used_at')) {
            DB::table('invitations')
                ->where('invited_by', $userId)
                ->whereNull('used_at')
                ->delete();
        }
    }

    /**
     * @param  array{id: int, username: string, email: string}  $original
     * @param  array{username: string, email: string, name: string}  $anonymous
     */
    private function anonymizeRetainedAuditRecords(array $original, array $anonymous): void
    {
        if (Schema::hasTable('user_activities')) {
            DB::table('user_activities')
                ->where(function ($query) use ($original): void {
                    $query->where('user_id', $original['id'])
                        ->orWhere('username', $original['username']);
                })
                ->orderBy('id')
                ->chunkById(100, function ($activities) use ($anonymous): void {
                    foreach ($activities as $activity) {
                        $metadata = $this->sanitizeMetadata($activity->metadata ?? null);
                        $updates = ['username' => $anonymous['username']];
                        if (Schema::hasColumn('user_activities', 'metadata')) {
                            $updates['metadata'] = json_encode($metadata, JSON_UNESCAPED_SLASHES);
                        }
                        DB::table('user_activities')->where('id', $activity->id)->update($updates);
                    }
                });
        }

        if (Schema::hasTable('gdpr_audit_logs') && Schema::hasColumn('gdpr_audit_logs', 'metadata')) {
            DB::table('gdpr_audit_logs')
                ->where('user_id', $original['id'])
                ->update(['metadata' => json_encode(['subject' => $anonymous['username']], JSON_UNESCAPED_SLASHES)]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function sanitizeMetadata(mixed $metadata): array
    {
        if (is_string($metadata)) {
            $decoded = json_decode($metadata, true);
            $metadata = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($metadata)) {
            $metadata = [];
        }

        unset($metadata['email'], $metadata['ip_address'], $metadata['host']);
        $metadata['gdpr_minimized'] = true;

        return $metadata;
    }

    /**
     * @param  array<string, mixed>  $updates
     * @return array<string, mixed>
     */
    private function filterColumns(string $table, array $updates): array
    {
        if (! Schema::hasTable($table)) {
            return [];
        }

        return array_filter(
            $updates,
            static fn (string $column): bool => Schema::hasColumn($table, $column),
            ARRAY_FILTER_USE_KEY
        );
    }
}
