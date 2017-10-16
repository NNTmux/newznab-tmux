<?php

namespace nntmux;

use App\Models\UserExcludedCategory;
use nntmux\db\DB;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Release;
use App\Mail\SendInvite;
use App\Models\Settings;
use App\Models\UserRole;
use App\Models\Invitation;
use App\Mail\AccountChange;
use App\Models\UserRequest;
use nntmux\utility\Utility;
use App\Models\UserDownload;
use App\Models\UsersRelease;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;

class Users
{
    const ERR_SIGNUP_BADUNAME = -1;
    const ERR_SIGNUP_BADPASS = -2;
    const ERR_SIGNUP_BADEMAIL = -3;
    const ERR_SIGNUP_UNAMEINUSE = -4;
    const ERR_SIGNUP_EMAILINUSE = -5;
    const ERR_SIGNUP_BADINVITECODE = -6;
    const ERR_SIGNUP_BADCAPTCHA = -7;
    const SUCCESS = 1;

    const ROLE_GUEST = 0;
    const ROLE_USER = 1;
    const ROLE_ADMIN = 2;
    const ROLE_DISABLED = 3;
    const ROLE_MODERATOR = 4;

    const DEFAULT_INVITES = 1;
    const DEFAULT_INVITE_EXPIRY_DAYS = 7;

    const SALTLEN = 4;
    const SHA1LEN = 40;

    /**
     * @var int
     */
    public $password_hash_cost;

    /**
     * Users SELECT queue type.
     */
    const QUEUE_NONE = 0;
    const QUEUE_SABNZBD = 1;
    const QUEUE_NZBGET = 2;

    /**
     * @var DB
     */
    private $pdo;

    /**
     * @var \Carbon\Carbon
     */
    private $carbon;

