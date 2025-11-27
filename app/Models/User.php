<?php

namespace App\Models;

use App\Jobs\SendAccountExpiredEmail;
use App\Jobs\SendAccountWillExpireEmail;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Jrean\UserVerification\Traits\UserVerification;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

/**
 * App\Models\User.
 *
 * App\Models\User.
 *
 * @property int $id
 * @property string $username
 * @property string|null $firstname
 * @property string|null $lastname
 * @property string $email
 * @property string $password
 * @property int $user_roles_id FK to roles.id
 * @property string|null $host
 * @property int $grabs
 * @property string $rsstoken
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property string|null $resetguid
 * @property string|null $lastlogin
 * @property string|null $apiaccess
 * @property int $invites
 * @property int|null $invitedby
 * @property int $movieview
 * @property int $xxxview
 * @property int $musicview
 * @property int $consoleview
 * @property int $bookview
 * @property int $gameview
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
 * @property string|null $rolechangedate When does the role expire
 * @property string|null $remember_token
 * @property-read Collection|\App\Models\ReleaseComment[] $comment
 * @property-read Collection|\App\Models\UserDownload[] $download
 * @property-read Collection|\App\Models\DnzbFailure[] $failedRelease
 * @property-read Collection|\App\Models\Invitation[] $invitation
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection|\Illuminate\Notifications\DatabaseNotification[] $notifications
 * @property-read Collection|\App\Models\UsersRelease[] $release
 * @property-read Collection|\App\Models\UserRequest[] $request
 * @property-read Collection|\App\Models\UserSerie[] $series
 *
 * @method static Builder|\App\Models\User whereApiaccess($value)
 * @method static Builder|\App\Models\User whereBookview($value)
 * @method static Builder|\App\Models\User whereConsoleview($value)
 * @method static Builder|\App\Models\User whereCpApi($value)
 * @method static Builder|\App\Models\User whereCpUrl($value)
 * @method static Builder|\App\Models\User whereCreatedAt($value)
 * @method static Builder|\App\Models\User whereEmail($value)
 * @method static Builder|\App\Models\User whereFirstname($value)
 * @method static Builder|\App\Models\User whereGameview($value)
 * @method static Builder|\App\Models\User whereGrabs($value)
 * @method static Builder|\App\Models\User whereHost($value)
 * @method static Builder|\App\Models\User whereId($value)
 * @method static Builder|\App\Models\User whereInvitedby($value)
 * @method static Builder|\App\Models\User whereInvites($value)
 * @method static Builder|\App\Models\User whereLastlogin($value)
 * @method static Builder|\App\Models\User whereLastname($value)
 * @method static Builder|\App\Models\User whereMovieview($value)
 * @method static Builder|\App\Models\User whereMusicview($value)
 * @method static Builder|\App\Models\User whereNotes($value)
 * @method static Builder|\App\Models\User whereNzbgetpassword($value)
 * @method static Builder|\App\Models\User whereNzbgeturl($value)
 * @method static Builder|\App\Models\User whereNzbgetusername($value)
 * @method static Builder|\App\Models\User whereNzbvortexApiKey($value)
 * @method static Builder|\App\Models\User whereNzbvortexServerUrl($value)
 * @method static Builder|\App\Models\User wherePassword($value)
 * @method static Builder|\App\Models\User whereRememberToken($value)
 * @method static Builder|\App\Models\User whereResetguid($value)
 * @method static Builder|\App\Models\User whereRolechangedate($value)
 * @method static Builder|\App\Models\User whereRsstoken($value)
 * @method static Builder|\App\Models\User whereSabapikey($value)
 * @method static Builder|\App\Models\User whereSabapikeytype($value)
 * @method static Builder|\App\Models\User whereSabpriority($value)
 * @method static Builder|\App\Models\User whereSaburl($value)
 * @method static Builder|\App\Models\User whereStyle($value)
 * @method static Builder|\App\Models\User whereUpdatedAt($value)
 * @method static Builder|\App\Models\User whereUserRolesId($value)
 * @method static Builder|\App\Models\User whereUsername($value)
 * @method static Builder|\App\Models\User whereXxxview($value)
 * @method static Builder|\App\Models\User whereVerified($value)
 * @method static Builder|\App\Models\User whereApiToken($value)
 *
 * @mixin \Eloquent
 *
 * @property int $roles_id FK to roles.id
 * @property string $api_token
 * @property int $rate_limit
 * @property string|null $email_verified_at
 * @property int $verified
 * @property string|null $verification_token
 * @property-read Collection|\Junaidnasir\Larainvite\Models\LaraInviteModel[] $invitationPending
 * @property-read Collection|\Junaidnasir\Larainvite\Models\LaraInviteModel[] $invitationSuccess
 * @property-read Collection|\Junaidnasir\Larainvite\Models\LaraInviteModel[] $invitations
 * @property-read Collection|\Spatie\Permission\Models\Permission[] $permissions
 * @property-read Role $role
 * @property-read Collection|\Spatie\Permission\Models\Role[] $roles
 *
 * @method static Builder|\App\Models\User newModelQuery()
 * @method static Builder|\App\Models\User newQuery()
 * @method static Builder|\App\Models\User permission($permissions)
 * @method static Builder|\App\Models\User query()
 * @method static Builder|\App\Models\User whereEmailVerifiedAt($value)
 * @method static Builder|\App\Models\User whereRateLimit($value)
 * @method static Builder|\App\Models\User whereRolesId($value)
 * @method static Builder|\App\Models\User whereVerificationToken($value)
 */
