<?php

declare(strict_types=1);

namespace App\Services\Gdpr;

use App\Models\GdprRequest;
use App\Models\Payment;
use App\Models\User;
use App\Models\UserActivity;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GdprExportService
{
    private const EXPORT_DISK = 'local';

    /**
     * Secrets and operational tokens are not included in data portability exports.
     *
     * @var list<string>
     */
    private const REDACTED_USER_COLUMNS = [
        'password',
        'remember_token',
        'session_token',
        'api_token',
        'resetguid',
        'verification_token',
        'sabapikey',
        'nzbgetpassword',
        'nzbvortex_api_key',
        'cp_api',
    ];

    public function __construct(
        private readonly GdprDataInventory $inventory = new GdprDataInventory,
        private readonly GdprAuditService $audit = new GdprAuditService,
        private readonly GdprNotificationService $notifications = new GdprNotificationService,
    ) {}

    /**
     * Generate and persist a JSON data export for a GDPR access/data portability request.
     *
     * @return array{disk: string, path: string, expires_at: Carbon}
     */
    public function generate(User $user, ?GdprRequest $request = null, ?User $actor = null): array
    {
        $payload = $this->buildPayload($user);
        $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $path = 'gdpr/exports/user-'.$user->id.'-'.now()->format('YmdHis').'-'.Str::random(12).'.json';

        Storage::disk(self::EXPORT_DISK)->put($path, $encoded);

        $expiresAt = now()->addDays(7);

        if ($request !== null) {
            $request->update([
                'status' => GdprRequest::STATUS_COMPLETED,
                'export_disk' => self::EXPORT_DISK,
                'export_path' => $path,
                'export_expires_at' => $expiresAt,
                'completed_at' => now(),
                'response_payload' => [
                    'format' => 'json',
                    'retained_records' => $this->inventory->retainedRecords(),
                    'expires_at' => $expiresAt->toIso8601String(),
                ],
            ]);
        }

        $this->audit->record(
            event: 'export_generated',
            description: 'GDPR data export generated.',
            subject: $user,
            actor: $actor,
            request: $request,
            metadata: ['path' => $path, 'expires_at' => $expiresAt->toIso8601String()]
        );

        if ($request !== null) {
            $this->notifications->exportReady($request->fresh() ?? $request);
        }

        return [
            'disk' => self::EXPORT_DISK,
            'path' => $path,
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildPayload(User $user): array
    {
        return [
            'generated_at' => now()->toIso8601String(),
            'format_version' => 1,
            'subject' => [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
            ],
            'account' => $this->exportAccount($user),
            'related_records' => $this->exportRelatedTables($user),
            'retained_audit_records' => $this->exportAuditRecords($user),
            'retained_payment_records' => $this->exportPaymentRecords($user),
            'retention_notice' => [
                'summary' => 'Payment and audit records may be retained after erasure where required for legal, accounting, security, dispute, or GDPR accountability purposes, with direct account identifiers anonymized or minimized where practical.',
                'records' => $this->inventory->retainedRecords(),
            ],
            'essential_cookies' => $this->inventory->essentialCookies(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function exportAccount(User $user): array
    {
        $attributes = $user->getAttributes();

        foreach (self::REDACTED_USER_COLUMNS as $column) {
            unset($attributes[$column]);
        }

        $attributes['redacted_columns'] = self::REDACTED_USER_COLUMNS;
        $attributes['roles'] = $user->roles()->pluck('name')->all();

        if (Schema::hasTable('passkeys')) {
            $attributes['passkeys'] = $this->tableRows('passkeys', 'authenticatable_id', (int) $user->id, redactedColumns: ['credential_id', 'data']);
        }

        return $attributes;
    }

    /**
     * @return array<string, mixed>
     */
    private function exportRelatedTables(User $user): array
    {
        $records = [];

        foreach ($this->inventory->exportTables() as $key => $definition) {
            $table = $definition['table'] ?? $key;
            $userColumn = $definition['user_column'];
            $orderBy = $definition['order_by'] ?? null;

            $records[$key] = $this->tableRows($table, $userColumn, (int) $user->id, $orderBy);
        }

        return $records;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function exportAuditRecords(User $user): array
    {
        if (! Schema::hasTable('user_activities')) {
            return [];
        }

        return UserActivity::query()
            ->where('user_id', $user->id)
            ->orWhere('username', $user->username)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (UserActivity $activity): array => $this->modelToArray($activity))
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function exportPaymentRecords(User $user): array
    {
        if (! Schema::hasTable('payments')) {
            return [];
        }

        return Payment::query()
            ->where('email', $user->email)
            ->orWhere('username', $user->username)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Payment $payment): array => $this->modelToArray($payment))
            ->all();
    }
    /**
     * @param  list<string>  $redactedColumns
     * @return array<int, array<string, mixed>>
     */
    private function tableRows(string $table, string $userColumn, int $userId, ?string $orderBy = null, array $redactedColumns = []): array
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $userColumn)) {
            return [];
        }

        $query = DB::table($table)->where($userColumn, $userId);

        if ($orderBy !== null && Schema::hasColumn($table, $orderBy)) {
            $query->orderByDesc($orderBy);
        }

        return $query->get()
            ->map(fn (object $row): array => $this->rowToArray($row, $redactedColumns))
            ->all();
    }

    /**
     * @param  list<string>  $redactedColumns
     * @return array<string, mixed>
     */
    private function rowToArray(object $row, array $redactedColumns = []): array
    {
        $data = json_decode(json_encode($row, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);

        foreach ($redactedColumns as $column) {
            if (array_key_exists($column, $data)) {
                $data[$column] = '[redacted]';
            }
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function modelToArray(object $model): array
    {
        return json_decode(json_encode($model, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
    }
}
