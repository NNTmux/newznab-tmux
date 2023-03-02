<?php

namespace App\Models;

use App\Jobs\SendAccountExpiredEmail;
use App\Jobs\SendAccountWillExpireEmail;
use App\Jobs\SendInviteEmail;
use Carbon\CarbonImmutable;
use DariusIII\Token\Facades\Token;
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
use Junaidnasir\Larainvite\Facades\Invite;
use Junaidnasir\Larainvite\InviteTrait;
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
 * @property bool $queuetype Type of queue, Sab or NZBGet
 * @property string|null $nzbgeturl
 * @property string|null $nzbgetusername
 * @property string|null $nzbgetpassword
 * @property string|null $nzbvortex_api_key
 * @property string|null $nzbvortex_server_url
 * @property string $userseed
 * @property string $notes
 * @property string|null $cp_url
 * @property string|null $cp_api
 * @property string|null $style
 * @property string|null $rolechangedate When does the role expire
 * @property string|null $remember_token
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\ReleaseComment[] $comment
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\UserDownload[] $download
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\DnzbFailure[] $failedRelease
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Invitation[] $invitation
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection|\Illuminate\Notifications\DatabaseNotification[] $notifications
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\UsersRelease[] $release
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\UserRequest[] $request
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\UserSerie[] $series
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\User whereApiaccess($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\User whereBookview($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\User whereConsoleview($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\User whereCpApi($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\User whereCpUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\User whereFirstname($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\User whereGameview($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\User whereGrabs($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\User whereHost($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\User whereInvitedby($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\User whereInvites($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\User whereLastlogin($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\User whereLastname($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\User whereMovieview($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\User whereMusicview($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\User whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\User whereNzbgetpassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\User whereNzbgeturl($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\User whereNzbgetusername($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\User whereNzbvortexApiKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\User whereNzbvortexServerUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\User whereQueuetype($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\User whereResetguid($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\User whereRolechangedate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\User whereRsstoken($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\User whereSabapikey($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\User whereSabapikeytype($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\User whereSabpriority($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\User whereSaburl($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\User whereStyle($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\User whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\User whereUserRolesId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\User whereUsername($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\User whereUserseed($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\User whereXxxview($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\User whereVerified($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\User whereApiToken($value)
 *
 * @mixin \Eloquent
 *
 * @property int $roles_id FK to roles.id
 * @property string $api_token
 * @property int $rate_limit
 * @property string|null $email_verified_at
 * @property int $verified
 * @property string|null $verification_token
 * @property-read \Illuminate\Database\Eloquent\Collection|\Junaidnasir\Larainvite\Models\LaraInviteModel[] $invitationPending
 * @property-read \Illuminate\Database\Eloquent\Collection|\Junaidnasir\Larainvite\Models\LaraInviteModel[] $invitationSuccess
 * @property-read \Illuminate\Database\Eloquent\Collection|\Junaidnasir\Larainvite\Models\LaraInviteModel[] $invitations
 * @property-read \Illuminate\Database\Eloquent\Collection|\Spatie\Permission\Models\Permission[] $permissions
 * @property-read \Spatie\Permission\Models\Role $role
 * @property-read \Illuminate\Database\Eloquent\Collection|\Spatie\Permission\Models\Role[] $roles
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\User permission($permissions)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\User query()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\User role($roles, $guard = null)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\User whereRateLimit($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\User whereRolesId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\User whereVerificationToken($value)
 */
class User extends Authenticatable
{
    use Notifiable, UserVerification, HasRoles, InviteTrait;

    public const ERR_SIGNUP_BADUNAME = -1;

    public const ERR_SIGNUP_BADPASS = -2;

    public const ERR_SIGNUP_BADEMAIL = -3;

    public const ERR_SIGNUP_UNAMEINUSE = -4;

    public const ERR_SIGNUP_EMAILINUSE = -5;

    public const ERR_SIGNUP_BADINVITECODE = -6;

    public const ERR_SIGNUP_BADCAPTCHA = -7;

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
    protected $table = 'users';

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

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function role()
    {
        return $this->belongsTo(Role::class, 'roles_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function request()
    {
        return $this->hasMany(UserRequest::class, 'users_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function download()
    {
        return $this->hasMany(UserDownload::class, 'users_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function release()
    {
        return $this->hasMany(UsersRelease::class, 'users_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function series()
    {
        return $this->hasMany(UserSerie::class, 'users_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function invitation()
    {
        return $this->hasMany(Invitation::class, 'users_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function failedRelease()
    {
        return $this->hasMany(DnzbFailure::class, 'users_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function comment()
    {
        return $this->hasMany(ReleaseComment::class, 'users_id');
    }

    /**
     * @throws \Exception
     */
    public static function deleteUser($id): void
    {
        self::find($id)->delete();
    }

    /**
     * @param  string  $role
     * @param  string  $username
     * @param  string  $host
     * @param  string  $email
     */
    public static function getCount($role = '', $username = '', $host = '', $email = ''): int
    {
        $res = self::query()->where('email', '<>', 'sharing@nZEDb.com');

        if ($role !== '') {
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

        return $res->count(['id']);
    }

    /**
     * @param  int  $id
     * @param  string  $userName
     * @param  string  $email
     * @param  int  $grabs
     * @param  int  $role
     * @param  string  $notes
     * @param  int  $invites
     * @param  int  $movieview
     * @param  int  $musicview
     * @param  int  $gameview
     * @param  int  $xxxview
     * @param  int  $consoleview
     * @param  int  $bookview
     * @param  string  $queueType
     * @param  string  $nzbgetURL
     * @param  string  $nzbgetUsername
     * @param  string  $nzbgetPassword
     * @param  string  $saburl
     * @param  string  $sabapikey
     * @param  string  $sabpriority
     * @param  string  $sabapikeytype
     * @param  bool  $nzbvortexServerUrl
     * @param  bool  $nzbvortexApiKey
     * @param  bool  $cp_url
     * @param  bool  $cp_api
     * @param  string  $style
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public static function updateUser($id, $userName, $email, $grabs, $role, $notes, $invites, $movieview, $musicview, $gameview, $xxxview, $consoleview, $bookview, $queueType = '', $nzbgetURL = '', $nzbgetUsername = '', $nzbgetPassword = '', $saburl = '', $sabapikey = '', $sabpriority = '', $sabapikeytype = '', $nzbvortexServerUrl = false, $nzbvortexApiKey = false, $cp_url = false, $cp_api = false, $style = 'None'): int
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
            'queuetype' => $queueType,
            'nzbgeturl' => $nzbgetURL,
            'nzbgetusername' => $nzbgetUsername,
            'nzbgetpassword' => $nzbgetPassword,
            'saburl' => $saburl,
            'sabapikey' => $sabapikey,
            'sabapikeytype' => $sabapikeytype,
            'sabpriority' => $sabpriority,
            'nzbvortex_server_url' => $nzbvortexServerUrl,
            'nzbvortex_api_key' => $nzbvortexApiKey,
            'cp_url' => $cp_url,
            'cp_api' => $cp_api,
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
     * @return \Illuminate\Database\Eloquent\Model|null|static
     */
    public static function getByUsername(string $userName)
    {
        return self::whereUsername($userName)->first();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Model|static
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public static function getByEmail(string $email)
    {
        return self::whereEmail($email)->first();
    }

    /**
     * @return bool
     */
    public static function updateUserRole(int $uid, int $role)
    {
        $roleQuery = Role::query()->where('id', $role)->first();
        $roleName = $roleQuery->name;

        $user = self::find($uid);
        $user->syncRoles([$roleName]);

        return self::find($uid)->update(['roles_id' => $role]);
    }

    /**
     * @param  int  $uid
     * @param  int  $addYear
     */
    public static function updateUserRoleChangeDate($uid, $date = '', $addYear = 0): void
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
            $days = $now->diffInDays($value);
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
     * @param  string  $userName
     * @param  string  $email
     * @param  string  $host
     * @param  string  $role
     * @param  bool  $apiRequests
     * @return \Illuminate\Database\Eloquent\Collection
     *
     * @throws \Throwable
     */
    public static function getRange($start, $offset, $orderBy, $userName = '', $email = '', $host = '', $role = '', $apiRequests = false)
    {
        if ($apiRequests) {
            UserRequest::clearApiRequests(false);
            $query = "
				SELECT users.*, roles.name AS rolename, COUNT(user_requests.id) AS apirequests
				FROM users
				INNER JOIN roles ON roles.id = users.roles_id
				LEFT JOIN user_requests ON user_requests.users_id = users.id
				WHERE users.id != 0 %s %s %s %s
				AND email != 'sharing@nZEDb.com'
				GROUP BY users.id
				ORDER BY %s %s %s ";
        } else {
            $query = '
				SELECT users.*, roles.name AS rolename
				FROM users
				INNER JOIN roles ON roles.id = users.roles_id
				WHERE 1=1 %s %s %s %s
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
        switch ($orderArr[0]) {
            case 'email':
                $orderField = 'email';
                break;
            case 'host':
                $orderField = 'host';
                break;
            case 'createdat':
                $orderField = 'created_at';
                break;
            case 'lastlogin':
                $orderField = 'lastlogin';
                break;
            case 'apiaccess':
                $orderField = 'apiaccess';
                break;
            case 'grabs':
                $orderField = 'grabs';
                break;
            case 'role':
                $orderField = 'rolename';
                break;
            case 'rolechangedate':
                $orderField = 'rolechangedate';
                break;
            case 'verification':
                $orderField = 'verified';
                break;
            default:
                $orderField = 'username';
                break;
        }
        $orderSort = (isset($orderArr[1]) && preg_match('/^asc|desc$/i', $orderArr[1])) ? $orderArr[1] : 'desc';

        return [$orderField, $orderSort];
    }

    /**
     * Verify a password against a hash.
     *
     * Automatically update the hash if it needs to be.
     *
     * @param  string  $password  Password to check against hash.
     * @param  string|bool  $hash  Hash to check against password.
     * @param  int  $userID  ID of the user.
     */
    public static function checkPassword($password, $hash, $userID = -1): bool
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
        self::find($id)->update(['password' => self::hashPassword($password), 'userseed' => md5(Str::uuid()->toString())]);

        return self::SUCCESS;
    }

    /**
     * @return mixed
     */
    public static function hashPassword($password)
    {
        return Hash::make($password);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Model|static
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public static function getByPassResetGuid(string $guid)
    {
        return self::whereResetguid($guid)->first();
    }

    /**
     * @param  int  $num
     */
    public static function incrementGrabs(int $id, $num = 1): void
    {
        self::find($id)->increment('grabs', $num);
    }

    /**
     * Check if the user is in the database, and if their API key is good, return user data if so.
     *
     *
     * @return bool|\Illuminate\Database\Eloquent\Model|null|static
     */
    public static function getByIdAndRssToken($userID, $rssToken)
    {
        $user = self::query()->where(['id' => $userID, 'api_token' => $rssToken])->first();
        if ($user === null) {
            return false;
        }

        return $user;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Model|null|static
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
     * Generate a random username.
     */
    public static function generateUsername(): string
    {
        return Str::random();
    }

    /**
     * @param  int  $length
     *
     * @throws \Exception
     */
    public static function generatePassword($length = 15): string
    {
        return Token::random($length, true);
    }

    /**
     * Register a new user.
     *
     * @param  int  $invites
     * @param  string  $inviteCode
     * @param  bool  $forceInviteMode
     * @param  int  $role
     * @param  bool  $validate
     * @return bool|int|string
     *
     * @throws \Exception
     */
    public static function signUp($userName, $password, $email, $host, $notes, $invites = Invitation::DEFAULT_INVITES, $inviteCode = '', $forceInviteMode = false, $role = self::ROLE_USER, $validate = true)
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
                $error = implode('', Arr::collapse($validator->errors()->toArray()));

                return $error;
            }
        }

        // Make sure this is the last check, as if a further validation check failed, the invite would still have been used up.
        $invitedBy = 0;
        if (! $forceInviteMode && (int) Settings::settingValue('..registerstatus') === Settings::REGISTER_STATUS_INVITE) {
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
        $invite = Invitation::getInvite($inviteCode);
        if (! $invite) {
            return -1;
        }

        self::query()->where('id', $invite['users_id'])->decrement('invites');
        Invitation::deleteInvite($inviteCode);

        return $invite['users_id'];
    }

    /**
     * Add a new user.
     *
     * @param  string  $userName
     * @param  string  $password
     * @param  string  $email
     * @param  int  $role
     * @param  string  $notes
     * @param  string  $host
     * @param  int  $invites
     * @param  int  $invitedBy
     * @return bool|int
     *
     * @throws \Exception
     */
    public static function add($userName, $password, $email, $role, $notes = '', $host = '', $invites = Invitation::DEFAULT_INVITES, $invitedBy = 0)
    {
        $password = self::hashPassword($password);
        if (! $password) {
            return false;
        }

        $storeips = (int) Settings::settingValue('..storeuserips') === 1 ? $host : '';

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
    public static function getCategoryExclusionById($userID): array
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
                    switch ($cat) {
                        case 'view console':
                            $ret[] = 1000;

                            continue 2;
                        case 'view movies':
                            $ret[] = 2000;

                            continue 2;
                        case 'view audio':
                            $ret[] = 3000;

                            continue 2;
                        case 'view pc':
                            $ret[] = 4000;

                            continue 2;
                        case 'view tv':
                            $ret[] = 5000;

                            continue 2;
                        case 'view adult':
                            $ret[] = 6000;

                            continue 2;
                        case 'view books':
                            $ret[] = 7000;

                            continue 2;
                        case 'view other':
                            $ret[] = 1;
                    }
                }
            }
        }

        $exclusion = Category::query()->whereIn('root_categories_id', $ret)->pluck('id')->toArray();

        return $exclusion;
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
     * @return \Illuminate\Database\Eloquent\Collection|\Illuminate\Support\Collection|static[]
     */
    public static function getTopGrabbers()
    {
        return self::query()->selectRaw('id, username, SUM(grabs) as grabs')->groupBy('id', 'username')->having('grabs', '>', 0)->orderBy('grabs', 'desc')->limit(10)->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection|\Illuminate\Support\Collection|static[]
     */
    public static function getUsersByMonth()
    {
        return self::query()->whereNotNull('created_at')->where('created_at', '<>', '0000-00-00 00:00:00')->selectRaw("DATE_FORMAT(created_at, '%M %Y') as mth, COUNT(id) as num")->groupBy(['mth'])->orderBy('created_at', 'desc')->get();
    }

    /**
     * @throws \Exception
     */
    public static function sendInvite($serverUrl, $uid, $emailTo): string
    {
        $user = self::find($uid);
        $token = Invite::invite($emailTo, $user->id);
        $url = $serverUrl.'/register?invitecode='.$token;

        Invitation::addInvite($uid, $token);
        SendInviteEmail::dispatch($emailTo, $user, $url)->onQueue('emails');

        return $url;
    }

    /**
     * Deletes old rows FROM the user_requests and user_downloads tables.
     * if site->userdownloadpurgedays SET to 0 then all release history is removed but
     * the download/request rows must remain for at least one day to allow the role based
     * limits to apply.
     *
     * @param  int  $days
     *
     * @throws \Exception
     */
    public static function pruneRequestHistory($days = 0): void
    {
        if ($days === 0) {
            $days = 1;
            UserDownload::query()->update(['releases_id' => null]);
        }

        UserRequest::query()->where('timestamp', '<', now()->subDays($days))->delete();
        UserDownload::query()->where('timestamp', '<', now()->subDays($days))->delete();
    }

    /**
     * Deletes users that have not verified their accounts for 3 or more days.
     */
    public static function deleteUnVerified(): void
    {
        static::whereVerified(0)->where('created_at', '<', now()->subDays(3))->delete();
    }

    public function passwordSecurity(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(PasswordSecurity::class);
    }
}