class User extends Authenticatable
{
    use HasRoles, Notifiable, SoftDeletes, UserVerification;

    public const ERR_SIGNUP_BADUNAME = -1;

    public const ERR_SIGNUP_BADPASS = -2;

    public const ERR_SIGNUP_BADEMAIL = -3;

    public const ERR_SIGNUP_UNAMEINUSE = -4;

    public const ERR_SIGNUP_EMAILINUSE = -5;

    public const ERR_SIGNUP_BADINVITECODE = -6;

    public const SUCCESS = 1;

    public const ROLE_USER = 1;

    public const ROLE_ADMIN = 2;

    public const ROLE_DISABLED = 3;

    public const ROLE_MODERATOR = 4;

    /**
     * Users SELECT queue type.
     */
    public const QUEUE_NONE = 0;

    public const QUEUE_SABNZBD = 1;

    public const QUEUE_NZBGET = 2;

    /**
     * @var string
     */

    /**
     * @var bool
     */
    protected $dateFormat = false;

    /**
     * @var array
     */
    protected $hidden = ['remember_token', 'password'];

    /**
     * @var array
     */
    protected $guarded = [];

    protected function getDefaultGuardName(): string
    {
        return 'web';
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'roles_id');
    }

    public function request(): HasMany
    {
        return $this->hasMany(UserRequest::class, 'users_id');
    }

    public function download(): HasMany
    {
        return $this->hasMany(UserDownload::class, 'users_id');
    }

    public function release(): HasMany
    {
        return $this->hasMany(UsersRelease::class, 'users_id');
    }

    public function series(): HasMany
    {
        return $this->hasMany(UserSerie::class, 'users_id');
    }

    public function invitation(): HasMany
    {
        return $this->hasMany(Invitation::class, 'invited_by');
    }

    public function failedRelease(): HasMany
    {
        return $this->hasMany(DnzbFailure::class, 'users_id');
    }

    public function comment(): HasMany
    {
        return $this->hasMany(ReleaseComment::class, 'users_id');
    }

    /**
     * Get the user's timezone or default to UTC
     */
    public function getTimezone(): string
    {
        return $this->timezone ?? 'UTC';
    }

    /**
     * @throws \Exception
     */
    public static function deleteUser($id): void
    {
        self::find($id)->delete();
    }

    public static function getCount(?string $role = null, ?string $username = '', ?string $host = '', ?string $email = '', ?string $createdFrom = '', ?string $createdTo = ''): int
    {
        $res = self::query()->withTrashed()->where('email', '<>', 'sharing@nZEDb.com');

        if (! empty($role)) {
            $res->where('roles_id', $role);
        }

        if ($username !== '') {
            $res->where('username', 'like', '%'.$username.'%');
        }

        if ($host !== '') {
            $res->where('host', 'like', '%'.$host.'%');
        }

        if ($email !== '') {
            $res->where('email', 'like', '%'.$email.'%');
        }

        if ($createdFrom !== '') {
            $res->where('created_at', '>=', $createdFrom.' 00:00:00');
        }

        if ($createdTo !== '') {
            $res->where('created_at', '<=', $createdTo.' 23:59:59');
        }

        return $res->count(['id']);
    }

    public static function updateUser(int $id, string $userName, ?string $email, int $grabs, int $role, ?string $notes, int $invites, int $movieview, int $musicview, int $gameview, int $xxxview, int $consoleview, int $bookview, string $style = 'None'): int
    {
        $userName = trim($userName);

        $rateLimit = Role::query()->where('id', $role)->first();

        $sql = [
            'username' => $userName,
            'grabs' => $grabs,
            'roles_id' => $role,
            'notes' => substr($notes, 0, 255),
            'invites' => $invites,
            'movieview' => $movieview,
            'musicview' => $musicview,
            'gameview' => $gameview,
            'xxxview' => $xxxview,
            'consoleview' => $consoleview,
            'bookview' => $bookview,
            'style' => $style,
            'rate_limit' => $rateLimit ? $rateLimit['rate_limit'] : 60,
        ];

        if (! empty($email)) {
            $email = trim($email);
            $sql += ['email' => $email];
        }

        $user = self::find($id);
        $user->update($sql);
        $user->syncRoles([$rateLimit['name']]);

        return self::SUCCESS;
    }

    /**
     * @return User|Builder|Model|object|null
     */
    public static function getByUsername(string $userName)
    {
        return self::whereUsername($userName)->first();
    }

    /**
     * @return Model|static
     *
     * @throws ModelNotFoundException
     */
    public static function getByEmail(string $email)
    {
        return self::whereEmail($email)->first();
    }

    public static function updateUserRole(int $uid, int|string $role): bool
    {
        if (is_int($role)) {
            $roleQuery = Role::query()->where('id', $role)->first();
        } else {
            $roleQuery = Role::query()->where('name', $role)->first();
        }
        $roleName = $roleQuery->name;

        $user = self::find($uid);
        $user->syncRoles([$roleName]);

        return self::find($uid)->update(['roles_id' => $roleQuery->id]);
    }

    public static function updateUserRoleChangeDate(int $uid, $date = '', int $addYear = 0): void
    {
        $user = self::find($uid);
        $currRoleExp = $user->rolechangedate ?? now()->toDateTimeString();
        if (! empty($date)) {
            $user->update(['rolechangedate' => $date]);
        }
        if (empty($date) && ! empty($addYear)) {
            $user->update(['rolechangedate' => Carbon::createFromDate($currRoleExp)->addYears($addYear)]);
        }
    }

    public static function updateExpiredRoles(): void
    {
        $now = CarbonImmutable::now();
        $period = [
            'day' => $now->addDay(),
            'week' => $now->addWeek(),
            'month' => $now->addMonth(),
        ];

        foreach ($period as $value) {
            $users = self::query()->whereDate('rolechangedate', '=', $value)->get();
            $days = $now->diffInDays($value, true);
            foreach ($users as $user) {
                SendAccountWillExpireEmail::dispatch($user, $days)->onQueue('emails');
            }
        }
        foreach (self::query()->whereDate('rolechangedate', '<', $now)->get() as $expired) {
            $expired->update(['roles_id' => self::ROLE_USER, 'rolechangedate' => null]);
            $expired->syncRoles(['User']);
            SendAccountExpiredEmail::dispatch($expired)->onQueue('emails');
        }
    }

    /**
     * @throws \Throwable
     */
    public static function getRange($start, $offset, $orderBy, ?string $userName = '', ?string $email = '', ?string $host = '', ?string $role = '', bool $apiRequests = false, ?string $createdFrom = '', ?string $createdTo = ''): Collection
    {
        if ($apiRequests) {
            UserRequest::clearApiRequests(false);
            $query = "
				SELECT users.*, roles.name AS rolename, COUNT(user_requests.id) AS apirequests
				FROM users
				INNER JOIN roles ON roles.id = users.roles_id
				LEFT JOIN user_requests ON user_requests.users_id = users.id
				WHERE users.id != 0 %s %s %s %s %s %s
				AND email != 'sharing@nZEDb.com'
				GROUP BY users.id
				ORDER BY %s %s %s ";
        } else {
            $query = '
				SELECT users.*, roles.name AS rolename
				FROM users
				INNER JOIN roles ON roles.id = users.roles_id
				WHERE 1=1 %s %s %s %s %s %s
				ORDER BY %s %s %s';
        }
        $order = self::getBrowseOrder($orderBy);

        return self::fromQuery(
            sprintf(
                $query,
                ! empty($userName) ? 'AND users.username '.'LIKE '.escapeString('%'.$userName.'%') : '',
                ! empty($email) ? 'AND users.email '.'LIKE '.escapeString('%'.$email.'%') : '',
                ! empty($host) ? 'AND users.host '.'LIKE '.escapeString('%'.$host.'%') : '',
                (! empty($role) ? ('AND users.roles_id = '.$role) : ''),
                ! empty($createdFrom) ? 'AND users.created_at >= '.escapeString($createdFrom.' 00:00:00') : '',
                ! empty($createdTo) ? 'AND users.created_at <= '.escapeString($createdTo.' 23:59:59') : '',
                $order[0],
                $order[1],
                ($start === false ? '' : ('LIMIT '.$offset.' OFFSET '.$start))
            )
        );
    }

    /**
     * Get sort types for sorting users on the web page user list.
     *
     * @return string[]
     */
    public static function getBrowseOrder($orderBy): array
    {
        $order = (empty($orderBy) ? 'username_desc' : $orderBy);
        $orderArr = explode('_', $order);
        $orderField = match ($orderArr[0]) {
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
        $orderSort = (isset($orderArr[1]) && preg_match('/^asc|desc$/i', $orderArr[1])) ? $orderArr[1] : 'desc';

        return [$orderField, $orderSort];
    }

    /**
     * Verify a password against a hash.
     *
     * Automatically update the hash if it needs to be.
     *
     * @param  string  $password  Password to check against hash.
     * @param  bool|string  $hash  Hash to check against password.
     * @param  int  $userID  ID of the user.
     */
    public static function checkPassword(string $password, bool|string $hash, int $userID = -1): bool
    {
        if (Hash::check($password, $hash) === false) {
            return false;
        }

        // Update the hash if it needs to be.
        if (is_numeric($userID) && $userID > 0 && Hash::needsRehash($hash)) {
            $hash = self::hashPassword($password);

            if ($hash !== false) {
                self::find($userID)->update(['password' => $hash]);
            }
        }

        return true;
    }

    public static function updateRssKey($uid): int
    {
        self::find($uid)->update(['api_token' => md5(Password::getRepository()->createNewToken())]);

        return self::SUCCESS;
    }

    public static function updatePassResetGuid($id, $guid): int
    {
        self::find($id)->update(['resetguid' => $guid]);

        return self::SUCCESS;
    }

    public static function updatePassword(int $id, string $password): int
    {
        self::find($id)->update(['password' => self::hashPassword($password)]);

        return self::SUCCESS;
    }

    public static function hashPassword($password): string
    {
        return Hash::make($password);
    }

    /**
     * @return Model|static
     *
     * @throws ModelNotFoundException
     */
    public static function getByPassResetGuid(string $guid)
    {
        return self::whereResetguid($guid)->first();
    }

    public static function incrementGrabs(int $id, int $num = 1): void
    {
        self::find($id)->increment('grabs', $num);
    }

    /**
     * @return Model|null|static
     */
    public static function getByRssToken(string $rssToken)
    {
        return self::whereApiToken($rssToken)->first();
    }

    public static function isValidUrl($url): bool
    {
        return (! preg_match('/^(http|https|ftp):\/\/([A-Z0-9][A-Z0-9_-]*(?:\.[A-Z0-9][A-Z0-9_-]*)+):?(\d+)?\/?/i', $url)) ? false : true;
    }

    /**
     * @throws \Exception
     */
    public static function generatePassword(int $length = 15): string
    {
        return Str::password($length);
    }

    /**
     * @throws \Exception
     */
    public static function signUp($userName, $password, $email, $host, $notes, int $invites = Invitation::DEFAULT_INVITES, string $inviteCode = '', bool $forceInviteMode = false, int $role = self::ROLE_USER, bool $validate = true): bool|int|string
    {
        $user = [
            'username' => trim($userName),
            'password' => trim($password),
            'email' => trim($email),
        ];

        if ($validate) {
            $validator = Validator::make($user, [
                'username' => ['required', 'string', 'min:5', 'max:255', 'unique:users'],
                'email' => ['required', 'string', 'email', 'max:255', 'unique:users', 'indisposable'],
                'password' => ['required', 'string', 'min:8', 'confirmed', 'regex:/^(?=.*?[A-Z])(?=.*?[a-z])(?=.*?[0-9])(?=.*?[#?!@$%^&*-]).{8,}$/'],
            ]);

            if ($validator->fails()) {
                return implode('', Arr::collapse($validator->errors()->toArray()));
            }
        }

        // Make sure this is the last check, as if a further validation check failed, the invite would still have been used up.
        $invitedBy = 0;
        if (! $forceInviteMode && (int) Settings::settingValue('registerstatus') === Settings::REGISTER_STATUS_INVITE) {
            if ($inviteCode === '') {
                return self::ERR_SIGNUP_BADINVITECODE;
            }

            $invitedBy = self::checkAndUseInvite($inviteCode);
            if ($invitedBy < 0) {
                return self::ERR_SIGNUP_BADINVITECODE;
            }
        }

        return self::add($user['username'], $user['password'], $user['email'], $role, $notes, $host, $invites, $invitedBy);
    }

    /**
     * If a invite is used, decrement the person who invited's invite count.
     */
    public static function checkAndUseInvite(string $inviteCode): int
    {
        $invite = Invitation::findValidByToken($inviteCode);
        if (! $invite) {
            return -1;
        }

        self::query()->where('id', $invite->invited_by)->decrement('invites');
        $invite->markAsUsed(0); // Will be updated with actual user ID later

        return $invite->invited_by;
    }

    /**
     * @return false|int|mixed
     */
    public static function add(string $userName, string $password, string $email, int $role, ?string $notes = '', string $host = '', int $invites = Invitation::DEFAULT_INVITES, int $invitedBy = 0)
    {
        $password = self::hashPassword($password);
        if (! $password) {
            return false;
        }

        $storeips = config('nntmux:settings.store_user_ip') === true ? $host : '';

        $user = self::create(
            [
                'username' => $userName,
                'password' => $password,
                'email' => $email,
                'host' => $storeips,
                'roles_id' => $role,
                'invites' => $invites,
                'invitedby' => (int) $invitedBy === 0 ? null : $invitedBy,
                'notes' => $notes,
            ]
        );

        return $user->id;
    }

    /**
     * Get the list of categories the user has excluded.
     *
     * @param  int  $userID  ID of the user.
     *
     * @throws \Exception
     */
    public static function getCategoryExclusionById(int $userID): array
    {
        $ret = [];

        $user = self::find($userID);

        $userAllowed = $user->getDirectPermissions()->pluck('name')->toArray();
        $roleAllowed = $user->getAllPermissions()->pluck('name')->toArray();

        $allowed = array_intersect($roleAllowed, $userAllowed);

        $cats = ['view console', 'view movies', 'view audio', 'view tv', 'view pc', 'view adult', 'view books', 'view other'];

        if (! empty($allowed)) {
            foreach ($cats as $cat) {
                if (! \in_array($cat, $allowed, false)) {
                    $ret[] = match ($cat) {
                        'view console' => 1000,
                        'view movies' => 2000,
                        'view audio' => 3000,
                        'view pc' => 4000,
                        'view tv' => 5000,
                        'view adult' => 6000,
                        'view books' => 7000,
                        'view other' => 1,
                    };
                }
            }
        }

        return Category::query()->whereIn('root_categories_id', $ret)->pluck('id')->toArray();
    }

    /**
     * @throws \Exception
     */
    public static function getCategoryExclusionForApi(Request $request): array
    {
        $apiToken = $request->has('api_token') ? $request->input('api_token') : $request->input('apikey');
        $user = self::getByRssToken($apiToken);

        return self::getCategoryExclusionById($user->id);
    }

    /**
     * @throws \Exception
     */
    public static function sendInvite($serverUrl, $uid, $emailTo): string
    {
        $user = self::find($uid);

        // Create invitation using our custom system
        $invitation = Invitation::createInvitation($emailTo, $user->id);
        $url = $serverUrl.'/register?token='.$invitation->token;

        // Send invitation email
        $invitationService = app(InvitationService::class);
        $invitationService->sendInvitationEmail($invitation);

        return $url;
    }

    /**
     * Deletes users that have not verified their accounts for 3 or more days.
     */
    public static function deleteUnVerified(): void
    {
        static::whereVerified(0)->where('created_at', '<', now()->subDays(3))->delete();
    }

    public function passwordSecurity(): HasOne
    {
        return $this->hasOne(PasswordSecurity::class);
    }

    public static function canPost($user_id): bool
    {
        // return true if can_post column is true and false if can_post column is false
        return self::where('id', $user_id)->value('can_post');
    }
}