    /**
     * @param array $options Class instances.
     * @throws \Exception
     */
    public function __construct(array $options = [])
    {
        $defaults = [
            'Settings' => null,
        ];
        $options += $defaults;

        $this->pdo = $options['Settings'] instanceof DB ? $options['Settings'] : new DB();

        $this->password_hash_cost = defined('NN_PASSWORD_HASH_COST') ? NN_PASSWORD_HASH_COST : 11;

        $this->carbon = new Carbon();
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
    public function checkPassword($password, $hash, $userID = -1): bool
    {
        if (Hash::check($password, $hash) === false) {
            return false;
        }

        // Update the hash if it needs to be.
        if (is_numeric($userID) && $userID > 0 && Hash::needsRehash($hash)) {
            $hash = $this->hashPassword($password);

            if ($hash !== false) {
                User::query()->where('id', $userID)->update(['password' => $hash]);
            }
        }

        return true;
    }

    /**
     * @return array
     */
    public function get(): array
    {
        return User::all()->all();
    }

    /**
     * Get the users selected theme.
     *
     * @param string|int $userID The id of the user.
     *
     * @return array|bool The users selected theme.
     */
    public function getStyle($userID)
    {
        $row = User::query()->where('id', $userID)->value('style');

        return $row ?? 'None';
    }

    /**
     * @param $id
     */
    public function delete($id): void
    {
        $this->delCartForUser($id);
        $this->delUserCategoryExclusions($id);
        $this->delDownloadRequests($id);
        $this->delApiRequests($id);

        $rc = new ReleaseComments();
        $rc->deleteCommentsForUser($id);

        $um = new UserMovies();
        $um->delMovieForUser($id);

        $us = new UserSeries();
        $us->delShowForUser($id);

        $forum = new Forum();
        $forum->deleteUser($id);

        User::query()->where('id', $id)->delete();
    }

    /**
     * @param $uid
     */
    public function delCartForUser($uid): void
    {
        UsersRelease::query()->where('users_id', $uid)->delete();
    }

    /**
     * @param $uid
     */
    public function delUserCategoryExclusions($uid): void
    {
        UserExcludedCategory::query()->where('users_id', $uid)->delete();
    }

    /**
     * @param $userID
     */
    public function delDownloadRequests($userID): void
    {
        UserDownload::query()->where('users_id', $userID)->delete();
    }

    /**
     * @param $userID
     */
    public function delApiRequests($userID): void
    {
        UserRequest::query()->where('users_id', $userID)->delete();
    }

    /**
     * Get all users / extra data from other tables.
     *
     * @param        $start
     * @param        $offset
     * @param        $orderBy
     * @param string $userName
     * @param string $email
     * @param string $host
     * @param string $role
     * @param bool   $apiRequests
     *
     * @return array
     * @throws \Exception
     */
    public function getRange($start, $offset, $orderBy, $userName = '', $email = '', $host = '', $role = '', $apiRequests = false): array
    {
        if ($apiRequests) {
            $this->clearApiRequests(false);
            $query = "
				SELECT users.*, user_roles.name AS rolename, COUNT(user_requests.id) AS apirequests
				FROM users
				INNER JOIN user_roles ON user_roles.id = users.user_roles_id
				LEFT JOIN user_requests ON user_requests.users_id = users.id
				WHERE users.id != 0 %s %s %s %s
				AND email != 'sharing@nZEDb.com'
				GROUP BY users.id
				ORDER BY %s %s %s";
        } else {
            $query = '
				SELECT users.*, user_roles.name AS rolename
				FROM users
				INNER JOIN user_roles ON user_roles.id = users.user_roles_id
				WHERE 1=1 %s %s %s %s
				ORDER BY %s %s %s';
        }

        $order = $this->getBrowseOrder($orderBy);

        return $this->pdo->query(
            sprintf(
                $query,
                ($userName !== '' ? ('AND users.username '.$this->pdo->likeString($userName)) : ''),
                ($email !== '' ? ('AND users.email '.$this->pdo->likeString($email)) : ''),
                ($host !== '' ? ('AND users.host '.$this->pdo->likeString($host)) : ''),
                ($role !== '' ? ('AND users.user_roles_id = '.$role) : ''),
                $order[0],
                $order[1],
                ($start === false ? '' : ('LIMIT '.$offset.' OFFSET '.$start))
            )
        );
    }

    /**
     * @param string $orderBy
     *
     * @return array
     */
    public function getBrowseOrder($orderBy): array
    {
        $order = ($orderBy === '' ? 'username_desc' : $orderBy);
        $orderArr = explode('_', $order);
        switch ($orderArr[0]) {
            case 'username':
                $orderField = 'username';
                break;
            case 'email':
                $orderField = 'email';
                break;
            case 'host':
                $orderField = 'host';
                break;
            case 'created_at':
                $orderField = 'created_at';
                break;
            case 'lastlogin':
                $orderField = 'lastlogin';
                break;
            case 'apiaccess':
                $orderField = 'apiaccess';
                break;
            case 'apirequests':
                $orderField = 'apirequests';
                break;
            case 'grabs':
                $orderField = 'grabs';
                break;
            case 'user_roles_id':
                $orderField = 'users_role_id';
                break;
            case 'rolechangedate':
                $orderField = 'rolechangedate';
                break;
            default:
                $orderField = 'username';
                break;
        }
        $orderSort = (isset($orderArr[1]) && preg_match('/^asc|desc$/i', $orderArr[1])) ? $orderArr[1] : 'desc';

        return [$orderField, $orderSort];
    }

    /**
     * @param string|int $role
     *
     * @return mixed
     */
    public function getCount($role = '')
    {
        $res = $this->pdo->queryOneRow(sprintf("SELECT COUNT(id) as num FROM users WHERE email != 'sharing@nZEDb.com' %s", $role !== '' ? sprintf('AND user_roles_id = %d', $role) : ''));

        return $res['num'];
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
    public function update($id, $userName, $email, $grabs, $role, $notes, $invites, $movieview, $musicview, $gameview, $xxxview, $consoleview, $bookview, $queueType = '', $nzbgetURL = '', $nzbgetUsername = '', $nzbgetPassword = '', $saburl = '', $sabapikey = '', $sabpriority = '', $sabapikeytype = '', $nzbvortexServerUrl = false, $nzbvortexApiKey = false, $cp_url = false, $cp_api = false, $style = 'None'): int
    {
        $userName = trim($userName);
        $email = trim($email);

        if (! $this->isValidUsername($userName)) {
            return self::ERR_SIGNUP_BADUNAME;
        }

        if (! $this->isValidEmail($email)) {
            return self::ERR_SIGNUP_BADEMAIL;
        }

        $res = $this->getByUsername($userName);
        if ($res) {
            if ((int) $res['id'] !== (int) $id) {
                return self::ERR_SIGNUP_UNAMEINUSE;
            }
        }

        $res = $this->getByEmail($email);
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

        User::query()->where('id', $id)->update($sql);

        return self::SUCCESS;
    }

    /**
     * @param string $userName
     *
     * @return int
     */
    public function isValidUsername(string $userName): int
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
    public function isValidEmail(string $email): bool
    {
        return (bool) preg_match('/^([\w\+-]+)(\.[\w\+-]+)*@([a-z0-9-]+\.)+[a-z]{2,6}$/i', $email);
    }

    /**
     * @param string $userName
     * @return \Illuminate\Database\Eloquent\Model|null|static
     */
    public function getByUsername(string $userName)
    {
        return User::query()->where('username', $userName)->first();
    }

    /**
     * @param string $email
     *
     * @return \Illuminate\Database\Eloquent\Model|static
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function getByEmail(string $email)
    {
        return User::query()->where('email', $email)->first();
    }

    /**
     * @param int $uid
     * @param int $role
     *
     * @return int
     */
    public function updateUserRole(int $uid, int $role): int
    {
        User::query()->where('id', $uid)->update(['user_roles_id' => $role]);

        return self::SUCCESS;
    }

    /**
     * @param $uid
     * @param $date
     *
     * @return int
     */
    public function updateUserRoleChangeDate($uid, $date): int
    {
        User::query()->where('id', $uid)->update(['rolechangedate' => $date]);

        return self::SUCCESS;
    }

    /**
     * @return int
     */
    public function updateExpiredRoles(): int
    {
        $data = User::query()->whereDate('rolechangedate', '<', Carbon::now())->get();

        foreach ($data as $u) {
            Mail::to($u['email'])->send(new AccountChange($u['id']));
            User::query()->where('id', $u['id'])->update(['user_roles_id' => self::ROLE_USER, 'rolechangedate' => null]);
        }

        return self::SUCCESS;
    }

    /**
     * @param $uid
     *
     * @return int
     */
    public function updateRssKey($uid): int
    {
        User::query()->where('id', $uid)->update(['rsstoken' => md5(Password::getRepository()->createNewToken())]);

        return self::SUCCESS;
    }

    /**
     * @param $id
     * @param $guid
     *
     * @return int
     */
    public function updatePassResetGuid($id, $guid): int
    {
        User::query()->where('id', $id)->update(['resetguid' => $guid]);

        return self::SUCCESS;
    }

    /**
     * @param int    $id
     * @param string $password
     *
     * @return int
     */
    public function updatePassword(int $id, string $password): int
    {
        User::query()->where('id', $id)->update(['password' => $this->hashPassword($password), 'userseed' => md5(Utility::generateUuid())]);

        return self::SUCCESS;
    }

    /**
     * Hash a password using crypt.
     *
     * @param string $password
     *
     * @return string|bool
     */
    public function hashPassword($password)
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
    public function getByPassResetGuid(string $guid)
    {
        return User::query()->where('resetguid', $guid)->first();
    }

    /**
     * @param     $id
     * @param int $num
     */
    public function incrementGrabs(int $id, $num = 1): void
    {
        User::query()->where('id', $id)->increment('grabs', $num);
    }

    /**
     * Check if the user is in the database, and if their API key is good, return user data if so.
     *
     * @param int    $userID   ID of the user.
     * @param string $rssToken API key.
     *
     * @return bool|array
     */
    public function getByIdAndRssToken($userID, $rssToken)
    {
        $user = $this->getById($userID);
        if ($user === false) {
            return false;
        }

        return $user->rsstoken !== $rssToken ? false : $user;
    }

    /**
     * @param $id
     *
     * @return array|bool
     */
    public function getById($id)
    {
        $result = User::find($id);

        if (empty($result)) {
            return false;
        }

        return $result;
    }

    /**
     * @param string $rssToken
     * @return \Illuminate\Database\Eloquent\Model|null|static
     */
    public function getByRssToken(string $rssToken)
    {
        return User::query()->where('rsstoken', $rssToken)->first();
    }

    /**
     * @return array
     */
    public function getBrowseOrdering(): array
    {
        return ['username_asc', 'username_desc', 'email_asc', 'email_desc', 'host_asc', 'host_desc', 'createdat_asc', 'createdat_desc', 'lastlogin_asc', 'lastlogin_desc', 'apiaccess_asc', 'apiaccess_desc', 'apirequests_asc', 'apirequests_desc', 'grabs_asc', 'grabs_desc', 'role_asc', 'role_desc', 'rolechangedate_asc', 'rolechangedate_desc'];
    }

    /**
     * @param $username
     *
     * @return bool
     */
    public function isDisabled($username): bool
    {
        return $this->roleCheck(self::ROLE_DISABLED, $username);
    }

    /**
     * @param $url
     *
     * @return bool
     */
    public function isValidUrl($url): bool
    {
        return (! preg_match('/^(http|https|ftp):\/\/([A-Z0-9][A-Z0-9_-]*(?:\.[A-Z0-9][A-Z0-9_-]*)+):?(\d+)?\/?/i', $url)) ? false : true;
    }

    /**
     * Create a random username.
     *
     * @param string $email
     *
     * @return string
     */
    public function generateUsername($email): string
    {
        $string = '';
        if (preg_match('/[A-Za-z0-9]+/', $email, $matches)) {
            $string = $matches[0];
        }

        return 'u'.substr(md5(uniqid('', true)), 0, 7).$string;
    }

    /**
     * @return string
     */
    public function generatePassword(): string
    {
        return str_random(8);
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
    public function signup($userName, $password, $email, $host, $role = self::ROLE_USER, $notes, $invites = self::DEFAULT_INVITES, $inviteCode = '', $forceInviteMode = false)
    {
        $userName = trim($userName);
        $password = trim($password);
        $email = trim($email);

        if (! $this->isValidUsername($userName)) {
            return self::ERR_SIGNUP_BADUNAME;
        }

        if (! $this->isValidPassword($password)) {
            return self::ERR_SIGNUP_BADPASS;
        }

        if (! $this->isValidEmail($email)) {
            return self::ERR_SIGNUP_BADEMAIL;
        }

        $res = $this->getByUsername($userName);
        if ($res) {
            return self::ERR_SIGNUP_UNAMEINUSE;
        }

        $res = $this->getByEmail($email);
        if ($res) {
            return self::ERR_SIGNUP_EMAILINUSE;
        }

        // Make sure this is the last check, as if a further validation check failed, the invite would still have been used up.
        $invitedBy = 0;
        if (! $forceInviteMode && (int) Settings::settingValue('..registerstatus') === Settings::REGISTER_STATUS_INVITE) {
            if ($inviteCode === '') {
                return self::ERR_SIGNUP_BADINVITECODE;
            }

            $invitedBy = $this->checkAndUseInvite($inviteCode);
            if ($invitedBy < 0) {
                return self::ERR_SIGNUP_BADINVITECODE;
            }
        }

        return $this->add($userName, $password, $email, $role, $notes, $host, $invites, $invitedBy);
    }

    /**
     * @param $password
     *
     * @return bool
     */
    public function isValidPassword(string $password): bool
    {
        return strlen($password) > 5;
    }

    /**
     * If a invite is used, decrement the person who invited's invite count.
     *
     * @param int $inviteCode
     *
     * @return int
     */
    public function checkAndUseInvite($inviteCode): int
    {
        $invite = $this->getInvite($inviteCode);
        if (! $invite) {
            return -1;
        }

        User::query()->where('id', $invite['users_id'])->decrement('invites');
        $this->deleteInvite($inviteCode);

        return $invite['users_id'];
    }

    /**
     * @param $inviteToken
     * @return \Illuminate\Database\Eloquent\Model|null|static
     */
    public function getInvite($inviteToken)
    {
        //
        // Tidy any old invites sent greater than DEFAULT_INVITE_EXPIRY_DAYS days ago.
        //
        Invitation::query()->where('created_at', '<', Carbon::now()->subDays(self::DEFAULT_INVITE_EXPIRY_DAYS));

        return Invitation::query()->where('guid', $inviteToken)->first();
    }

    /**
     * @param $inviteToken
     */
    public function deleteInvite(string $inviteToken): void
    {
        Invitation::query()->where('guid', $inviteToken)->delete();
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
     */
    public function add($userName, $password, $email, $role, $notes, $host, $invites = self::DEFAULT_INVITES, $invitedBy = 0)
    {
        $password = $this->hashPassword($password);
        if (! $password) {
            return false;
        }

        return User::query()->insertGetId(
            [
                'username' => $userName,
                'password' => $password,
                'email' => $email,
                'user_roles_id' => $role,
                'created_at' => Carbon::now(),
                'host' => (int) Settings::settingValue('..storeuserips') === 1 ? $host : '',
                'rsstoken' => md5(Password::getRepository()->createNewToken()),
                'invites' => $invites,
                'invitedby' => (int) $invitedBy === 0 ? 'NULL' : $invitedBy,
                'userseed' => md5(Utility::generateUuid()),
                'notes' => $notes,
            ]
        );
    }

    /**
     * Verify if the user is logged in.
     *
     * @return bool
     * @throws \Exception
     */
    public function isLoggedIn(): bool
    {
        if (isset($_SESSION['uid'])) {
            return true;
        }
        if (isset($_COOKIE['uid'], $_COOKIE['idh'])) {
            $u = $this->getById($_COOKIE['uid']);

            if ((int) $u['user_roles_id'] !== self::ROLE_DISABLED && $_COOKIE['idh'] === self::hashSHA1($u['userseed'].$_COOKIE['uid'])) {
                $this->login($_COOKIE['uid'], $_SERVER['REMOTE_ADDR']);
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
    public function login($userID, $host = '', $remember = false): void
    {
        $_SESSION['uid'] = $userID;

        if ((int) Settings::settingValue('..storeuserips') !== 1) {
            $host = '';
        }

        $this->updateSiteAccessed($userID, $host);

        if ($remember === true) {
            $this->setCookies($userID);
        }
    }

    /**
     * When a user logs in, update the last time they logged in.
     *
     * @param int    $userID ID of the user.
     * @param string $host
     */
    public function updateSiteAccessed($userID, $host = ''): void
    {
        $this->pdo->queryExec(
            sprintf(
                'UPDATE users SET lastlogin = NOW() %s WHERE id = %d',
                ($host === '' ? '' : (', host = '.$this->pdo->escapeString($host))),
                $userID
            )
        );
    }

    /**
     * Set up cookies for a user.
     *
     * @param int $userID
     */
    public function setCookies($userID): void
    {
        $user = $this->getById($userID);
        $secure_cookie = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? '1' : '0');
        setcookie('uid', $userID, time() + 2592000, '/', null, $secure_cookie, true);
        setcookie('idh', self::hashSHA1($user['userseed'].$userID), time() + 2592000, '/', null, $secure_cookie, true);
    }

    /**
     * Return the User ID of the user.
     *
     * @return int
     */
    public function currentUserId(): int
    {
        return $_SESSION['uid'] ?? -1;
    }

    /**
     * Logout the user, destroying his cookies and session.
     */
    public function logout(): void
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
    public function updateApiAccessed($uid): void
    {
        User::query()->where('id', $uid)->update(['apiaccess' => date('Y-m-d h:m:s')]);
    }

    /**
     * @param $uid
     * @param $releaseid
     * @return int
     */
    public function addCart($uid, $releaseid): int
    {
        return UsersRelease::query()->insertGetId(
            [
                'users_id' => $uid,
                'releases_id' => $releaseid,
                'created_at' => Carbon::now(),
            ]
        );
    }

    /**
     * @param $uid
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function getCart($uid)
    {
        return UsersRelease::query()->with('release')->where(['users_id' => $uid])->get();
    }

    /**
     * @param $guids
     * @param $userID
     * @return bool|mixed
     */
    public function delCartByGuid($guids, $userID)
    {
        if (! is_array($guids)) {
            return false;
        }

        $del = [];
        foreach ($guids as $guid) {
            $rel = Release::query()->where('guid', $guid)->first(['id']);
            if ($rel !== null) {
                $del[] = $rel['id'];
            }
        }

        return UsersRelease::query()->whereIn('releases_id', $del)->where('users_id', $userID)->delete();
    }

    /**
     * @param $guid
     * @param $uid
     */
    public function delCartByUserAndRelease($guid, $uid): void
    {
        $rel = Release::query()->where('guid', $guid)->first(['id']);
        if ($rel) {
            UsersRelease::query()->where(['users_id' => $uid, 'releases_id' => $rel['id']])->delete();
        }
    }

    /**
     * @param $rid
     */
    public function delCartForRelease($rid): void
    {
        UsersRelease::query()->where('releases_id', $rid)->delete();
    }

    /**
     * @param       $uid
     * @param array $catids
     */
    public function addCategoryExclusions($uid, array $catids): void
    {
        $this->delUserCategoryExclusions($uid);
        if (count($catids) > 0) {
            foreach ($catids as $catid) {
                UserExcludedCategory::query()->insertGetId(['users_id' => $uid, 'categories_id' => $catid, 'created_at' => Carbon::now()]);
            }
        }
    }

    /**
     * @param $role
     *
     * @return array
     */
    public function getRoleCategoryExclusion($role): array
    {
        $ret = [];
        $categories = UserExcludedCategory::query()->where('role', $role)->get(['categories_id']);
        foreach ($categories as $category) {
            $ret[] = $category['categories_id'];
        }

        return $ret;
    }

    /**
     * @param $role
     * @param $catids
     */
    public function addRoleCategoryExclusions($role, array $catids): void
    {
        $this->delRoleCategoryExclusions($role);
        if (count($catids) > 0) {
            foreach ($catids as $catid) {
                $this->pdo->queryInsert(sprintf('INSERT INTO role_excluded_categories (role, categories_id, created_at) VALUES (%d, %d, now())', $role, $catid));
            }
        }
    }

    /**
     * @param $role
     */
    public function delRoleCategoryExclusions($role): void
    {
        $this->pdo->queryExec(sprintf('DELETE FROM role_excluded_categories WHERE role = %d', $role));
    }

    /**
     * Get the list of categories the user has excluded.
     *
     * @param int $userID ID of the user.
     *
     * @return array
     */
    public function getCategoryExclusion($userID): array
    {
        $ret = [];
        $categories = $this->pdo->query(sprintf('SELECT categories_id FROM user_excluded_categories WHERE users_id = %d', $userID));
        foreach ($categories as $category) {
            $ret[] = $category['categories_id'];
        }

        return $ret;
    }

    /**
     * Get list of category names excluded by the user.
     *
     * @param int $userID ID of the user.
     *
     * @return array
     */
    public function getCategoryExclusionNames($userID): array
    {
        $data = $this->getCategoryExclusion($userID);
        $category = new Category(['Settings' => $this->pdo]);
        $categories = $category->getByIds($data);
        $ret = [];
        if ($categories !== false) {
            foreach ($categories as $cat) {
                $ret[] = $cat['title'];
            }
        }

        return $ret;
    }

    /**
     * @param $uid
     * @param $catid
     */
    public function delCategoryExclusion($uid, $catid): void
    {
        UserExcludedCategory::query()->where(['users_id'=> $uid, 'categories_id' => $catid])->delete();
    }

    /**
     * @param $serverUrl
     * @param $uid
     * @param $emailTo
     * @return string
     */
    public function sendInvite($serverUrl, $uid, $emailTo): string
    {
        $token = self::hashSHA1(uniqid('', true));
        $url = $serverUrl.'register?invitecode='.$token;

        Mail::to($emailTo)->send(new SendInvite($uid, $url));
        $this->addInvite($uid, $token);

        return $url;
    }

    /**
     * @param int $uid
     * @param string $inviteToken
     */
    public function addInvite(int $uid, string $inviteToken)
    {
        Invitation::query()->insertGetId(['guid' => $inviteToken, 'users_id' => $uid, 'created_at' => Carbon::now()]);
    }

    /**
     * @return array
     */
    public function getTopGrabbers(): array
    {
        return $this->pdo->query(
            'SELECT id, username, SUM(grabs) as grabs FROM users
							GROUP BY id, username
							HAVING SUM(grabs) > 0
							ORDER BY grabs DESC
							LIMIT 10'
        );
    }

    /**
     * Get list of user signups by month.
     *
     * @return array
     */
    public function getUsersByMonth(): array
    {
        return $this->pdo->query(
            "
			SELECT DATE_FORMAT(created_at, '%M %Y') AS mth, COUNT(id) AS num
			FROM users
			WHERE created_at IS NOT NULL AND created_at != '0000-00-00 00:00:00'
			GROUP BY mth
			ORDER BY created_at DESC"
        );
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function getUsersByHostHash(): array
    {
        $ipsql = "('-1')";

        if (Settings::settingValue('..userhostexclusion') !== '') {
            $ipsql = '';
            $ips = explode(',', Settings::settingValue('..userhostexclusion'));
            foreach ($ips as $ip) {
                $ipsql .= $this->pdo->escapeString($this->getHostHash($ip, Settings::settingValue('..siteseed'))).',';
            }
            $ipsql = '('.$ipsql." '-1')";
        }

        $sql = sprintf(
            "SELECT hosthash, group_concat(users_id) AS user_string, group_concat(username) AS user_names
							FROM
							(
							SELECT hosthash, users_id, username FROM user_downloads LEFT OUTER JOIN users ON users.id = user_downloads.users_id WHERE hosthash IS NOT NULL AND hosthash NOT IN %s GROUP BY hosthash, users_id
							union distinct
							SELECT hosthash, users_id, username FROM user_requests LEFT OUTER JOIN users on users.id = user_requests.users_id WHERE hosthash IS NOT NULL AND hosthash NOT IN %s GROUP BY hosthash, users_id
							) x
							GROUP BY hosthash
							HAVING CAST((LENGTH(group_concat(users_id)) - LENGTH(REPLACE(group_concat(users_id), ',', ''))) / LENGTH(',') AS UNSIGNED) < 9
							ORDER BY CAST((LENGTH(group_concat(users_id)) - LENGTH(REPLACE(group_concat(users_id), ',', ''))) / LENGTH(',') AS UNSIGNED) DESC
							limit 10",
            $ipsql,
            $ipsql
        );

        return $this->pdo->query($sql);
    }

    /**
     * @param        $host
     * @param string|null $siteseed
     *
     * @return string
     * @throws \Exception
     */
    public function getHostHash($host, string $siteseed = ''): string
    {
        if ($siteseed === '') {
            $siteseed = Settings::settingValue('..siteseed');
        }

        return self::hashSHA1($siteseed.$host.$siteseed);
    }

    /**
     * @return array
     */
    public function getUsersByRole(): array
    {
        return $this->pdo->query(
            'SELECT ur.name, COUNT(u.id) as num FROM users u
							INNER JOIN user_roles ur ON ur.id = u.user_roles_id
							GROUP BY ur.name
							ORDER BY COUNT(u.id) DESC'
        );
    }

    /**
     * @return array
     */
    public function getLoginCountsByMonth(): array
    {
        return $this->pdo->query(
            "SELECT 'Login' as type,
			sum(case when lastlogin > curdate() - INTERVAL 1 DAY then 1 else 0 end) as 1day,
			sum(case when lastlogin > curdate() - INTERVAL 7 DAY AND lastlogin < curdate() - INTERVAL 1 DAY then 1 else 0 end) as 7day,
			sum(case when lastlogin > curdate() - INTERVAL 1 MONTH AND lastlogin < curdate() - INTERVAL 7 DAY then 1 else 0 end) as 1month,
			sum(case when lastlogin > curdate() - INTERVAL 3 MONTH AND lastlogin < curdate() - INTERVAL 1 MONTH then 1 else 0 end) as 3month,
			sum(case when lastlogin > curdate() - INTERVAL 6 MONTH AND lastlogin < curdate() - INTERVAL 3 MONTH then 1 else 0 end) as 6month,
			sum(case when lastlogin < curdate() - INTERVAL 6 MONTH then 1 else 0 end) as 12month
			FROM users
			union
			SELECT 'Api' as type,
			sum(case when apiaccess > curdate() - INTERVAL 1 DAY then 1 else 0 end) as 1day,
			sum(case when apiaccess > curdate() - INTERVAL 7 DAY AND apiaccess < curdate() - INTERVAL 1 DAY then 1 else 0 end) as 7day,
			sum(case when apiaccess > curdate() - INTERVAL 1 MONTH AND apiaccess < curdate() - INTERVAL 7 DAY then 1 else 0 end) as 1month,
			sum(case when apiaccess > curdate() - INTERVAL 3 MONTH AND apiaccess < curdate() - INTERVAL 1 MONTH then 1 else 0 end) as 3month,
			sum(case when apiaccess > curdate() - INTERVAL 6 MONTH AND apiaccess < curdate() - INTERVAL 3 MONTH then 1 else 0 end) as 6month,
			sum(case when apiaccess < curdate() - INTERVAL 6 MONTH then 1 else 0 end) as 12month
			FROM users"
        );
    }

    /**
     * @return array
     */
    public function getRoles(): array
    {
        return UserRole::all()->toArray();
    }

    /**
     * @param $id
     *
     * @return \Illuminate\Database\Eloquent\Model|null|static
     */
    public function getRoleById($id)
    {
        return UserRole::query()->where('id', $id)->first();
    }

    /**
     * @param $name
     * @param $apirequests
     * @param $downloadrequests
     * @param $defaultinvites
     * @param $canpreview
     * @param $hideads
     *
     * @return false|int|string
     */
    public function addRole($name, $apirequests, $downloadrequests, $defaultinvites, $canpreview, $hideads)
    {
        return UserRole::query()->insertGetId(
            [
                'name' => $name,
                'apirequests' => $apirequests,
                'downloadrequests' => $downloadrequests,
                'defaultinvites' => $defaultinvites,
                'canpreview' => $canpreview,
                'hideads' => $hideads,
            ]
        );
    }

    /**
     * @param $id
     * @param $name
     * @param $apirequests
     * @param $downloadrequests
     * @param $defaultinvites
     * @param $isdefault
     * @param $canpreview
     * @param $hideads
     *
     * @return int
     */
    public function updateRole($id, $name, $apirequests, $downloadrequests, $defaultinvites, $isdefault, $canpreview, $hideads)
    {
        if ((int) $isdefault === 1) {
            UserRole::query()->update(['isdefault' => 0]);
        }

        return UserRole::query()->where('id', $id)->update(
            [
                'name' => $name,
                'apirequests' => $apirequests,
                'downloadrequests' => $downloadrequests,
                'defaultinvites' => $defaultinvites,
                'isdefault' => $isdefault,
                'canpreview' => $canpreview,
                'hideads' => $hideads,
            ]
        );
    }

    /**
     * @param $id
     *
     * @return bool|\PDOStatement
     */
    public function deleteRole($id)
    {
        $res = $this->pdo->query(sprintf('SELECT id FROM users WHERE role = %d', $id));
        if (count($res) > 0) {
            $userids = [];
            foreach ($res as $user) {
                $userids[] = $user['id'];
            }
            $defaultrole = $this->getDefaultRole();
            $this->pdo->queryExec(sprintf('UPDATE users SET user_roles_id = %d WHERE id IN (%s)', $defaultrole['id'], implode(',', $userids)));
        }

        return UserRole::query()->where('id', $id)->delete();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Model|null|static
     */
    public function getDefaultRole()
    {
        return UserRole::query()->where('isdefault', '=', 1)->first();
    }

    /**
     * Get the quantity of API requests in the last day for the users_id.
     *
     * @param int $userID
     *
     * @return int
     * @throws \Exception
     */
    public function getApiRequests($userID): int
    {
        // Clear old requests.
        $this->clearApiRequests($userID);
        $requests = UserRequest::query()->where('users_id', $userID)->count('id');

        return ! $requests ? 0 : $requests;
    }

    /**
     * If a user accesses the API, log it.
     *
     * @param int    $userID  ID of the user.
     * @param string $request The API request.
     */
    public function addApiRequest($userID, $request): void
    {
        UserRequest::query()->insert(['users_id' => $userID, 'request' => $request, 'timestamp'=> Carbon::now()]);
    }

    /**
     * Delete api requests older than a day.
     *
     * @param int|bool $userID
     *                   int The users ID.
     *                   bool false do all user ID's..
     *
     * @return void
     * @throws \Exception
     */
    protected function clearApiRequests($userID): void
    {
        if ($userID === false) {
            UserRequest::query()->where('timestamp', '<', Carbon::now()->subDay())->delete();
        } else {
            UserRequest::query()->where('users_id', $userID)->where('timestamp', '<', Carbon::now()->subDay())->delete();
        }
    }

    /**
     * deletes old rows FROM the user_requests and user_downloads tables.
     * if site->userdownloadpurgedays SET to 0 then all release history is removed but
     * the download/request rows must remain for at least one day to allow the role based
     * limits to apply.
     *
     * @param int $days
     */
    public function pruneRequestHistory($days = 0): void
    {
        if ($days === 0) {
            $days = 1;
            UserDownload::query()->update(['releases_id' => null]);
        }

        UserRequest::query()->where('timestamp', '<', Carbon::now()->subDays($days))->delete();
        UserDownload::query()->where('timestamp', '<', Carbon::now()->subDays($days))->delete();
    }

    /**
     * Get the COUNT of how many NZB's the user has downloaded in the past day.
     *
     * @param int $userID
     *
     * @return int
     */
    public function getDownloadRequests($userID): int
    {
        // Clear old requests.
        UserDownload::query()->where('users_id', $userID)->where('timestamp', '<', Carbon::now()->subDay())->delete();
        $value = UserDownload::query()->where('users_id', $userID)->where('timestamp', '>', Carbon::now()->subDay())->count('id');

        return $value === false ? 0 : $value;
    }

    /**
     * @param $userID
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function getDownloadRequestsForUser($userID)
    {
        return UserDownload::query()->where('users_id', $userID)->with('release')->orderBy('timestamp', 'DESC')->get();
    }

    /**
     * If a user downloads a NZB, log it.
     *
     * @param int $userID id of the user.
     *
     * @param     $releaseID
     *
     * @return bool|int
     */
    public function addDownloadRequest($userID, $releaseID)
    {
        return UserDownload::query()
            ->insertGetId(
                [
                    'users_id' => $userID,
                    'releases_id' => $releaseID,
                    'timestamp' => Carbon::now(),
                ]
            );
    }

    /**
     * @param int $releaseID
     * @return mixed
     */
    public function delDownloadRequestsForRelease(int $releaseID)
    {
        return UserDownload::query()->where('releases_id', $releaseID)->delete();
    }

    /**
     * Checks if a user is a specific role.
     *
     * @notes Uses type of $user to denote identifier. if string: username, if int: users_id
     * @param int $roleID
     * @param string|int $user
     * @return bool
     */
    public function roleCheck($roleID, $user): bool
    {
        $result = User::query()->where('username', $user)->orWhere('id', $user)->first(['user_roles_id']);

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
    public function isAdmin($userID): bool
    {
        return $this->roleCheck(self::ROLE_ADMIN, (int) $userID);
    }

    /**
     * Wrapper for roleCheck specifically for Moderators.
     *
     * @param int $userId
     * @return bool
     */
    public function isModerator($userId): bool
    {
        return $this->roleCheck(self::ROLE_MODERATOR, (int) $userId);
    }
}
