<?php

namespace App\Models;

use App\Mail\SendInvite;
use App\Mail\AccountChange;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Blacklight\utility\Utility;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Password;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use Notifiable;

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
    protected $guarded = [];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function role()
    {
        return $this->belongsTo(UserRole::class, 'user_roles_id');
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
    public function excludedCategory()
    {
        return $this->hasMany(UserExcludedCategory::class, 'users_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function comment()
    {
        return $this->hasMany(ReleaseComment::class, 'users_id');
    }

    protected static function boot(): void
    {
        parent::boot();

        static::deleting(function (User $user) {
            $user->release()->delete();
            $user->failedRelease()->delete();
            $user->excludedCategory()->delete();
            $user->download()->delete();
            $user->request()->delete();
        });
    }

    /**
     * @return array
     */
    public static function get(): array
    {
        return self::all()->toArray();
    }

    /**
     * Get the users selected theme.
     *
     * @param string|int $userID The id of the user.
     *
     * @return array|bool The users selected theme.
     */
    public static function getStyle($userID)
    {
        $row = self::query()->where('id', $userID)->value('style');

        return $row ?? 'None';
    }

    /**
     * @param $id
     * @throws \Exception
     */
    public static function deleteUser($id): void
    {
        self::query()->where('id', $id)->delete();
    }

    /**
     * @param string $role
     * @param string $username
     * @param string $host
     * @param string $email
     * @return int
     */
    public static function getCount($role = '', $username = '', $host = '', $email = ''): int
    {
        $res = self::query()->where('email', '!=', 'sharing@nZEDb.com');

        if ($role !== '') {
            $res->where('user_roles_id', $role);
        }

        if ($username !== '') {
            $res->where('username', 'LIKE', '%'.$username.'%');
        }

        if ($host !== '') {
            $res->where('host', 'LIKE', '%'.$host.'%');
        }

        if ($email !== '') {
            $res->where('email', 'LIKE', '%'.$email.'%');
        }

        return $res->count(['id']);
    }

    /**
     * @param        $id
     * @param        $userName
     * @param        $email
     * @param        $grabs
     * @param        $role
     * @param        $notes
     * @param        $invites
     * @param        $movieview
     * @param        $musicview
     * @param        $gameview
     * @param        $xxxview
     * @param        $consoleview
     * @param        $bookview
     * @param string $queueType
     * @param string $nzbgetURL
     * @param string $nzbgetUsername
     * @param string $nzbgetPassword
     * @param string $saburl
     * @param string $sabapikey
     * @param string $sabpriority
     * @param string $sabapikeytype
     * @param bool   $nzbvortexServerUrl
     * @param bool   $nzbvortexApiKey
     * @param bool   $cp_url
     * @param bool   $cp_api
     * @param string $style
     *
     * @return int
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public static function updateUser($id, $userName, $email, $grabs, $role, $notes, $invites, $movieview, $musicview, $gameview, $xxxview, $consoleview, $bookview, $queueType = '', $nzbgetURL = '', $nzbgetUsername = '', $nzbgetPassword = '', $saburl = '', $sabapikey = '', $sabpriority = '', $sabapikeytype = '', $nzbvortexServerUrl = false, $nzbvortexApiKey = false, $cp_url = false, $cp_api = false, $style = 'None'): int
    {
        $userName = trim($userName);
        $email = trim($email);

        if (! self::isValidUsername($userName)) {
            return self::ERR_SIGNUP_BADUNAME;
        }

        if (! self::isValidEmail($email)) {
            return self::ERR_SIGNUP_BADEMAIL;
        }

        $res = self::getByUsername($userName);
        if ($res) {
            if ((int) $res['id'] !== (int) $id) {
                return self::ERR_SIGNUP_UNAMEINUSE;
            }
        }

        $res = self::getByEmail($email);
        if ($res) {
            if ((int) $res['id'] !== (int) $id) {
                return self::ERR_SIGNUP_EMAILINUSE;
            }
        }

        $sql = [
            'username' => $userName,
            'email' => $email,
            'grabs' => $grabs,
            'user_roles_id' => $role,
            'notes' => substr($notes, 0, 255),
            'invites' => $invites,
            'movieview' => $movieview,
            'musicview' => $musicview,
            'gameview' => $gameview,
            'xxxview' => $xxxview,
            'consoleview' => $consoleview,
            'bookview' => $bookview,
            'style' => $style,
            'queuetype'  => $queueType,
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
        ];

        self::query()->where('id', $id)->update($sql);

        return self::SUCCESS;
    }

    /**
     * @param string $userName
     *
     * @return int
     */
    public static function isValidUsername(string $userName): int
    {
        return preg_match('/^[a-z][a-z0-9_]{2,}$/i', $userName);
    }

    /**
     * When a user is registering or updating their profile, check if the email is valid.
     *
     * @param string $email
     *
     * @return bool
     */
    public static function isValidEmail(string $email): bool
    {
        return (bool) preg_match('/^([\w\+-]+)(\.[\w\+-]+)*@([a-z0-9-]+\.)+[a-z]{2,6}$/i', $email);
    }

    /**
     * @param string $userName
     * @return \Illuminate\Database\Eloquent\Model|null|static
     */
    public static function getByUsername(string $userName)
    {
        return self::query()->where('username', $userName)->first();
    }

    /**
     * @param string $email
     *
     * @return \Illuminate\Database\Eloquent\Model|static
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public static function getByEmail(string $email)
    {
        return self::query()->where('email', $email)->first();
    }

    /**
     * @param int $uid
     * @param int $role
     * @return int
     */
    public static function updateUserRole(int $uid, int $role): int
    {
        return self::query()->where('id', $uid)->update(['user_roles_id' => $role]);
    }

    /**
     * @param $uid
     * @param $date
     * @return int
     */
    public static function updateUserRoleChangeDate($uid, $date): int
    {
        return self::query()->where('id', $uid)->update(['rolechangedate' => $date]);
    }

    /**
     * @return int
     */
    public static function updateExpiredRoles(): int
    {
        $data = self::query()->whereDate('rolechangedate', '<', Carbon::now())->get();

        foreach ($data as $u) {
            Mail::to($u['email'])->send(new AccountChange($u['id']));
            self::query()->where('id', $u['id'])->update(['user_roles_id' => self::ROLE_USER, 'rolechangedate' => null]);
        }

        return self::SUCCESS;
    }

    /**
     * @param $start
     * @param $offset
     * @param $orderBy
     * @param string $userName
     * @param string $email
     * @param string $host
     * @param string $role
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     * @throws \Exception
     */
    public static function getRange($start, $offset, $orderBy, $userName = '', $email = '', $host = '', $role = '')
    {
        UserRequest::clearApiRequests(false);

        $order = getUserBrowseOrder($orderBy);

        $users = self::query()->with('role', 'request')->where('id', '!=', 0)->groupBy('id')->orderBy($order[0], $order[1])->withCount('request as apirequests');
        if ($userName !== '') {
            $users->where('username', 'LIKE', '%'.$userName.'%');
        }

        if ($email !== '') {
            $users->where('email', 'LIKE', '%'.$email.'%');
        }

        if ($host !== '') {
            $users->where('host', 'LIKE', '%'.$host.'%');
        }

        if ($role !== '') {
            $users->where('user_roles_id', $role);
        }

        if ($start !== false) {
            $users->limit($offset)->offset($start);
        }

        return $users->get();
    }

    /**
     * Verify a password against a hash.
     *
     * Automatically update the hash if it needs to be.
     *
     * @param string $password Password to check against hash.
     * @param string|bool $hash     Hash to check against password.
     * @param int    $userID   ID of the user.
     *
     * @return bool
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
                self::query()->where('id', $userID)->update(['password' => $hash]);
            }
        }

        return true;
    }

    /**
     * @param $uid
     *
     * @return int
     */
    public static function updateRssKey($uid): int
    {
        self::query()->where('id', $uid)->update(['rsstoken' => md5(Password::getRepository()->createNewToken())]);

        return self::SUCCESS;
    }

    /**
     * @param $id
     * @param $guid
     *
     * @return int
     */
    public static function updatePassResetGuid($id, $guid): int
    {
        self::query()->where('id', $id)->update(['resetguid' => $guid]);

        return self::SUCCESS;
    }

    /**
     * @param int    $id
     * @param string $password
     *
     * @return int
     */
    public static function updatePassword(int $id, string $password): int
    {
        self::query()->where('id', $id)->update(['password' => self::hashPassword($password), 'userseed' => md5(Utility::generateUuid())]);

        return self::SUCCESS;
    }

    /**
     * @param $password
     * @return mixed
     */
    public static function hashPassword($password)
    {
        return Hash::make($password);
    }

    /**
     * @param string $string
     *
     * @return string
     */
    public static function hashSHA1(string $string): string
    {
        return sha1($string);
    }

    /**
     * @param $guid
     *
     * @return \Illuminate\Database\Eloquent\Model|static
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public static function getByPassResetGuid(string $guid)
    {
        return self::query()->where('resetguid', $guid)->first();
    }

    /**
     * @param     $id
     * @param int $num
     */
    public static function incrementGrabs(int $id, $num = 1): void
    {
        self::query()->where('id', $id)->increment('grabs', $num);
    }

    /**
     * Check if the user is in the database, and if their API key is good, return user data if so.
     *
     * @param int    $userID   ID of the user.
     * @param string $rssToken API key.
     *
     * @return bool|array
     */
    public static function getByIdAndRssToken($userID, $rssToken)
    {
        $user = self::query()->where(['id' => $userID, 'rsstoken' => $rssToken])->first();
        if ($user === null) {
            return false;
        }

        return $user;
    }

    /**
     * @param string $rssToken
     * @return \Illuminate\Database\Eloquent\Model|null|static
     */
    public static function getByRssToken(string $rssToken)
    {
        return self::query()->where('rsstoken', $rssToken)->first();
    }

    /**
     * @param $username
     *
     * @return bool
     */
    public static function isDisabled($username): bool
    {
        return self::roleCheck(self::ROLE_DISABLED, $username);
    }

    /**
     * @param $url
     *
     * @return bool
     */
    public static function isValidUrl($url): bool
    {
        return (! preg_match('/^(http|https|ftp):\/\/([A-Z0-9][A-Z0-9_-]*(?:\.[A-Z0-9][A-Z0-9_-]*)+):?(\d+)?\/?/i', $url)) ? false : true;
    }

    /**
     * Generate a random username.
     *
     *
     * @return string
     */
    public static function generateUsername(): string
    {
        return Str::random();
    }

    /**
     * Generate a stron password.
     *
     *
     * @param int $length
     * @param bool $add_dashes
     * @param string $available_sets
     * @return bool|string
     */
    public static function generatePassword($length = 15, $add_dashes = false, $available_sets = 'luds')
    {
        $sets = [];
        if (strpos($available_sets, 'l') !== false) {
            $sets[] = 'abcdefghjkmnpqrstuvwxyz';
        }
        if (strpos($available_sets, 'u') !== false) {
            $sets[] = 'ABCDEFGHJKMNPQRSTUVWXYZ';
        }
        if (strpos($available_sets, 'd') !== false) {
            $sets[] = '23456789';
        }
        if (strpos($available_sets, 's') !== false) {
            $sets[] = '!@#$%&*?';
        }
        $all = '';
        $password = '';
        foreach ($sets as $set) {
            $password .= $set[random_int(0, \count(str_split($set)) - 1)];
            $all .= $set;
        }
        $all = str_split($all);
        for ($i = 0; $i < $length - \count($sets); $i++) {
            $password .= $all[random_int(0, \count($all) - 1)];
        }
        $password = str_shuffle($password);
        if (! $add_dashes) {
            return $password;
        }
        $dash_len = floor(sqrt($length));
        $dash_str = '';
        while (\strlen($password) > $dash_len) {
            $dash_str .= substr($password, 0, $dash_len).'-';
            $password = substr($password, $dash_len);
        }
        $dash_str .= $password;

        return $dash_str;
    }

    /**
     * Register a new user.
     *
     * @param        $userName
     * @param        $password
     * @param        $email
     * @param        $host
     * @param int    $role
     * @param        $notes
     * @param int    $invites
     * @param string $inviteCode
     * @param bool   $forceInviteMode
     *
     * @return bool|int
     * @throws \Exception
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public static function signup($userName, $password, $email, $host, $role = self::ROLE_USER, $notes, $invites = self::DEFAULT_INVITES, $inviteCode = '', $forceInviteMode = false)
    {
        $userName = trim($userName);
        $password = trim($password);
        $email = trim($email);

        if (! self::isValidUsername($userName)) {
            return self::ERR_SIGNUP_BADUNAME;
        }

        if (! self::isValidPassword($password)) {
            return self::ERR_SIGNUP_BADPASS;
        }

        if (! self::isValidEmail($email)) {
            return self::ERR_SIGNUP_BADEMAIL;
        }

        $res = self::getByUsername($userName);
        if ($res) {
            return self::ERR_SIGNUP_UNAMEINUSE;
        }

        $res = self::getByEmail($email);
        if ($res) {
            return self::ERR_SIGNUP_EMAILINUSE;
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

        return self::add($userName, $password, $email, $role, $notes, $host, $invites, $invitedBy);
    }

    /**
     * @param string $password
     * @return bool
     */
    public static function isValidPassword(string $password)
    {
        return \strlen($password) > 8 && preg_match('#[0-9]+#', $password) && preg_match('#[A-Z]+#', $password) && preg_match('#[a-z]+#', $password);
    }

    /**
     * If a invite is used, decrement the person who invited's invite count.
     *
     * @param int $inviteCode
     *
     * @return int
     */
    public static function checkAndUseInvite($inviteCode): int
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
     * @param     $userName
     * @param     $password
     * @param     $email
     * @param     $role
     * @param     $notes
     * @param     $host
     * @param int $invites
     * @param int $invitedBy
     *
     * @return bool|int
     * @throws \Exception
     */
    public static function add($userName, $password, $email, $role, $notes, $host, $invites = Invitation::DEFAULT_INVITES, $invitedBy = 0)
    {
        $password = self::hashPassword($password);
        if (! $password) {
            return false;
        }

        if (\defined('NN_INSTALLER')) {
            $storeips = '';
        } else {
            $storeips = (int) Settings::settingValue('..storeuserips') === 1 ? $host : '';
        }

        return self::create(
            [
                'username' => $userName,
                'password' => $password,
                'email' => $email,
                'user_roles_id' => $role,
                'host' => $storeips,
                'rsstoken' => md5(Password::getRepository()->createNewToken()),
                'invites' => $invites,
                'invitedby' => (int) $invitedBy === 0 ? 'NULL' : $invitedBy,
                'userseed' => md5(Utility::generateUuid()),
                'notes' => $notes,
            ]
        )->id;
    }

    /**
     * Verify if the user is logged in.
     *
     * @return bool
     * @throws \Exception
     */
    public static function isLoggedIn(): bool
    {
        if (isset($_SESSION['uid'])) {
            return true;
        }
        if (isset($_COOKIE['uid'], $_COOKIE['idh'])) {
            $u = self::find($_COOKIE['uid']);

            if ((int) $u['user_roles_id'] !== self::ROLE_DISABLED && $_COOKIE['idh'] === self::hashSHA1($u['userseed'].$_COOKIE['uid'])) {
                self::login($_COOKIE['uid'], $_SERVER['REMOTE_ADDR']);
            }
        }

        return isset($_SESSION['uid']);
    }

    /**
     * Log in a user.
     *
     * @param int    $userID   ID of the user.
     * @param string $host
     * @param bool $remember Save the user in cookies to keep them logged in.
     *
     * @throws \Exception
     */
    public static function login($userID, $host = '', $remember = false): void
    {
        $_SESSION['uid'] = $userID;

        if ((int) Settings::settingValue('..storeuserips') !== 1) {
            $host = '';
        }

        self::updateSiteAccessed($userID, $host);

        if ($remember === true) {
            self::setCookies($userID);
        }
    }

    /**
     * When a user logs in, update the last time they logged in.
     *
     * @param int    $userID ID of the user.
     * @param string $host
     */
    public static function updateSiteAccessed($userID, $host = ''): void
    {
        self::query()->where('id', $userID)->update(
            [
                'lastlogin' => Carbon::now(),
                'host' => $host,
            ]
        );
    }

    /**
     * Set up cookies for a user.
     *
     * @param int $userID
     */
    public static function setCookies($userID): void
    {
        $user = self::find($userID);
        $secure_cookie = request()->secure();
        setcookie('uid', $userID, time() + 2592000, '/', null, $secure_cookie, true);
        setcookie('idh', self::hashSHA1($user['userseed'].$userID), time() + 2592000, '/', null, $secure_cookie, true);
    }

    /**
     * Return the User ID of the user.
     *
     * @return int
     */
    public static function currentUserId(): int
    {
        return $_SESSION['uid'] ?? -1;
    }

    /**
     * Logout the user, destroying his cookies and session.
     */
    public static function logout(): void
    {
        session_unset();
        session_destroy();
        $secure_cookie = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? '1' : '0');
        setcookie('uid', null, -1, '/', null, $secure_cookie, true);
        setcookie('idh', null, -1, '/', null, $secure_cookie, true);
    }

    /**
     * @param $uid
     */
    public static function updateApiAccessed($uid): void
    {
        self::query()->where('id', $uid)->update(['apiaccess' => date('Y-m-d h:m:s')]);
    }

    /**
     * Get the list of categories the user has excluded.
     *
     * @param int $userID ID of the user.
     *
     * @return array
     */
    public static function getCategoryExclusion($userID): array
    {
        $ret = [];
        $categories = self::query()->where('id', $userID)->first();
        if ($categories !== null) {
            foreach ($categories->excludedCategory as $category) {
                $ret[] = $category['categories_id'];
            }
        }

        return $ret;
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
        return self::query()->whereNotNull('created_at')->where('created_at', '!=', '0000-00-00 00:00:00')->selectRaw("DATE_FORMAT(created_at, '%M %Y') as mth, COUNT(id) as num")->groupBy(['mth'])->orderBy('created_at', 'desc')->get();
    }

    /**
     * @param        $host
     * @param string|null $siteseed
     *
     * @return string
     * @throws \Exception
     */
    public static function getHostHash($host, string $siteseed = ''): string
    {
        if ($siteseed === '') {
            $siteseed = Settings::settingValue('..siteseed');
        }

        return self::hashSHA1($siteseed.$host.$siteseed);
    }

    /**
     * Checks if a user is a specific role.
     *
     * @notes Uses type of $user to denote identifier. if string: username, if int: users_id
     * @param int $roleID
     * @param string|int $user
     * @return bool
     */
    public static function roleCheck($roleID, $user): bool
    {
        $result = self::query()->where('username', $user)->orWhere('id', $user)->first(['user_roles_id']);

        if ($result !== null) {
            return $result['user_roles_id'] === $roleID;
        }

        return false;
    }

    /**
     * Wrapper for roleCheck specifically for Admins.
     *
     * @param int $userID
     * @return bool
     */
    public static function isAdmin($userID): bool
    {
        return self::roleCheck(self::ROLE_ADMIN, (int) $userID);
    }

    /**
     * Wrapper for roleCheck specifically for Moderators.
     *
     * @param int $userId
     * @return bool
     */
    public static function isModerator($userId): bool
    {
        return self::roleCheck(self::ROLE_MODERATOR, (int) $userId);
    }

    /**
     * @param $serverUrl
     * @param $uid
     * @param $emailTo
     * @return string
     */
    public static function sendInvite($serverUrl, $uid, $emailTo): string
    {
        $token = self::hashSHA1(uniqid('', true));
        $url = $serverUrl.'register?invitecode='.$token;

        Mail::to($emailTo)->send(new SendInvite($uid, $url));
        Invitation::addInvite($uid, $token);

        return $url;
    }

    /**
     * deletes old rows FROM the user_requests and user_downloads tables.
     * if site->userdownloadpurgedays SET to 0 then all release history is removed but
     * the download/request rows must remain for at least one day to allow the role based
     * limits to apply.
     *
     * @param int $days
     */
    public static function pruneRequestHistory($days = 0): void
    {
        if ($days === 0) {
            $days = 1;
            UserDownload::query()->update(['releases_id' => null]);
        }

        UserRequest::query()->where('timestamp', '<', Carbon::now()->subDays($days))->delete();
        UserDownload::query()->where('timestamp', '<', Carbon::now()->subDays($days))->delete();
    }
}
