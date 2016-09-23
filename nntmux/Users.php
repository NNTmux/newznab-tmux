<?php
namespace nntmux;

use nntmux\db\Settings;
use nntmux\utility\Utility;

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
	 * @param array $options Class instances.
	 */
	public function __construct(array $options = [])
	{
		$defaults = [
			'Settings' => null,
		];
		$options += $defaults;

		$this->pdo = ($options['Settings'] instanceof Settings ? $options['Settings'] : new Settings());

		$this->password_hash_cost = (defined('NN_PASSWORD_HASH_COST') ? NN_PASSWORD_HASH_COST : 11);
	}

	/**
	 * Verify a password against a hash.
	 *
	 * Automatically update the hash if it needs to be.
	 *
	 * @param string $password Password to check against hash.
	 * @param string $hash     Hash to check against password.
	 * @param int    $userID   ID of the user.
	 *
	 * @return bool
	 */
	public function checkPassword($password, $hash, $userID = -1)
	{
		if (password_verify($password, $hash) === false) {
			return false;
		}

		// Update the hash if it needs to be.
		if (is_numeric($userID) && $userID > 0 && password_needs_rehash($hash, PASSWORD_DEFAULT, ['cost' => $this->password_hash_cost])) {
			$hash = $this->hashPassword($password);

			if ($hash !== false) {
				$this->pdo->queryExec(
					sprintf(
						'UPDATE users SET password = %s WHERE id = %d',
						$this->pdo->escapeString((string)$hash),
						$userID
					)
				);
			}
		}
		return true;
	}

	public function get()
	{
		return $this->pdo->query("SELECT * FROM users");
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
		$row = $this->pdo->queryOneRow(sprintf("SELECT style FROM users WHERE id = %d", $userID));
		return ($row === false ? 'None' : $row['style']);
	}

	public function delete($id)
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

		$this->pdo->queryExec(sprintf("DELETE FROM users WHERE id = %d", $id));
	}

	public function delCartForUser($uid)
	{
		$this->pdo->queryExec(sprintf("DELETE FROM users_releases WHERE users_id = %d", $uid));
	}

	public function delUserCategoryExclusions($uid)
	{
		$this->pdo->queryExec(sprintf("DELETE FROM user_excluded_categories WHERE users_id = %d", $uid));
	}

	public function delDownloadRequests($userID)
	{
		return $this->pdo->queryExec(sprintf("DELETE FROM user_downloads WHERE users_id = %d", $userID));
	}

	public function delApiRequests($userID)
	{
		return $this->pdo->queryExec(sprintf("DELETE FROM user_requests WHERE users_id = %d", $userID));
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
	 */
	public function getRange($start, $offset, $orderBy, $userName = '', $email = '', $host = '', $role = '', $apiRequests = false)
	{
		if ($apiRequests) {
			$this->clearApiRequests(false);
			$query = ("
				SELECT users.*, user_roles.name AS rolename, COUNT(user_requests.id) AS apirequests
				FROM users
				INNER JOIN user_roles ON user_roles.id = users.role
				LEFT JOIN user_requests ON user_requests.users_id = users.id
				WHERE users.id != 0 %s %s %s %s
				AND email != 'sharing@nZEDb.com'
				GROUP BY users.id
				ORDER BY %s %s %s"
			);
		} else {
			$query = ("
				SELECT users.*, user_roles.name AS rolename
				FROM users
				INNER JOIN user_roles ON user_roles.id = users.role
				WHERE 1=1 %s %s %s %s
				ORDER BY %s %s %s"
			);
		}

		$order = $this->getBrowseOrder($orderBy);

		return $this->pdo->query(
			sprintf(
				$query,
				($userName != '' ? ('AND users.username ' . $this->pdo->likeString($userName)) : ''),
				($email != '' ? ('AND users.email ' . $this->pdo->likeString($email)) : ''),
				($host != '' ? ('AND users.host ' . $this->pdo->likeString($host)) : ''),
				($role != '' ? ('AND users.role = ' . $role) : ''),
				$order[0],
				$order[1],
				($start === false ? '' : ('LIMIT ' . $offset . ' OFFSET ' . $start))
			)
		);
	}

	public function getBrowseOrder($orderby)
	{
		$order = ($orderby == '') ? 'username_desc' : $orderby;
		$orderArr = explode("_", $order);
		switch ($orderArr[0]) {
			case 'username':
				$orderfield = 'username';
				break;
			case 'email':
				$orderfield = 'email';
				break;
			case 'host':
				$orderfield = 'host';
				break;
			case 'createddate':
				$orderfield = 'createddate';
				break;
			case 'lastlogin':
				$orderfield = 'lastlogin';
				break;
			case 'apiaccess':
				$orderfield = 'apiaccess';
				break;
			case 'apirequests':
				$orderfield = 'apirequests';
				break;
			case 'grabs':
				$orderfield = 'grabs';
				break;
			case 'role':
				$orderfield = 'role';
				break;
			case 'rolechangedate':
				$orderfield = 'rolechangedate';
				break;
			default:
				$orderfield = 'username';
				break;
		}
		$ordersort = (isset($orderArr[1]) && preg_match('/^asc|desc$/i', $orderArr[1])) ? $orderArr[1] : 'DESC';

		return [$orderfield, $ordersort];
	}

	public function getCount()
	{
		$res = $this->pdo->queryOneRow("SELECT COUNT(id) as num FROM users WHERE email != 'sharing@nZEDb.com'");

		return $res["num"];
	}

	public function update($id, $userName, $email, $grabs, $role, $notes, $invites, $movieview, $musicview, $gameview, $xxxview, $consoleview, $bookview, $queueType = '', $nzbgetURL = '', $nzbgetUsername = '', $nzbgetPassword = '', $saburl = '', $sabapikey = '', $sabpriority = '', $sabapikeytype = '', $nzbvortexServerUrl = false, $nzbvortexApiKey = false, $cp_url = false, $cp_api = false, $style = 'None')
	{
		$userName = trim($userName);
		$email = trim($email);

		if (!$this->isValidUsername($userName))
			return Users::ERR_SIGNUP_BADUNAME;

		if (!$this->isValidEmail($email))
			return Users::ERR_SIGNUP_BADEMAIL;

		$res = $this->getByUsername($userName);
		if ($res)
			if ($res["id"] != $id)
				return Users::ERR_SIGNUP_UNAMEINUSE;

		$res = $this->getByEmail($email);
		if ($res)
			if ($res["id"] != $id)
				return Users::ERR_SIGNUP_EMAILINUSE;

		$sql = [];

		$sql[] = sprintf('username = %s', $this->pdo->escapeString($userName));
		$sql[] = sprintf('email = %s', $this->pdo->escapeString($email));
		$sql[] = sprintf('grabs = %d', $grabs);
		$sql[] = sprintf('role = %d', $role);
		$sql[] = sprintf('notes = %s', $this->pdo->escapeString(substr($notes, 0, 255)));
		$sql[] = sprintf('invites = %d', $invites);
		$sql[] = sprintf('movieview = %d', $movieview);
		$sql[] = sprintf('musicview = %d', $musicview);
		$sql[] = sprintf('gameview = %d', $gameview);
		$sql[] = sprintf('xxxview = %d', $xxxview);
		$sql[] = sprintf('consoleview = %d', $consoleview);
		$sql[] = sprintf('bookview = %d', $bookview);
		$sql[] = sprintf('style = %s', $this->pdo->escapeString($style));

		if ($queueType !== '') {
			$sql[] = sprintf('queuetype = %d', $queueType);
		}

		if ($nzbgetURL !== '') {
			$sql[] = sprintf('nzbgeturl = %s', $this->pdo->escapeString($nzbgetURL));
		}

		$sql[] = sprintf('nzbgetusername = %s', $this->pdo->escapeString($nzbgetUsername));
		$sql[] = sprintf('nzbgetpassword = %s', $this->pdo->escapeString($nzbgetPassword));

		if ($saburl !== '') {
			$sql[] = sprintf('saburl = %s', $this->pdo->escapeString($saburl));
		}
		if ($sabapikey !== '') {
			$sql[] = sprintf('sabapikey = %s', $this->pdo->escapeString($sabapikey));
		}
		if ($sabpriority !== '') {
			$sql[] = sprintf('sabpriority = %d', $sabpriority);
		}
		if ($sabapikeytype !== '') {
			$sql[] = sprintf('sabapikeytype = %d', $sabapikeytype);
		}
		if ($nzbvortexServerUrl !== false) {
			$sql[] = sprintf("nzbvortex_server_url = '%s'", $nzbvortexServerUrl);
		}
		if ($nzbvortexApiKey !== false) {
			$sql[] = sprintf("nzbvortex_api_key = '%s'", $nzbvortexApiKey);
		}
		if ($cp_url !== false) {
			$sql[] = sprintf('cp_url = %s', $this->pdo->escapeString($cp_url));
		}
		if ($cp_api !== false) {
			$sql[] = sprintf('cp_api = %s', $this->pdo->escapeString($cp_api));
		}

		$this->pdo->queryExec(sprintf("UPDATE users SET %s WHERE id = %d", implode(', ', $sql), $id));

		return self::SUCCESS;
	}

	public function isValidUsername($userName)
	{
		return preg_match("/^[a-z][a-z0-9_]{2,}$/i", $userName);
	}

	/**
	 * When a user is registering or updating their profile, check if the email is valid.
	 *
	 * @param string $email
	 *
	 * @return bool
	 */
	public function isValidEmail($email)
	{
		return (bool)preg_match('/^([\w\+-]+)(\.[\w\+-]+)*@([a-z0-9-]+\.)+[a-z]{2,6}$/i', $email);
	}

	public function getByUsername($userName)
	{
		return $this->pdo->queryOneRow(sprintf("SELECT users.*, user_roles.name as rolename, user_roles.apirequests, user_roles.downloadrequests FROM users INNER JOIN user_roles on user_roles.id = users.role WHERE username = %s ", $this->pdo->escapeString($userName)));
	}

	public function getByEmail($email)
	{
		return $this->pdo->queryOneRow(sprintf("SELECT * FROM users WHERE lower(email) = %s ", $this->pdo->escapeString(strtolower($email))));
	}

	public function updateUserRole($uid, $role)
	{
		$this->pdo->queryExec(sprintf("UPDATE users SET role = %d WHERE id = %d", $role, $uid));

		return Users::SUCCESS;
	}

	public function updateUserRoleChangeDate($uid, $date)
	{
		$this->pdo->queryExec(sprintf("UPDATE users SET rolechangedate = '%s' WHERE id = %d", $date, $uid));

		return Users::SUCCESS;
	}

	public function updateExpiredRoles($uprole, $downrole, $msgsubject, $msgbody)
	{
		$data = $this->pdo->query(sprintf("SELECT id,email FROM users WHERE role = %d AND rolechangedate < now()", $uprole));
		foreach ($data as $u) {
			Utility::sendEmail($u["email"], $msgsubject, $msgbody, $this->pdo->getSetting('email'));
			$this->pdo->queryExec(sprintf("UPDATE users SET role = %d, rolechangedate=null WHERE id = %d", $downrole, $u["id"]));
		}

		return Users::SUCCESS;
	}

	public function updateRssKey($uid)
	{

		$this->pdo->queryExec(sprintf("UPDATE users SET rsstoken = md5(%s) WHERE id = %d", $this->pdo->escapeString(uniqid()), $uid));

		return Users::SUCCESS;
	}

	public function updatePassResetGuid($id, $guid)
	{

		$this->pdo->queryExec(sprintf("UPDATE users SET resetguid = %s WHERE id = %d", $this->pdo->escapeString($guid), $id));

		return Users::SUCCESS;
	}

	public function updatePassword($id, $password)
	{

		$this->pdo->queryExec(sprintf("UPDATE users SET password = %s, userseed=md5(%s) WHERE id = %d", $this->pdo->escapeString($this->hashPassword($password)), $this->pdo->escapeString(Utility::generateUuid()), $id));

		return Users::SUCCESS;
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
		return password_hash($password, PASSWORD_DEFAULT, ['cost' => $this->password_hash_cost]);
	}

	public static function hashSHA1($string)
	{
		return sha1($string);
	}

	public function getByPassResetGuid($guid)
	{


		return $this->pdo->queryOneRow(sprintf("SELECT * FROM users WHERE resetguid = %s ", $this->pdo->escapeString($guid)));
	}

	public function incrementGrabs($id, $num = 1)
	{

		$this->pdo->queryExec(sprintf("UPDATE users SET grabs = grabs + %d WHERE id = %d ", $num, $id));
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

		return ($user['rsstoken'] != $rssToken ? false : $user);
	}

	public function getById($id)
	{

		$sql = sprintf("SELECT users.*, user_roles.name as rolename, user_roles.hideads, user_roles.canpreview, user_roles.apirequests, user_roles.downloadrequests, NOW() as now FROM users INNER JOIN user_roles on user_roles.id = users.role WHERE users.id = %d ", $id);

		return $this->pdo->queryOneRow($sql);
	}

	public function getByRssToken($rsstoken)
	{


		return $this->pdo->queryOneRow(sprintf("SELECT users.*, user_roles.apirequests, user_roles.downloadrequests, NOW() as now FROM users INNER JOIN user_roles on user_roles.id = users.role WHERE users.rsstoken = %s ", $this->pdo->escapeString($rsstoken)));
	}

	public function getBrowseOrdering()
	{
		return array('username_asc', 'username_desc', 'email_asc', 'email_desc', 'host_asc', 'host_desc', 'createddate_asc', 'createddate_desc', 'lastlogin_asc', 'lastlogin_desc', 'apiaccess_asc', 'apiaccess_desc', 'apirequets_asc', 'apirequests_desc', 'grabs_asc', 'grabs_desc', 'role_asc', 'role_desc', 'rolechangedate_asc', 'rolechangedate_desc');
	}

	public function isDisabled($username)
	{

		return $this->roleCheck(self::ROLE_DISABLED, $username);
	}

	public function isValidUrl($url)
	{
		return (!preg_match('/^(http|https|ftp):\/\/([A-Z0-9][A-Z0-9_-]*(?:\.[A-Z0-9][A-Z0-9_-]*)+):?(\d+)?\/?/i', $url)) ? false : true;
	}

	/**
	 * Create a random username.
	 *
	 * @param string $email
	 *
	 * @return string
	 */
	public function generateUsername($email)
	{
		$string = '';
		if (preg_match('/[A-Za-z0-9]+/', $email, $matches)) {
			$string = $matches[0];
		}

		return "u" . substr(md5(uniqid()), 0, 7) . $string;
	}

	public function generatePassword()
	{
		return substr(md5(uniqid()), 0, 8);
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
	 */
	public function signup($userName, $password, $email, $host, $role = self::ROLE_USER, $notes, $invites = self::DEFAULT_INVITES, $inviteCode = '', $forceInviteMode = false)
	{

		$userName = trim($userName);
		$password = trim($password);
		$email = trim($email);

		if (!$this->isValidUsername($userName))
			return Users::ERR_SIGNUP_BADUNAME;

		if (!$this->isValidPassword($password))
			return Users::ERR_SIGNUP_BADPASS;

		if (!$this->isValidEmail($email)) {
			return self::ERR_SIGNUP_BADEMAIL;
		}

		$res = $this->getByUsername($userName);
		if ($res)
			return Users::ERR_SIGNUP_UNAMEINUSE;

		$res = $this->getByEmail($email);
		if ($res)
			return Users::ERR_SIGNUP_EMAILINUSE;

		// Make sure this is the last check, as if a further validation check failed, the invite would still have been used up.
		$invitedBy = 0;
		if (($this->pdo->getSetting('registerstatus') == Settings::REGISTER_STATUS_INVITE) && !$forceInviteMode) {
			if ($inviteCode == '') {
				return self::ERR_SIGNUP_BADINVITECODE;
			}

			$invitedBy = $this->checkAndUseInvite($inviteCode);
			if ($invitedBy < 0) {
				return self::ERR_SIGNUP_BADINVITECODE;
			}
		}

		return $this->add($userName, $password, $email, $role, $notes, $host, $invites, $invitedBy);
	}

	public function isValidPassword($password)
	{
		return (strlen($password) > 5);
	}

	/**
	 * If a invite is used, decrement the person who invited's invite count.
	 *
	 * @param int $inviteCode
	 *
	 * @return int
	 */
	public function checkAndUseInvite($inviteCode)
	{
		$invite = $this->getInvite($inviteCode);
		if (!$invite) {
			return -1;
		}

		$this->pdo->queryExec(sprintf("UPDATE users SET invites = invites-1 WHERE id = %d ", $invite["users_id"]));
		$this->deleteInvite($inviteCode);
		return $invite["users_id"];
	}

	public function getInvite($inviteToken)
	{


		//
		// Tidy any old invites sent greater than DEFAULT_INVITE_EXPIRY_DAYS days ago.
		//
		$this->pdo->queryExec(sprintf("DELETE FROM invitations WHERE createddate < now() - INTERVAL %d DAY", self::DEFAULT_INVITE_EXPIRY_DAYS));

		return $this->pdo->queryOneRow(
			sprintf(
				"SELECT * FROM invitations WHERE guid = %s",
				$this->pdo->escapeString($inviteToken)
			)
		);
	}

	public function deleteInvite($inviteToken)
	{

		$this->pdo->queryExec(sprintf("DELETE FROM invitations WHERE guid = %s ", $this->pdo->escapeString($inviteToken)));
	}


	/**
	 * Add a new user.
	 *
	 * @param string  $userName
	 * @param string  $password
	 * @param string  $email
	 * @param integer $role
	 * @param         $notes
	 * @param         $host
	 * @param integer $invites
	 * @param integer $invitedBy
	 *
	 * @return bool|int
	 */
	public function add($userName, $password, $email, $role, $notes, $host, $invites = self::DEFAULT_INVITES, $invitedBy = 0)
	{

		$password = $this->hashPassword($password);
		if (!$password) {
			return false;
		}
		return $this->pdo->queryInsert(
			sprintf("
				INSERT INTO users (username, password, email, role, createddate, host, rsstoken,
					invites, invitedby, userseed, notes)
				VALUES (%s, %s, LOWER(%s), %d, NOW(), %s, MD5(%s), %d, %s, MD5(%s), %s)",
				$this->pdo->escapeString($userName),
				$this->pdo->escapeString((string)$password),
				$this->pdo->escapeString($email),
				$role,
				$this->pdo->escapeString(($this->pdo->getSetting('storeuserips') == 1 ? $host : '')),
				$this->pdo->escapeString(uniqid()),
				$invites,
				($invitedBy == 0 ? 'NULL' : $invitedBy),
				$this->pdo->escapeString($this->pdo->uuid()),
				$this->pdo->escapeString($notes)
			)
		);
	}

	/**
	 * Verify if the user is logged in.
	 *
	 * @return bool
	 */
	public function isLoggedIn()
	{
		if (isset($_SESSION['uid'])) {
			return true;
		} else if (isset($_COOKIE['uid']) && isset($_COOKIE['idh'])) {
			$u = $this->getById($_COOKIE['uid']);

			if (($_COOKIE['idh'] == $this->hashSHA1($u["userseed"] . $_COOKIE['uid'])) && ($u["role"] != self::ROLE_DISABLED)) {
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
	 * @param string $remember Save the user in cookies to keep them logged in.
	 */
	public function login($userID, $host = '', $remember = '')
	{
		$_SESSION['uid'] = $userID;

		if ($this->pdo->getSetting('storeuserips') != 1) {
			$host = '';
		}

		$this->updateSiteAccessed($userID, $host);

		if ($remember == 1) {
			$this->setCookies($userID);
		}
	}

	/**
	 * When a user logs in, update the last time they logged in.
	 *
	 * @param int    $userID ID of the user.
	 * @param string $host
	 */
	public function updateSiteAccessed($userID, $host = '')
	{
		$this->pdo->queryExec(
			sprintf(
				"UPDATE users SET lastlogin = NOW() %s WHERE id = %d",
				($host == '' ? '' : (', host = ' . $this->pdo->escapeString($host))),
				$userID
			)
		);
	}

	/**
	 * Set up cookies for a user.
	 *
	 * @param int $userID
	 */
	public function setCookies($userID)
	{
		$user = $this->getById($userID);
		$secure_cookie = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on' ? '1' : '0');
		setcookie('uid', $userID, (time() + 2592000), '/', null, $secure_cookie, true);
		setcookie('idh', ($this->hashSHA1($user['userseed'] . $userID)), (time() + 2592000), '/', null, $secure_cookie, true);	}

	/**
	 * Return the User ID of the user.
	 *
	 * @return int
	 */
	public function currentUserId()
	{
		return (isset($_SESSION['uid']) ? $_SESSION['uid'] : -1);
	}

	/**
	 * Logout the user, destroying his cookies and session.
	 */
	public function logout()
	{
		session_unset();
		session_destroy();
		$secure_cookie = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on' ? '1' : '0');
		setcookie('uid', null, -1, '/', null, $secure_cookie, true);
		setcookie('idh', null, -1, '/', null, $secure_cookie, true);
	}

	public function updateApiAccessed($uid)
	{

		$this->pdo->queryExec(sprintf("UPDATE users SET apiaccess = now() WHERE id = %d ", $uid));
	}

	public function addCart($uid, $releaseid)
	{

		$sql = sprintf("INSERT INTO users_releases (users_id, releases_id, createddate) VALUES (%d, %d, now())", $uid, $releaseid);

		return $this->pdo->queryInsert($sql);
	}

	public function getCart($uid, $releaseid = "")
	{

		if ($releaseid != "")
			$releaseid = " AND releases.id = " . $this->pdo->escapeString($releaseid);

		return $this->pdo->query(sprintf("SELECT users_releases.*, releases.searchname,releases.guid FROM users_releases INNER JOIN releases on releases.id = users_releases.releases_id WHERE users_id = %d %s", $uid, $releaseid));
	}

	public function delCartByGuid($guids, $userID)
	{
		if (!is_array($guids)) {
			return false;
		}

		$del = [];
		foreach ($guids as $guid) {
			$rel = $this->pdo->queryOneRow(sprintf("SELECT id FROM releases WHERE guid = %s", $this->pdo->escapeString($guid)));
			if ($rel) {
				$del[] = $rel['id'];
			}
		}

		return (bool)$this->pdo->queryExec(
			sprintf(
				"DELETE FROM users_releases WHERE releases_id IN (%s) AND users_id = %d", implode(',', $del), $userID
			)
		);
	}

	public function delCartByUserAndRelease($guid, $uid)
	{
		$rel = $this->pdo->queryOneRow(sprintf("SELECT id FROM releases WHERE guid = %s", $this->pdo->escapeString($guid)));
		if ($rel)
			$this->pdo->queryExec(sprintf("DELETE FROM users_releases WHERE users_id = %d AND releases_id = %d", $uid, $rel["id"]));
	}

	public function delCartForRelease($rid)
	{
		$this->pdo->queryExec(sprintf("DELETE FROM users_releases WHERE releases_id = %d", $rid));
	}

	public function addCategoryExclusions($uid, $catids)
	{
		$this->delUserCategoryExclusions($uid);
		if (COUNT($catids) > 0) {
			foreach ($catids as $catid) {
				$this->pdo->queryInsert(sprintf("INSERT INTO user_excluded_categories (users_id, categories_id, createddate) VALUES (%d, %d, now())", $uid, $catid));
			}
		}
	}

	public function getRoleCategoryExclusion($role)
	{
		$ret = [];
		$data = $this->pdo->query(sprintf("SELECT categories_id FROM role_excluded_categories WHERE role = %d", $role));
		foreach ($data as $d)
			$ret[] = $d["categories_id"];

		return $ret;
	}

	public function addRoleCategoryExclusions($role, $catids)
	{
		$this->delRoleCategoryExclusions($role);
		if (COUNT($catids) > 0) {
			foreach ($catids as $catid) {
				$this->pdo->queryInsert(sprintf("INSERT INTO role_excluded_categories (role, categories_id, createddate) VALUES (%d, %d, now())", $role, $catid));
			}
		}
	}

	public function delRoleCategoryExclusions($role)
	{
		$this->pdo->queryExec(sprintf("DELETE FROM role_excluded_categories WHERE role = %d", $role));
	}

	/**
	 * Get the list of categories the user has excluded.
	 *
	 * @param int $userID ID of the user.
	 *
	 * @return array
	 */
	public function getCategoryExclusion($userID)
	{
		$ret = [];
		$categories = $this->pdo->query(sprintf("SELECT categories_id FROM user_excluded_categories WHERE users_id = %d", $userID));
		foreach ($categories as $category) {
			$ret[] = $category["categories_id"];
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
	public function getCategoryExclusionNames($userID)
	{
		$data = $this->getCategoryExclusion($userID);
		$category = new Category(['Settings' => $this->pdo]);
		$categories = $category->getByIds($data);
		$ret = [];
		if ($categories !== false) {
			foreach ($categories as $cat) {
				$ret[] = $cat["title"];
			}
		}
		return $ret;
	}

	public function delCategoryExclusion($uid, $catid)
	{
		$this->pdo->queryExec(sprintf("DELETE FROM user_excluded_categories WHERE users_id = %d AND categories_id = %d", $uid, $catid));
	}

	public function sendInvite($sitetitle, $siteemail, $serverurl, $uid, $emailto)
	{
		$sender = $this->getById($uid);
		$token = $this->hashSHA1(uniqid());
		$subject = $sitetitle . " Invitation";
		$url = $serverurl . "register?invitecode=" . $token;
		$contents = $sender["username"] . " has sent an invite to join " . $sitetitle . " to this email address. To accept the invitation click the following link.\n\n " . $url;

		Utility::sendEmail($emailto, $subject, $contents, $siteemail);
		$this->addInvite($uid, $token);

		return $url;
	}

	public function addInvite($uid, $inviteToken)
	{
		$this->pdo->queryInsert(sprintf("INSERT INTO invitations (guid, users_id, createddate) VALUES (%s, %d, now())", $this->pdo->escapeString($inviteToken), $uid));
	}

	public function getTopGrabbers()
	{
		return $this->pdo->query("SELECT id, username, SUM(grabs) as grabs FROM users
							GROUP BY id, username
							HAVING SUM(grabs) > 0
							ORDER BY grabs DESC
							LIMIT 10"
		);
	}

	/**
	 * Get list of user signups by month.
	 *
	 * @return array
	 */
	public function getUsersByMonth()
	{
		return $this->pdo->query("
			SELECT DATE_FORMAT(createddate, '%M %Y') AS mth, COUNT(id) AS num
			FROM users
			WHERE createddate IS NOT NULL AND createddate != '0000-00-00 00:00:00'
			GROUP BY mth
			ORDER BY createddate DESC"
		);
	}

	public function getUsersByHostHash()
	{
		$ipsql = "('-1')";

		if ($this->pdo->getSetting('userhostexclusion') != '') {
			$ipsql = "";
			$ips = explode(",", $this->pdo->getSetting('userhostexclusion'));
			foreach ($ips as $ip) {
				$ipsql .= $this->pdo->escapeString($this->getHostHash($ip, $this->pdo->getSetting('siteseed'))) . ",";
			}
			$ipsql = "(" . $ipsql . " '-1')";
		}

		$sql = sprintf("SELECT hosthash, group_concat(users_id) AS user_string, group_concat(username) AS user_names
							FROM
							(
							SELECT hosthash, users_id, username FROM user_downloads LEFT OUTER JOIN users ON users.id = user_downloads.users_id WHERE hosthash IS NOT NULL AND hosthash NOT IN %s GROUP BY hosthash, users_id
							union distinct
							SELECT hosthash, users_id, username FROM user_requests LEFT OUTER JOIN users on users.id = user_requests.users_id WHERE hosthash IS NOT NULL AND hosthash NOT IN %s GROUP BY hosthash, users_id
							) x
							GROUP BY hosthash
							HAVING CAST((LENGTH(group_concat(users_id)) - LENGTH(REPLACE(group_concat(users_id), ',', ''))) / LENGTH(',') AS UNSIGNED) < 9
							ORDER BY CAST((LENGTH(group_concat(users_id)) - LENGTH(REPLACE(group_concat(users_id), ',', ''))) / LENGTH(',') AS UNSIGNED) DESC
							limit 10", $ipsql, $ipsql
		);

		return $this->pdo->query($sql);
	}

	public function getHostHash($host, $siteseed = "")
	{
		if ($siteseed == "") {
			$siteseed = $this->pdo->getSetting('siteseed');
		}

		return self::hashSHA1($siteseed . $host . $siteseed);
	}

	public function getUsersByRole()
	{
		return $this->pdo->query("SELECT ur.name, COUNT(u.id) as num FROM users u
							INNER JOIN user_roles ur ON ur.id = u.role
							GROUP BY ur.name
							ORDER BY COUNT(u.id) DESC;"
		);
	}

	public function getLoginCountsByMonth()
	{
		return $this->pdo->query("SELECT 'Login' as type,
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

	public function getRoles()
	{
		return $this->pdo->query("SELECT * FROM user_roles");
	}

	public function getRoleById($id)
	{
		$sql = sprintf("SELECT * FROM user_roles WHERE id = %d", $id);

		return $this->pdo->queryOneRow($sql);
	}

	public function addRole($name, $apirequests, $downloadrequests, $defaultinvites, $canpreview, $hideads)
	{
		$sql = sprintf("INSERT INTO user_roles (name, apirequests, downloadrequests, defaultinvites, canpreview, hideads) VALUES (%s, %d, %d, %d, %d, %d)", $this->pdo->escapeString($name), $apirequests, $downloadrequests, $defaultinvites, $canpreview, $hideads);

		return $this->pdo->queryInsert($sql);
	}

	public function updateRole($id, $name, $apirequests, $downloadrequests, $defaultinvites, $isdefault, $canpreview, $hideads)
	{
		if ($isdefault == 1) {
			$this->pdo->queryExec("UPDATE user_roles SET isdefault=0");
		}
		return $this->pdo->queryExec(sprintf("UPDATE user_roles SET name=%s, apirequests=%d, downloadrequests=%d, defaultinvites=%d, isdefault=%d, canpreview=%d, hideads=%d WHERE id=%d", $this->pdo->escapeString($name), $apirequests, $downloadrequests, $defaultinvites, $isdefault, $canpreview, $hideads, $id));
	}

	public function deleteRole($id)
	{
		$res = $this->pdo->query(sprintf("SELECT id FROM users WHERE role = %d", $id));
		if (sizeof($res) > 0) {
			$userids = [];
			foreach ($res as $user)
				$userids[] = $user['id'];
			$defaultrole = $this->getDefaultRole();
			$this->pdo->queryExec(sprintf("UPDATE users SET role=%d WHERE id IN (%s)", $defaultrole['id'], implode(',', $userids)));
		}

		return $this->pdo->queryExec(sprintf("DELETE FROM user_roles WHERE id=%d", $id));
	}

	public function getDefaultRole()
	{
		return $this->pdo->queryOneRow("SELECT * FROM user_roles WHERE isdefault = 1");
	}

	/**
	 * Get the quantity of API requests in the last day for the users_id.
	 *
	 * @param int $userID
	 *
	 * @return int
	 */
	public function getApiRequests($userID)
	{
		// Clear old requests.
		$this->clearApiRequests($userID);
		$requests = $this->pdo->queryOneRow(
			sprintf('SELECT COUNT(id) AS num FROM user_requests WHERE users_id = %d', $userID)
		);
		return (!$requests ? 0 : (int)$requests['num']);
	}

	/**
	 * If a user accesses the API, log it.
	 *
	 * @param int    $userID  ID of the user.
	 * @param string $request The API request.
	 *
	 * @return bool|int
	 */
	public function addApiRequest($userID, $request)
	{
		return $this->pdo->queryInsert(
			sprintf(
				"INSERT INTO user_requests (users_id, request, timestamp) VALUES (%d, %s, NOW())",
				$userID,
				$this->pdo->escapeString($request)
			)
		);
	}

	/**
	 * Delete api requests older than a day.
	 *
	 * @param int|bool  $userID
	 *                   int The users ID.
	 *                   bool false do all user ID's..
	 *
	 * @return void
	 */
	protected function clearApiRequests($userID)
	{
		if ($userID === false) {
			$this->pdo->queryExec('DELETE FROM user_requests WHERE timestamp < DATE_SUB(NOW(), INTERVAL 1 DAY)');
		} else {
			$this->pdo->queryExec(
				sprintf(
					'DELETE FROM user_requests WHERE users_id = %d AND timestamp < DATE_SUB(NOW(), INTERVAL 1 DAY)',
					$userID
				)
			);
		}
	}

	/**
	 * deletes old rows FROM the userrequest and user_downloads tables.
	 * if site->userdownloadpurgedays SET to 0 then all release history is removed but
	 * the download/request rows must remain for at least one day to allow the role based
	 * limits to apply.
	 *
	 * @param int $days
	 */
	public function pruneRequestHistory($days = 0)
	{
		if ($days == 0) {
			$days = 1;
			$this->pdo->queryExec("UPDATE user_downloads SET releases_id = null");
		}

		$this->pdo->queryExec(sprintf("DELETE FROM user_requests WHERE timestamp < DATE_SUB(NOW(), INTERVAL %d DAY)", $days));
		$this->pdo->queryExec(sprintf("DELETE FROM user_downloads WHERE timestamp < DATE_SUB(NOW(), INTERVAL %d DAY)", $days));
	}

	/**
	 * Get the COUNT of how many NZB's the user has downloaded in the past day.
	 *
	 * @param int $userID
	 *
	 * @return int
	 */
	public function getDownloadRequests($userID)
	{
		// Clear old requests.
		$this->pdo->queryExec(
			sprintf(
				'DELETE FROM user_downloads WHERE users_id = %d AND timestamp < DATE_SUB(NOW(), INTERVAL 1 DAY)',
				$userID
			)
		);
		$value = $this->pdo->queryOneRow(
			sprintf(
				'SELECT COUNT(id) AS num FROM user_downloads WHERE users_id = %d AND timestamp > DATE_SUB(NOW(), INTERVAL 1 DAY)',
				$userID
			)
		);
		return ($value === false ? 0 : (int) $value['num']);
	}

	public function getDownloadRequestsForUser($userID)
	{
		return $this->pdo->query(sprintf('SELECT u.*, r.guid, r.searchname FROM user_downloads u
										  LEFT OUTER JOIN releases r ON r.id = u.releases_id
										  WHERE u.users_id = %d
										  ORDER BY u.timestamp
										  DESC',
											$userID
										)
									);
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
		return $this->pdo->queryInsert(
			sprintf(
				"INSERT INTO user_downloads (users_id, releases_id, timestamp) VALUES (%d, %d, NOW())",
				$userID,
				$releaseID
			)
		);
	}

	public function delDownloadRequestsForRelease($releaseID)
	{
		return $this->pdo->queryInsert(sprintf("delete FROM user_downloads WHERE releases_id = %d", $releaseID));
	}

	/**
	 * Checks if a user is a specific role.
	 *
	 * @notes Uses type of $user to denote identifier. if string: username, if int: users_id
	 * @param int $roleID
	 * @param string|int $user
	 * @return bool
	 */
	public function roleCheck($roleID, $user) {

		if (is_string($user) && strlen($user) > 0) {
			$user = $this->pdo->escapeString($user);
			$querySuffix = "username = $user";
		} elseif (is_int($user) && $user >= 0) {
			$querySuffix = "id = $user";
		} else {
			return false;
		}

		$result = $this->pdo->queryOneRow(
			sprintf(
				"SELECT role FROM users WHERE %s",
				$querySuffix
			)
		);

		return ((integer)$result['role'] == (integer) $roleID) ? true : false;
	}

	/**
	 * Wrapper for roleCheck specifically for Admins.
	 *
	 * @param int $userID
	 * @return bool
	 */
	public function isAdmin($userID) {
		return $this->roleCheck(self::ROLE_ADMIN, (integer) $userID);
	}

	/**
	 * Wrapper for roleCheck specifically for Moderators.
	 *
	 * @param int $userId
	 * @return bool
	 */
	public function isModerator($userId) {
		return $this->roleCheck(self::ROLE_MODERATOR, (integer) $userId);
	}
}
