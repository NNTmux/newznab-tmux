<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\QueueType;
use App\Enums\SignupError;
use App\Enums\UserRole;
use App\Jobs\SendAccountExpiredEmail;
use App\Jobs\SendAccountWillExpireEmail;
use App\Rules\ValidEmailDomain;
use App\Services\InvitationService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Jrean\UserVerification\Traits\UserVerification;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $username
 * @property string|null $firstname
 * @property string|null $lastname
 * @property string $email
 * @property string $password
 * @property int $roles_id
 * @property string|null $host
 * @property int $grabs
 * @property string $api_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $resetguid
 * @property Carbon|null $lastlogin
 * @property Carbon|null $apiaccess
 * @property int $invites
 * @property int|null $invitedby
 * @property bool $movieview
 * @property bool $xxxview
 * @property bool $musicview
 * @property bool $consoleview
 * @property bool $bookview
 * @property bool $gameview
 * @property string|null $saburl
 * @property string|null $sabapikey
 * @property bool|null $sabapikeytype
 * @property bool|null $sabpriority
 * @property string|null $nzbgeturl
 * @property string|null $nzbgetusername
 * @property string|null $nzbgetpassword
 * @property string|null $nzbvortex_api_key
 * @property string|null $nzbvortex_server_url
 * @property string $notes
 * @property string|null $cp_url
 * @property string|null $cp_api
 * @property string|null $style
 * @property Carbon|null $rolechangedate
 * @property Carbon|null $pending_role_start_date
 * @property int|null $pending_roles_id
 * @property string|null $remember_token
 * @property int $rate_limit
 * @property Carbon|null $email_verified_at
 * @property bool $verified
 * @property string|null $verification_token
 * @property string|null $timezone
 * @property bool $can_post
 * @property array<int>|string|null $categoryexclusions Computed from join/subquery for category exclusion lists
 * @property string|null $sort_date Computed column for date sorting
 * @property string|null $role_name Computed from join with roles table
 * @property int|null $num Computed count value
 * @property string|null $mth Computed month value
 * @property bool|null $is_role_expired Computed via Attribute accessor
 * @property int|null $days_until_expiry Computed via Attribute accessor
 * @property int|null $count Computed count from aggregate queries
 * @property int|null $daily_api_count Computed daily API request count
 * @property int|null $daily_download_count Computed daily download count
 * @property-read Collection<int, ReleaseComment> $comments
 * @property-read Collection<int, UserDownload> $downloads
 * @property-read Collection<int, DnzbFailure> $failedReleases
 * @property-read Collection<int, Invitation> $invitations
 * @property-read Collection<int, UsersRelease> $releases
 * @property-read Collection<int, UserRequest> $requests
 * @property-read Collection<int, UserSerie> $series
 * @property-read Collection<int, RolePromotionStat> $promotionStats
 * @property-read Collection<int, UserRoleHistory> $roleHistory
 * @property-read Role|null $role
 * @property-read PasswordSecurity|null $passwordSecurity
 *
 * @method static Builder|User whereUsername(string $value)
 * @method static Builder|User whereEmail(string $value)
 * @method static Builder|User whereApiToken(string $value)
 * @method static Builder|User whereResetguid(string $value)
 * @method static Builder|User whereVerified(int $value)
 * @method static Builder|User active()
 * @method static Builder|User verified()
 * @method static Builder|User withRole(int|string $role)
 * @method static Builder|User expiringSoon(int $days = 7)
 * @method static Builder|User expired()
 */
final class User extends Authenticatable
{
    use HasFactory; // @phpstan-ignore missingType.generics
    use HasRoles;
    use Notifiable;
    use SoftDeletes;
    use UserVerification;

    /**
     * @var list<string>
     */
    protected $hidden = [
        'remember_token',
        'password',
    ];

    /**
     * @var list<string>
     */
    protected $guarded = [];

    /**
     * Roles excluded from promotions.
     *
     * @var list<string>
     */
    private const PROMOTION_EXCLUDED_ROLES = [
        'User',
        'Admin',
        'Moderator',
        'Disabled',
        'Friend',
    ];

