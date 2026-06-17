<?php

declare(strict_types=1);

namespace App\Services\Gdpr;

class GdprDataInventory
{
    /**
     * Tables retained for legal, accounting, security, or accountability reasons.
     * Direct account identifiers are anonymized/minimized during erasure whenever practical.
     *
     * @return array<int, array<string, mixed>>
     */
    public function retainedRecords(): array
    {
        return [
            [
                'table' => 'payments',
                'reason' => 'Accounting, fraud prevention, dispute handling, and legal/tax obligations; direct account identifiers are anonymized while transaction details are retained.',
                'erasure_action' => 'retained_anonymized',
            ],
            [
                'table' => 'paypal_payments',
                'reason' => 'Legacy payment audit data retained for accounting and dispute handling; linked account identity resolves only to the anonymized/deleted account record.',
                'erasure_action' => 'retained_minimized',
            ],
            [
                'table' => 'user_activities',
                'reason' => 'Security and administrative audit trail.',
                'erasure_action' => 'retained_minimized',
            ],
            [
                'table' => 'gdpr_audit_logs',
                'reason' => 'GDPR accountability record for data subject requests.',
                'erasure_action' => 'retained_minimized',
            ],
            [
                'table' => 'user_role_history',
                'reason' => 'Administrative audit trail for role changes, upgrades, expirations, and account security reviews.',
                'erasure_action' => 'retained_minimized',
            ],
        ];
    }

    /**
     * Tables where records are removed when an account is erased.
     *
     * @return array<string, array<string, mixed>>
     */
    public function erasableTables(): array
    {
        return [
            'user_requests' => ['user_column' => 'users_id'],
            'user_downloads' => ['user_column' => 'users_id'],
            'dnzb_failures' => ['user_column' => 'users_id'],
            'users_releases' => ['user_column' => 'users_id'],
            'user_series' => ['user_column' => 'users_id'],
            'user_movies' => ['user_column' => 'users_id'],
            'user_excluded_categories' => ['user_column' => 'users_id'],
            'user_invitations' => ['user_column' => 'user_id'],
            'trusted_devices' => ['user_column' => 'user_id'],
            'password_securities' => ['user_column' => 'user_id'],
            'passkeys' => ['user_column' => 'authenticatable_id', 'extra' => ['authenticatable_type' => 'App\\Models\\User']],
            'model_has_permissions' => ['user_column' => 'model_id', 'extra' => ['model_type' => 'App\\Models\\User']],
            'model_has_roles' => ['user_column' => 'model_id', 'extra' => ['model_type' => 'App\\Models\\User']],
            'role_promotion_stats' => ['user_column' => 'user_id'],
        ];
    }

    /**
     * Tables where public/community records are kept but direct identifiers are minimized.
     *
     * @return array<string, array<string, mixed>>
     */
    public function anonymizedTables(): array
    {
        return [
            'release_comments' => [
                'user_column' => 'users_id',
                'updates' => [
                    'username' => 'Deleted user',
                    'host' => null,
                ],
            ],
            'release_reports' => [
                'user_column' => 'users_id',
                'updates' => [
                    'description' => '[Removed during account erasure]',
                ],
            ],
        ];
    }

    /**
     * Tables exported as part of a data access package when present.
     *
     * @return array<string, array<string, mixed>>
     */
    public function exportTables(): array
    {
        return [
            'user_requests' => ['user_column' => 'users_id', 'order_by' => 'timestamp'],
            'user_downloads' => ['user_column' => 'users_id', 'order_by' => 'timestamp'],
            'dnzb_failures' => ['user_column' => 'users_id'],
            'users_releases' => ['user_column' => 'users_id', 'order_by' => 'created_at'],
            'user_series' => ['user_column' => 'users_id'],
            'user_movies' => ['user_column' => 'users_id'],
            'user_excluded_categories' => ['user_column' => 'users_id'],
            'user_invitations' => ['user_column' => 'user_id', 'order_by' => 'created_at'],
            'release_comments' => ['user_column' => 'users_id', 'order_by' => 'created_at'],
            'release_reports' => ['user_column' => 'users_id', 'order_by' => 'created_at'],
            'invitations_sent' => ['table' => 'invitations', 'user_column' => 'invited_by', 'order_by' => 'created_at'],
            'invitations_used' => ['table' => 'invitations', 'user_column' => 'used_by', 'order_by' => 'used_at'],
            'trusted_devices' => ['user_column' => 'user_id', 'order_by' => 'created_at'],
            'role_history' => ['table' => 'user_role_history', 'user_column' => 'user_id', 'order_by' => 'created_at'],
            'promotion_stats' => ['table' => 'role_promotion_stats', 'user_column' => 'user_id', 'order_by' => 'created_at'],
            'gdpr_requests' => ['user_column' => 'user_id', 'order_by' => 'created_at'],
            'gdpr_consents' => ['user_column' => 'user_id', 'order_by' => 'created_at'],
            'gdpr_audit_logs' => ['user_column' => 'user_id', 'order_by' => 'created_at'],
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function essentialCookies(): array
    {
        return [
            ['name' => 'session', 'purpose' => 'Keeps you signed in and stores security/session state.', 'type' => 'essential'],
            ['name' => 'XSRF-TOKEN', 'purpose' => 'Protects forms and authenticated requests from cross-site request forgery.', 'type' => 'essential'],
            ['name' => '2fa_trusted_device', 'purpose' => 'Optional security cookie used only when a user chooses to trust a device for two-factor authentication.', 'type' => 'essential_security'],
            ['name' => 'theme/color local storage', 'purpose' => 'Stores display preferences for guests; authenticated preferences are stored on the account.', 'type' => 'essential_preference'],
        ];
    }
}