    /**
     * Days in a year for subscription calculations.
     */
    private const DAYS_PER_YEAR = 365;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'lastlogin' => 'datetime',
            'apiaccess' => 'datetime',
            'rolechangedate' => 'datetime',
            'pending_role_start_date' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'password' => 'hashed',
            'movieview' => 'boolean',
            'xxxview' => 'boolean',
            'musicview' => 'boolean',
            'consoleview' => 'boolean',
            'bookview' => 'boolean',
            'gameview' => 'boolean',
            'sabapikeytype' => 'boolean',
            'sabpriority' => 'boolean',
            'verified' => 'boolean',
            'can_post' => 'boolean',
            'grabs' => 'integer',
            'invites' => 'integer',
            'rate_limit' => 'integer',
            'roles_id' => 'integer',
            'pending_roles_id' => 'integer',
            'invitedby' => 'integer',
        ];
    }

    protected function getDefaultGuardName(): string
    {
        return 'web';
    }

    // ===== Relationships =====

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\Spatie\Permission\Models\Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'roles_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\UserRequest, $this>
     */
    public function requests(): HasMany
    {
        return $this->hasMany(UserRequest::class, 'users_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\UserDownload, $this>
     */
    public function downloads(): HasMany
    {
        return $this->hasMany(UserDownload::class, 'users_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\UsersRelease, $this>
     */
    public function releases(): HasMany
    {
        return $this->hasMany(UsersRelease::class, 'users_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\UserSerie, $this>
     */
    public function series(): HasMany
    {
        return $this->hasMany(UserSerie::class, 'users_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\Invitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class, 'invited_by');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\DnzbFailure, $this>
     */
    public function failedReleases(): HasMany
    {
        return $this->hasMany(DnzbFailure::class, 'users_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\ReleaseComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(ReleaseComment::class, 'users_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\RolePromotionStat, $this>
     */
    public function promotionStats(): HasMany
    {
        return $this->hasMany(RolePromotionStat::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\UserRoleHistory, $this>
     */
    public function roleHistory(): HasMany
    {
        return $this->hasMany(UserRoleHistory::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne<\App\Models\PasswordSecurity, $this>
     */
    public function passwordSecurity(): HasOne
    {
        return $this->hasOne(PasswordSecurity::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\UserExcludedCategory, $this>
     */
    public function excludedCategories(): HasMany
    {
        return $this->hasMany(UserExcludedCategory::class, 'users_id');
    }

    // ===== Backward Compatibility Aliases =====

    public function request(): HasMany // @phpstan-ignore missingType.generics
    {
        return $this->requests();
    }

    public function download(): HasMany // @phpstan-ignore missingType.generics
    {
        return $this->downloads();
    }

    public function release(): HasMany // @phpstan-ignore missingType.generics
    {
        return $this->releases();
    }

    public function invitation(): HasMany // @phpstan-ignore missingType.generics
    {
        return $this->invitations();
    }

    public function failedRelease(): HasMany // @phpstan-ignore missingType.generics
    {
        return $this->failedReleases();
    }

    public function comment(): HasMany // @phpstan-ignore missingType.generics
    {
        return $this->comments();
    }

    // ===== Query Scopes =====

    /**
     * Scope to get active (non-disabled) users.
     */
    public function scopeActive(Builder $query): Builder // @phpstan-ignore missingType.generics
    {
        return $query->where('roles_id', '!=', UserRole::DISABLED->value);
    }

    /**
     * Scope to get verified users.
     */
    public function scopeVerified(Builder $query): Builder // @phpstan-ignore missingType.generics
    {
        return $query->where('verified', true);
    }

    /**
     * Scope to filter users by role.
     */
    public function scopeWithRole(Builder $query, int|string $role): Builder // @phpstan-ignore missingType.generics
    {
        if (is_numeric($role)) {
            return $query->where('roles_id', (int) $role);
        }

        return $query->whereHas('role', fn (Builder $q) => $q->where('name', $role));
    }

    /**
     * Scope to get users with roles expiring within specified days.
     */
    public function scopeExpiringSoon(Builder $query, int $days = 7): Builder // @phpstan-ignore missingType.generics
    {
        return $query->whereNotNull('rolechangedate')
            ->whereBetween('rolechangedate', [now(), now()->addDays($days)]);
    }

    /**
     * Scope to get users with expired roles.
     */
    public function scopeExpired(Builder $query): Builder // @phpstan-ignore missingType.generics
    {
        return $query->whereNotNull('rolechangedate')
            ->where('rolechangedate', '<', now());
    }

    /**
     * Scope to exclude sharing email.
     */
    public function scopeExcludeSharing(Builder $query): Builder // @phpstan-ignore missingType.generics
    {
        return $query->where('email', '!=', 'sharing@nZEDb.com');
    }

    // ===== Attribute Accessors =====

    /**
     * Get the user's full name.
     */
    protected function fullName(): Attribute // @phpstan-ignore missingType.generics
    {
        return Attribute::make(
            get: fn (): string => trim("{$this->firstname} {$this->lastname}") ?: $this->username,
        );
    }

    /**
     * Get the user's timezone or default.
     */
    protected function effectiveTimezone(): Attribute // @phpstan-ignore missingType.generics
    {
        return Attribute::make(
            get: fn (): string => $this->timezone ?? 'UTC',
        );
    }

    /**
     * Check if user has admin privileges.
     */
    protected function isAdmin(): Attribute // @phpstan-ignore missingType.generics
    {
        return Attribute::make(
            get: fn (): bool => $this->roles_id === UserRole::ADMIN->value,
        );
    }

    /**
     * Check if user is disabled.
     */
    protected function isDisabled(): Attribute // @phpstan-ignore missingType.generics
    {
        return Attribute::make(
            get: fn (): bool => $this->roles_id === UserRole::DISABLED->value,
        );
    }

    /**
     * Check if role is expired.
     */
    protected function isRoleExpired(): Attribute // @phpstan-ignore missingType.generics
    {
        return Attribute::make(
            get: fn (): bool => $this->rolechangedate?->isPast() ?? false,
        );
    }

    /**
     * Get days until role expires.
     */
    protected function daysUntilExpiry(): Attribute // @phpstan-ignore missingType.generics
    {
        return Attribute::make(
            get: fn (): ?int => $this->rolechangedate
                ? (int) now()->diffInDays($this->rolechangedate, false)
                : null,
        );
    }

    /**
     * Get country code from IP address lookup.
     * Uses ip-api.com with caching to minimize API calls.
     */
    protected function countryCode(): Attribute // @phpstan-ignore missingType.generics
    {
        return Attribute::make(
            get: fn (): ?string => $this->getCountryFromIp()['countryCode'] ?? null,
        );
    }

    /**
     * Get country name from IP address lookup.
     * Uses ip-api.com with caching to minimize API calls.
     */
    protected function countryName(): Attribute // @phpstan-ignore missingType.generics
    {
        return Attribute::make(
            get: fn (): ?string => $this->getCountryFromIp()['country'] ?? null,
        );
    }

    /**
     * Get country flag emoji from country code.
     * Converts 2-letter country code to Unicode regional indicator symbols.
     */
    protected function countryFlag(): Attribute // @phpstan-ignore missingType.generics
    {
        return Attribute::make(
            get: function (): ?string {
                $countryCode = $this->getCountryFromIp()['countryCode'] ?? null;

                if (empty($countryCode) || strlen($countryCode) !== 2) {
                    return null;
                }

                // Convert country code to regional indicator symbols (flag emoji)
                // Each letter is converted to its regional indicator equivalent
                // A = 🇦 (U+1F1E6), B = 🇧 (U+1F1E7), etc.
                $countryCode = strtoupper($countryCode);
                $flag = '';

                for ($i = 0; $i < 2; $i++) {
                    $char = ord($countryCode[$i]) - ord('A') + 0x1F1E6;
                    $flag .= mb_chr($char, 'UTF-8');
                }

                return $flag;
            },
        );
    }

    /**
     * Lookup country information from IP address using ip-api.com.
     *
     * @return array{country: string|null, countryCode: string|null}
     */
    protected function getCountryFromIp(): array
    {
        $ip = $this->host;

        if (empty($ip)) {
            return ['country' => null, 'countryCode' => null];
        }

        // Skip private/local IPs
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return ['country' => null, 'countryCode' => null];
        }

        $cacheKey = 'ip_country_lookup_'.md5($ip);

        return Cache::remember($cacheKey, 86400, function () use ($ip) {
            return $this->fetchCountryFromApi($ip);
        });
    }

    /**
     * Fetch country info from ip-api.com.
     *
     * @return array{country: string|null, countryCode: string|null}
     */
    protected function fetchCountryFromApi(string $ip): array
    {
        try {
            $response = Http::timeout(3)
                ->retry(2, 100)
                ->get("http://ip-api.com/json/{$ip}", [
                    'fields' => 'status,country,countryCode',
                ]);

            if (! $response->successful()) {
                Log::debug('Country lookup failed', ['ip' => $ip, 'status' => $response->status()]);

                return ['country' => null, 'countryCode' => null];
            }

            $data = $response->json();

            if (($data['status'] ?? '') !== 'success') {
                return ['country' => null, 'countryCode' => null];
            }

            return [
                'country' => $data['country'] ?? null,
                'countryCode' => $data['countryCode'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::debug('Country lookup exception', ['ip' => $ip, 'error' => $e->getMessage()]);

            return ['country' => null, 'countryCode' => null];
        }
    }

    // ===== Pending Role Methods =====

    /**
     * Check if user has a pending stacked role.
     */
    public function hasPendingRole(): bool
    {
        return $this->pending_roles_id !== null && $this->pending_role_start_date !== null;
    }

    /**
     * Get the pending role.
     */
    public function getPendingRole(): ?Role
    {
        if (! $this->hasPendingRole()) {
            return null;
        }

        return Role::find($this->pending_roles_id);
    }

    /**
     * Cancel a pending stacked role.
     */
    public function cancelPendingRole(): bool
    {
        if (! $this->hasPendingRole()) {
            return false;
        }

        return $this->update([
            'pending_roles_id' => null,
            'pending_role_start_date' => null,
        ]);
    }

    /**
     * Get all pending stacked role changes for this user.
     * Returns an array of stacked role changes that haven't been activated yet (effective_date is in the future).
     *
     * @return \Illuminate\Support\Collection<int, array{
     *     role: Role|null,
     *     role_name: string,
     *     start_date: string,
     *     end_date: string|null,
     *     is_current_pending: bool
     * }>
     */
    public function getAllPendingStackedRoles(): \Illuminate\Support\Collection
    {
        // Get all stacked role changes from history where effective_date is in the future
        $stackedHistory = UserRoleHistory::where('user_id', $this->id)
            ->where('is_stacked', true)
            ->where('effective_date', '>', now())
            ->orderBy('effective_date', 'asc')
            ->get();

        return $stackedHistory->map(function ($history) { // @phpstan-ignore return.type
            $role = Role::find($history->new_role_id);

            return [
                'role' => $role,
                'role_name' => $role?->name ?? 'Unknown Role', // @phpstan-ignore nullsafe.neverNull
                'start_date' => $history->effective_date,
                'end_date' => $history->new_expiry_date,
                'is_current_pending' => $this->pending_roles_id === $history->new_role_id
                    && $this->pending_role_start_date?->equalTo($history->effective_date),
            ];
        });
    }

    /**
     * Get comprehensive role expiry information.
     *
     * @return array{
     *     current_role: Role|null,
     *     current_expiry: Carbon|null,
     *     has_expiry: bool,
     *     is_expired: bool,
     *     days_until_expiry: int|null,
     *     pending_role: Role|null,
     *     pending_start: Carbon|null,
     *     has_pending_role: bool
     * }
     */
    public function getRoleExpiryInfo(): array
    {
        return [
            'current_role' => $this->role,
            'current_expiry' => $this->rolechangedate,
            'has_expiry' => $this->rolechangedate !== null,
            'is_expired' => $this->is_role_expired,
            'days_until_expiry' => $this->days_until_expiry,
            'pending_role' => $this->getPendingRole(),
            'pending_start' => $this->pending_role_start_date,
            'has_pending_role' => $this->hasPendingRole(),
        ];
    }

    // ===== Static Query Methods =====

    /**
     * Get count of users matching filters.
     */
    public static function getCount(
        ?string $role = null,
        ?string $username = '',
        ?string $host = '',
        ?string $email = '',
        ?string $createdFrom = '',
        ?string $createdTo = '',
    ): int {
        return self::query()
            ->withTrashed()
            ->excludeSharing()
            ->when($role, fn (Builder $q) => $q->where('roles_id', $role))
            ->when($username, fn (Builder $q) => $q->where('username', 'like', "%{$username}%"))
            ->when($host, fn (Builder $q) => $q->where('host', 'like', "%{$host}%"))
            ->when($email, fn (Builder $q) => $q->where('email', 'like', "%{$email}%"))
            ->when($createdFrom, fn (Builder $q) => $q->where('created_at', '>=', "{$createdFrom} 00:00:00"))
            ->when($createdTo, fn (Builder $q) => $q->where('created_at', '<=', "{$createdTo} 23:59:59"))
            ->count();
    }

    /**
     * Find user by username.
     */
    public static function findByUsername(string $username): ?static
    {
        return self::whereUsername($username)->first();
    }

    /**
     * Find user by email.
     */
    public static function findByEmail(string $email): ?static
    {
        return static::whereEmail($email)->first();
    }

    /**
     * Find user by RSS token.
     */
    public static function findByRssToken(string $token): ?static
    {
        return static::whereApiToken($token)->first();
    }

    /**
     * Find user by password reset GUID.
     */
    public static function findByResetGuid(string $guid): ?static
    {
        return static::whereResetguid($guid)->first();
    }

    // ===== Backward Compatibility Aliases =====

    /**
     * @deprecated Use findByUsername() instead
     */
    public static function getByUsername(string $userName): ?static
    {
        return static::findByUsername($userName);
    }

    /**
     * @deprecated Use findByEmail() instead
     */
    public static function getByEmail(string $email): ?static
    {
        return static::findByEmail($email);
    }

    /**
     * @deprecated Use findByRssToken() instead
     */
    public static function getByRssToken(string $rssToken): ?static
    {
        return static::findByRssToken($rssToken);
    }

    /**
     * @deprecated Use findByResetGuid() instead
     */
    public static function getByPassResetGuid(string $guid): ?static
    {
        return static::findByResetGuid($guid);
    }

    // ===== User Management Methods =====

    /**
     * Delete user by ID.
     *
     * @throws \Exception
     */
    public static function deleteUser(int $id): void
    {
        static::findOrFail($id)->delete();
    }

    /**
     * Update user details.
     */
    public static function updateUser(
        int $id,
        string $userName,
        ?string $email,
        int $grabs,
        int $role,
        ?string $notes,
        int $invites,
        int $movieview,
        int $musicview,
        int $gameview,
        int $xxxview,
        int $consoleview,
        int $bookview,
        string $style = 'None',
    ): int {
        $user = static::findOrFail($id);
        $roleModel = Role::find($role);

        $user->update([
            'username' => trim($userName),
            'grabs' => $grabs,
            'roles_id' => $role,
            'notes' => substr($notes ?? '', 0, 255),
            'invites' => $invites,
            'movieview' => $movieview,
            'musicview' => $musicview,
            'gameview' => $gameview,
            'xxxview' => $xxxview,
            'consoleview' => $consoleview,
            'bookview' => $bookview,
            'style' => $style,
            'rate_limit' => $roleModel?->rate_limit ?? 60, // @phpstan-ignore nullsafe.neverNull
            ...($email ? ['email' => trim($email)] : []),
        ]);

        if ($roleModel) {
            $user->syncRoles([$roleModel->name]);
        }

        return SignupError::SUCCESS->value;
    }

    /**
     * Update user role with optional stacking and promotions.
     */
    public static function updateUserRole(
        int $uid,
        int|string $role,
        bool $applyPromotions = true,
        bool $stackRole = true,
        ?int $changedBy = null,
        ?string $originalExpiryBeforeEdits = null,
        bool $preserveCurrentExpiry = false,
        ?int $addYears = null,
    ): bool {
        Log::info('updateUserRole called', [
            'uid' => $uid,
            'role' => $role,
            'applyPromotions' => $applyPromotions,
            'stackRole' => $stackRole,
            'changedBy' => $changedBy,
            'originalExpiryBeforeEdits' => $originalExpiryBeforeEdits,
            'preserveCurrentExpiry' => $preserveCurrentExpiry,
            'addYears' => $addYears,
        ]);

        $roleModel = is_numeric($role)
            ? Role::find((int) $role)
            : Role::where('name', $role)->first();

        if (! $roleModel) {
            Log::error('Role not found', ['role' => $role]);

            return false;
        }

        $user = static::find($uid);
        if (! $user) {
            Log::error('User not found', ['uid' => $uid]);

            return false;
        }

        $currentRoleId = $user->roles_id;
        $oldExpiryDate = $originalExpiryBeforeEdits
            ? Carbon::parse($originalExpiryBeforeEdits)
            : $user->rolechangedate;
        $currentExpiryDate = $user->rolechangedate;

        Log::info('updateUserRole - expiry date values', [
            'originalExpiryBeforeEdits_raw' => $originalExpiryBeforeEdits,
            'user_rolechangedate_raw' => $user->rolechangedate,
            'oldExpiryDate' => $oldExpiryDate?->toDateTimeString(),
            'currentExpiryDate' => $currentExpiryDate?->toDateTimeString(),
            'currentRoleId' => $currentRoleId,
            'newRoleId' => $roleModel->id,
        ]);

        // No role change needed
        if ($currentRoleId === $roleModel->id) {
            return self::handleSameRoleUpdate(
                $user,
                $roleModel,
                $applyPromotions,
                $addYears,
                $currentExpiryDate
            );
        }

        // Determine if we should stack
        $shouldStack = $stackRole && $currentExpiryDate?->isFuture();

        if ($shouldStack) {
            return self::handleStackedRoleChange(
                $user,
                $roleModel,
                $currentRoleId,
                $oldExpiryDate,
                $currentExpiryDate,
                $applyPromotions,
                $addYears,
                $changedBy
            );
        }

        return self::handleImmediateRoleChange(
            $user,
            $roleModel,
            $currentRoleId,
            $oldExpiryDate,
            $currentExpiryDate,
            $applyPromotions,
            $addYears,
            $changedBy,
            $preserveCurrentExpiry
        );
    }

    /**
     * Handle role update when role is not changing.
     */
    private static function handleSameRoleUpdate(
        self $user,
        Role $roleModel,
        bool $applyPromotions,
        ?int $addYears,
        ?Carbon $currentExpiryDate,
    ): bool {
        if (in_array($roleModel->name, self::PROMOTION_EXCLUDED_ROLES, true)) {
            return true;
        }

        $additionalDays = ($addYears ?? 0) * self::DAYS_PER_YEAR;
        $promotionDays = $applyPromotions
            ? RolePromotion::calculateAdditionalDays($roleModel->id)
            : 0;
        $totalDays = $additionalDays + $promotionDays;

        if ($totalDays <= 0) {
            return true;
        }

        $newExpiryDate = $currentExpiryDate?->isFuture()
            ? $currentExpiryDate->copy()->addDays($totalDays)
            : now()->addDays($totalDays);

        $updated = $user->update(['rolechangedate' => $newExpiryDate]);

        if ($updated && $promotionDays > 0) {
            self::trackPromotionApplication($user, $roleModel->id, $currentExpiryDate, $newExpiryDate);
        }

        return $updated;
    }

    /**
     * Handle stacked role change.
     */
    private static function handleStackedRoleChange(
        self $user,
        Role $roleModel,
        int $currentRoleId,
        ?Carbon $oldExpiryDate,
        Carbon $currentExpiryDate,
        bool $applyPromotions,
        ?int $addYears,
        ?int $changedBy,
    ): bool {
        // Determine the stacking start date
        // If user already has a pending stacked role, we need to calculate when THAT role would expire
        // and use that as the starting point for this new stacked role

        $stackingStartDate = $currentExpiryDate;

        // Check if there's already a pending role - if so, calculate when it would expire
        if ($user->hasPendingRole()) {
            $pendingRole = $user->getPendingRole();
            $pendingStartDate = $user->pending_role_start_date;

            if ($pendingRole && $pendingStartDate) {
                // Calculate when the pending role would expire
                $pendingRoleBaseDays = $pendingRole->addyears * self::DAYS_PER_YEAR;
                $pendingRolePromotionDays = ! in_array($pendingRole->name, self::PROMOTION_EXCLUDED_ROLES, true)
                    ? RolePromotion::calculateAdditionalDays($pendingRole->id)
                    : 0;
                $pendingRoleTotalDays = $pendingRoleBaseDays + $pendingRolePromotionDays;
                $pendingRoleExpiryDate = Carbon::parse($pendingStartDate)->addDays($pendingRoleTotalDays);

                Log::info('Existing pending role detected - calculating new stacking start date', [
                    'pendingRoleId' => $pendingRole->id,
                    'pendingRoleName' => $pendingRole->name,
                    'pendingStartDate' => $pendingStartDate->toDateTimeString(),
                    'pendingRoleBaseDays' => $pendingRoleBaseDays,
                    'pendingRolePromotionDays' => $pendingRolePromotionDays,
                    'pendingRoleExpiryDate' => $pendingRoleExpiryDate->toDateTimeString(),
                ]);

                // Use the pending role's expiry date as the new stacking start date
                if ($pendingRoleExpiryDate->isFuture()) {
                    $stackingStartDate = $pendingRoleExpiryDate;
                }
            }
        } else {
            // No pending role - use the latest of oldExpiryDate and currentExpiryDate
            Log::info('No pending role - comparing old and current expiry dates', [
                'oldExpiryDate' => $oldExpiryDate?->toDateTimeString(),
                'currentExpiryDate' => $currentExpiryDate->toDateTimeString(),
            ]);

            // Use the greater of the two dates if old expiry exists and is in the future
            if ($oldExpiryDate !== null && $oldExpiryDate->isFuture() && $oldExpiryDate->gt($currentExpiryDate)) {
                $stackingStartDate = $oldExpiryDate;
            }
        }

        Log::info('Final stacking start date selected', [
            'stackingStartDate' => $stackingStartDate->toDateTimeString(),
            'hadPendingRole' => $user->hasPendingRole(),
        ]);

        $baseDays = ($addYears ?? $roleModel->addyears) * self::DAYS_PER_YEAR;
        $promotionDays = ! in_array($roleModel->name, self::PROMOTION_EXCLUDED_ROLES, true) && $applyPromotions
            ? RolePromotion::calculateAdditionalDays($roleModel->id)
            : 0;
        $totalDays = $baseDays + $promotionDays;
        $newExpiryDate = $stackingStartDate->copy()->addDays($totalDays);

        $user->update([
            'pending_roles_id' => $roleModel->id,
            'pending_role_start_date' => $stackingStartDate,
        ]);

        try {
            UserRoleHistory::recordRoleChange(
                userId: $user->id,
                oldRoleId: $currentRoleId,
                newRoleId: $roleModel->id,
                oldExpiryDate: $stackingStartDate,
                newExpiryDate: $newExpiryDate,
                effectiveDate: $stackingStartDate,
                isStacked: true,
                changeReason: 'stacked_role_change',
                changedBy: $changedBy
            );
        } catch (\Exception $e) {
            Log::error('Failed to record role history', ['error' => $e->getMessage()]);
        }

        return true;
    }

    /**
     * Handle immediate role change.
     */
    private static function handleImmediateRoleChange(
        self $user,
        Role $roleModel,
        int $currentRoleId,
        ?Carbon $oldExpiryDate,
        ?Carbon $currentExpiryDate,
        bool $applyPromotions,
        ?int $addYears,
        ?int $changedBy,
        bool $preserveCurrentExpiry,
    ): bool {
        $baseDays = ($addYears ?? $roleModel->addyears) * self::DAYS_PER_YEAR;
        $promotionDays = ! in_array($roleModel->name, self::PROMOTION_EXCLUDED_ROLES, true) && $applyPromotions
            ? RolePromotion::calculateAdditionalDays($roleModel->id)
            : 0;
        $totalDays = $baseDays + $promotionDays;

        $newExpiryDate = match (true) {
            $preserveCurrentExpiry && $currentExpiryDate !== null => $currentExpiryDate,
            $totalDays > 0 => now()->addDays($totalDays),
            default => null,
        };

        $updated = $user->update([
            'roles_id' => $roleModel->id,
            'rolechangedate' => $newExpiryDate,
        ]);

        $user->syncRoles([$roleModel->name]);

        if ($updated) {
            try {
                UserRoleHistory::recordRoleChange(
                    userId: $user->id,
                    oldRoleId: $currentRoleId,
                    newRoleId: $roleModel->id,
                    oldExpiryDate: $oldExpiryDate,
                    newExpiryDate: $newExpiryDate,
                    effectiveDate: now(),
                    isStacked: false,
                    changeReason: 'immediate_role_change',
                    changedBy: $changedBy
                );
            } catch (\Exception $e) {
                Log::error('Failed to record role history', ['error' => $e->getMessage()]);
            }

            if ($applyPromotions && $promotionDays > 0) {
                self::trackPromotionApplication($user, $roleModel->id, $oldExpiryDate, $newExpiryDate);
            }
        }

        return $updated;
    }

    /**
     * Track promotion application statistics.
     */
    private static function trackPromotionApplication(
        self $user,
        int $roleId,
        ?Carbon $oldExpiryDate,
        ?Carbon $newExpiryDate,
    ): void {
        $promotions = RolePromotion::getActivePromotions($roleId);
        foreach ($promotions as $promotion) {
            $promotion->trackApplication($user->id, $roleId, $oldExpiryDate, $newExpiryDate);
        }
    }

    /**
     * Process expired roles and send notifications.
     */
    public static function updateExpiredRoles(): void
    {
        $now = CarbonImmutable::now();

        // Send expiration warnings
        self::sendExpirationWarnings($now);

        // Process expired roles
        self::processExpiredRoles($now);
    }

    /**
     * Send expiration warning emails.
     */
    private static function sendExpirationWarnings(CarbonImmutable $now): void
    {
        $periods = [
            'day' => $now->addDay(),
            'week' => $now->addWeek(),
            'month' => $now->addMonth(),
        ];

        foreach ($periods as $period) {
            $users = static::whereDate('rolechangedate', '=', $period)->get();
            $days = $now->diffInDays($period, true);

            foreach ($users as $user) {
                SendAccountWillExpireEmail::dispatch($user, $days)->onQueue('emails'); // @phpstan-ignore argument.type
            }
        }
    }

    /**
     * Process users with expired roles.
     */
    private static function processExpiredRoles(CarbonImmutable $now): void
    {
        static::expired()->each(function (self $user) use ($now) {
            $oldRoleId = $user->roles_id;
            $oldExpiryDate = $user->rolechangedate;

            // Check for pending stacked role
            if ($user->hasPendingRole() && $user->pending_role_start_date?->lte($now)) {
                self::activatePendingRole($user, $oldRoleId, $oldExpiryDate, $now);

                return;
            }

            // Downgrade to default user role
            self::downgradeToDefaultRole($user, $oldRoleId, $oldExpiryDate, $now);
        });
    }

    /**
     * Activate a pending stacked role.
     */
    private static function activatePendingRole(
        self $user,
        int $oldRoleId,
        ?Carbon $oldExpiryDate,
        CarbonImmutable $now,
    ): void {
        $roleModel = Role::find($user->pending_roles_id);
        if (! $roleModel) {
            return;
        }

        $baseDays = $roleModel->addyears * self::DAYS_PER_YEAR;
        $promotionDays = RolePromotion::calculateAdditionalDays($roleModel->id);
        $totalDays = $baseDays + $promotionDays;
        $newExpiryDate = $totalDays > 0 ? $now->addDays($totalDays) : null;

        $user->update([
            'roles_id' => $roleModel->id,
            'rolechangedate' => $newExpiryDate,
            'pending_roles_id' => null,
            'pending_role_start_date' => null,
        ]);
        $user->syncRoles([$roleModel->name]);

        UserRoleHistory::recordRoleChange(
            userId: $user->id,
            oldRoleId: $oldRoleId,
            newRoleId: $roleModel->id,
            oldExpiryDate: $oldExpiryDate,
            newExpiryDate: $newExpiryDate,
            effectiveDate: Carbon::instance($now),
            isStacked: true,
            changeReason: 'stacked_role_activated',
            changedBy: null
        );
    }

    /**
     * Downgrade user to default role.
     */
    private static function downgradeToDefaultRole(
        self $user,
        int $oldRoleId,
        ?Carbon $oldExpiryDate,
        CarbonImmutable $now,
    ): void {
        $user->update([
            'roles_id' => UserRole::USER->value,
            'rolechangedate' => null,
            'pending_roles_id' => null,
            'pending_role_start_date' => null,
        ]);
        $user->syncRoles(['User']);

        UserRoleHistory::recordRoleChange(
            userId: $user->id,
            oldRoleId: $oldRoleId,
            newRoleId: UserRole::USER->value,
            oldExpiryDate: $oldExpiryDate,
            newExpiryDate: null,
            effectiveDate: Carbon::instance($now),
            isStacked: false,
            changeReason: 'role_expired',
            changedBy: null
        );

        SendAccountExpiredEmail::dispatch($user)->onQueue('emails');
    }

    /**
     * Get paginated user list with filters.
     *
     * @return Collection<int, static>
     *
     * @throws \Throwable
     */
    public static function getRange(
        int|false $start,
        int $offset,
        string $orderBy,
        ?string $userName = '',
        ?string $email = '',
        ?string $host = '',
        ?string $role = '',
        bool $apiRequests = false,
        ?string $createdFrom = '',
        ?string $createdTo = '',
    ): Collection {
        $order = self::getBrowseOrder($orderBy);

        if ($apiRequests) {
            UserRequest::clearApiRequests(false);

            $query = '
                SELECT users.*, roles.name AS rolename, COUNT(user_requests.id) AS apirequests
                FROM users
                INNER JOIN roles ON roles.id = users.roles_id
                LEFT JOIN user_requests ON user_requests.users_id = users.id
                WHERE users.id != 0 %s %s %s %s %s %s
                AND email != \'sharing@nZEDb.com\'
                GROUP BY users.id
                ORDER BY %s %s %s';
        } else {
            $query = '
                SELECT users.*, roles.name AS rolename
                FROM users
                INNER JOIN roles ON roles.id = users.roles_id
                WHERE 1=1 %s %s %s %s %s %s
                ORDER BY %s %s %s';
        }

        return static::fromQuery(
            sprintf(
                $query,
                $userName ? 'AND users.username LIKE '.escapeString("%{$userName}%") : '',
                $email ? 'AND users.email LIKE '.escapeString("%{$email}%") : '',
                $host ? 'AND users.host LIKE '.escapeString("%{$host}%") : '',
                $role ? "AND users.roles_id = {$role}" : '',
                $createdFrom ? 'AND users.created_at >= '.escapeString("{$createdFrom} 00:00:00") : '',
                $createdTo ? 'AND users.created_at <= '.escapeString("{$createdTo} 23:59:59") : '',
                $order[0],
                $order[1],
                $start === false ? '' : "LIMIT {$offset} OFFSET {$start}"
            )
        );
    }

    /**
     * Get sort configuration for user browsing.
     *
     * @return array{0: string, 1: string}
     */
    public static function getBrowseOrder(string $orderBy = ''): array
    {
        $order = $orderBy ?: 'username_desc';
        $parts = explode('_', $order);

        $field = match ($parts[0]) {
            'email' => 'email',
            'host' => 'host',
            'createdat' => 'created_at',
            'lastlogin' => 'lastlogin',
            'apiaccess' => 'apiaccess',
            'grabs' => 'grabs',
            'role' => 'rolename',
            'rolechangedate' => 'rolechangedate',
            'verification' => 'verified',
            default => 'username',
        };

        $direction = isset($parts[1]) && preg_match('/^asc|desc$/i', $parts[1])
            ? $parts[1]
            : 'desc';

        return [$field, $direction];
    }

    // ===== Password Methods =====

    /**
     * Verify password and rehash if needed.
     */
    public static function checkPassword(string $password, string $hash, int $userId = -1): bool
    {
        if (! Hash::check($password, $hash)) {
            return false;
        }

        if ($userId > 0 && Hash::needsRehash($hash)) {
            static::find($userId)?->update(['password' => Hash::make($password)]);
        }

        return true;
    }

    /**
     * Hash a password.
     */
    public static function hashPassword(string $password): string
    {
        return Hash::make($password);
    }

    /**
     * Generate a secure random password.
     *
     * @throws \Exception
     */
    public static function generatePassword(int $length = 15): string
    {
        return Str::password($length);
    }

    // ===== Update Methods =====

    /**
     * Regenerate RSS key.
     */
    public static function updateRssKey(int $uid): int
    {
        static::find($uid)?->update([
            'api_token' => md5(Password::getRepository()->createNewToken()), // @phpstan-ignore method.notFound
        ]);

        return SignupError::SUCCESS->value;
    }

    /**
     * Update password reset GUID.
     */
    public static function updatePassResetGuid(int $id, ?string $guid): int
    {
        static::find($id)?->update(['resetguid' => $guid]);

        return SignupError::SUCCESS->value;
    }

    /**
     * Update user password.
     */
    public static function updatePassword(int $id, string $password): int
    {
        static::find($id)?->update(['password' => Hash::make($password)]);

        return SignupError::SUCCESS->value;
    }

    /**
     * Update user role change date.
     */
    public static function updateUserRoleChangeDate(int $id, string $roleChangeDate): int
    {
        static::find($id)?->update(['rolechangedate' => $roleChangeDate]);

        return SignupError::SUCCESS->value;
    }

    /**
     * Increment user's grab count.
     */
    public static function incrementGrabs(int $id, int $num = 1): void
    {
        static::find($id)?->increment('grabs', $num);
    }

    // ===== Validation Methods =====

    /**
     * Validate a URL format.
     */
    public static function isValidUrl(string $url): bool
    {
        return (bool) preg_match(
            '/^(https?|ftp):\/\/([A-Z0-9][A-Z0-9_-]*(?:\.[A-Z0-9][A-Z0-9_-]*)+):?(\d+)?\/?/i',
            $url
        );
    }

    // ===== Registration Methods =====

    /**
     * Register a new user.
     *
     * @throws \Exception
     */
    public static function signUp(
        string $userName,
        string $password,
        string $email,
        string $host,
        ?string $notes,
        int $invites = Invitation::DEFAULT_INVITES,
        string $inviteCode = '',
        bool $forceInviteMode = false,
        int $role = UserRole::USER->value,
        bool $validate = true,
    ): int|string {
        $userData = [
            'username' => trim($userName),
            'password' => trim($password),
            'email' => trim($email),
        ];

        if ($validate) {
            $validator = Validator::make($userData, [
                'username' => ['required', 'string', 'min:5', 'max:255', 'unique:users'],
                'email' => ['required', 'string', 'email', 'max:255', 'unique:users', new ValidEmailDomain],
                'password' => [
                    'required',
                    'string',
                    'min:8',
                    'confirmed',
                    'regex:/^(?=.*?[A-Z])(?=.*?[a-z])(?=.*?[0-9])(?=.*?[#?!@$%^&*-]).{8,}$/',
                ],
            ]);

            if ($validator->fails()) {
                return implode('', Arr::collapse($validator->errors()->toArray()));
            }
        }

        $invitedBy = 0;
        if (! $forceInviteMode && (int) Settings::settingValue('registerstatus') === Settings::REGISTER_STATUS_INVITE) {
            if ($inviteCode === '') {
                return SignupError::BAD_INVITE_CODE->value;
            }

            $invitedBy = self::checkAndUseInvite($inviteCode);
            if ($invitedBy < 0) {
                return SignupError::BAD_INVITE_CODE->value;
            }
        }

        return self::add(
            $userData['username'],
            $userData['password'],
            $userData['email'],
            $role,
            $notes,
            $host,
            $invites,
            $invitedBy
        );
    }

    /**
     * Validate and consume an invite code.
     */
    public static function checkAndUseInvite(string $inviteCode): int
    {
        $invite = Invitation::findValidByToken($inviteCode);
        if (! $invite) {
            return -1;
        }

        static::where('id', $invite->invited_by)->decrement('invites');
        $invite->markAsUsed(0);

        return $invite->invited_by;
    }

    /**
     * Create a new user.
     */
    public static function add(
        string $userName,
        string $password,
        string $email,
        int $role,
        ?string $notes = '',
        string $host = '',
        int $invites = Invitation::DEFAULT_INVITES,
        int $invitedBy = 0,
    ): int {
        $hashedPassword = Hash::make($password);

        $storeIps = config('nntmux:settings.store_user_ip') === true ? $host : '';

        $user = static::create([
            'username' => $userName,
            'password' => $hashedPassword,
            'email' => $email,
            'host' => $storeIps,
            'roles_id' => $role,
            'invites' => $invites,
            'invitedby' => $invitedBy === 0 ? null : $invitedBy,
            'notes' => $notes,
        ]);

        return $user->id;
    }

    // ===== Category Exclusion Methods =====

    /**
     * Get excluded category IDs for a user.
     *
     * This includes both permission-based exclusions (entire root categories)
     * and user-selected subcategory exclusions from user_excluded_categories table.
     *
     * @return array<int>
     *
     * @throws \Exception
     */
    public static function getCategoryExclusionById(int $userId): array
    {
        $user = static::findOrFail($userId);

        $userAllowed = $user->getDirectPermissions()->pluck('name')->toArray();
        $roleAllowed = $user->getAllPermissions()->pluck('name')->toArray();
        $allowed = array_intersect($roleAllowed, $userAllowed);

        $categoryPermissions = [
            'view console' => 1000,
            'view movies' => 2000,
            'view audio' => 3000,
            'view pc' => 4000,
            'view tv' => 5000,
            'view adult' => 6000,
            'view books' => 7000,
            'view other' => 1,
        ];

        $excludedRoots = [];
        foreach ($categoryPermissions as $permission => $rootId) {
            if (! in_array($permission, $allowed, true)) {
                $excludedRoots[] = $rootId;
            }
        }

        // Get all subcategories that belong to excluded root categories
        $permissionExclusions = Category::whereIn('root_categories_id', $excludedRoots)
            ->pluck('id')
            ->toArray();

        // Get user's custom excluded subcategories from pivot table
        $userExclusions = $user->excludedCategories()
            ->pluck('categories_id')
            ->toArray();

        // Filter out user exclusions that belong to already excluded root categories
        // to avoid duplicates
        $filteredUserExclusions = array_filter($userExclusions, function ($categoryId) use ($excludedRoots) {
            $category = Category::find($categoryId);

            return $category && ! in_array($category->root_categories_id, $excludedRoots);
        });

        // Merge permission-based exclusions with user-selected subcategory exclusions
        return array_unique(array_merge($permissionExclusions, $filteredUserExclusions));
    }

    /**
     * Sync user's excluded subcategories.
     *
     * @param  array<int>  $categoryIds  Array of category IDs to exclude
     */
    public function syncExcludedCategories(array $categoryIds): void
    {
        // Delete existing exclusions
        $this->excludedCategories()->delete();

        // Insert new exclusions
        foreach ($categoryIds as $categoryId) {
            UserExcludedCategory::create([
                'users_id' => $this->id,
                'categories_id' => (int) $categoryId,
            ]);
        }
    }

    /**
     * Get excluded categories for API request.
     *
     * @return array<string, mixed>
     *
     * @throws \Exception
     */
    public static function getCategoryExclusionForApi(Request $request): array
    {
        $apiToken = $request->input('api_token') ?? $request->input('apikey');
        $user = static::findByRssToken($apiToken);

        return $user ? static::getCategoryExclusionById($user->id) : [];
    }

    // ===== Invitation Methods =====

    /**
     * Send an invitation email.
     *
     * @throws \Exception
     */
    public static function sendInvite(string $serverUrl, int $uid, string $emailTo): string
    {
        $user = static::findOrFail($uid);

        $invitation = Invitation::createInvitation($emailTo, $user->id);
        $url = "{$serverUrl}/register?token={$invitation->token}";

        app(InvitationService::class)->sendInvitationEmail($invitation);

        return $url;
    }

    // ===== Cleanup Methods =====

    /**
     * Delete unverified users older than 3 days.
     */
    public static function deleteUnVerified(): void
    {
        static::whereVerified(0)
            ->where('created_at', '<', now()->subDays(3))
            ->delete();
    }

    /**
     * Check if user can post.
     */
    public static function canPost(int $userId): bool
    {
        return (bool) static::where('id', $userId)->value('can_post');
    }

    /**
     * Get the user's timezone.
     */
    public function getTimezone(): string
    {
        return $this->timezone ?? 'UTC';
    }

    // ===== Legacy Constants (Deprecated - Use Enums) =====

    /** @deprecated Use SignupError::BAD_USERNAME->value instead */
    public const ERR_SIGNUP_BADUNAME = SignupError::BAD_USERNAME->value;

    /** @deprecated Use SignupError::BAD_PASSWORD->value instead */
    public const ERR_SIGNUP_BADPASS = SignupError::BAD_PASSWORD->value;

    /** @deprecated Use SignupError::BAD_EMAIL->value instead */
    public const ERR_SIGNUP_BADEMAIL = SignupError::BAD_EMAIL->value;

    /** @deprecated Use SignupError::USERNAME_IN_USE->value instead */
    public const ERR_SIGNUP_UNAMEINUSE = SignupError::USERNAME_IN_USE->value;

    /** @deprecated Use SignupError::EMAIL_IN_USE->value instead */
    public const ERR_SIGNUP_EMAILINUSE = SignupError::EMAIL_IN_USE->value;

    /** @deprecated Use SignupError::BAD_INVITE_CODE->value instead */
    public const ERR_SIGNUP_BADINVITECODE = SignupError::BAD_INVITE_CODE->value;

    /** @deprecated Use SignupError::SUCCESS->value instead */
    public const SUCCESS = SignupError::SUCCESS->value;

    /** @deprecated Use UserRole::USER->value instead */
    public const ROLE_USER = UserRole::USER->value;

    /** @deprecated Use UserRole::ADMIN->value instead */
    public const ROLE_ADMIN = UserRole::ADMIN->value;

    /** @deprecated Use UserRole::DISABLED->value instead */
    public const ROLE_DISABLED = UserRole::DISABLED->value;

    /** @deprecated Use UserRole::MODERATOR->value instead */
    public const ROLE_MODERATOR = UserRole::MODERATOR->value;

    /** @deprecated Use QueueType::NONE->value instead */
    public const QUEUE_NONE = QueueType::NONE->value;

    /** @deprecated Use QueueType::SABNZBD->value instead */
    public const QUEUE_SABNZBD = QueueType::SABNZBD->value;

    /** @deprecated Use QueueType::NZBGET->value instead */
    public const QUEUE_NZBGET = QueueType::NZBGET->value;
}
