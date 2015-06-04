<?php

use newznab\db\Settings;
use newznab\utility\Utility;

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
	 * Users select queue type.
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
		return $this->pdo->query("select * from users");
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

		$this->pdo->queryExec(sprintf("DELETE from users where id = %d", $id));
	}

	public function delCartForUser($uid)
	{
		$this->pdo->queryExec(sprintf("DELETE from usercart where userid = %d", $uid));
	}

	public function delUserCategoryExclusions($uid)
	{
		$this->pdo->queryExec(sprintf("DELETE from userexcat where userid = %d", $uid));
	}

	public function delDownloadRequests($userid)
	{
		return $this->pdo->queryExec(sprintf("delete from userdownloads where userid = %d", $userid));
	}

	public function delApiRequests($userid)
	{
		return $this->pdo->queryExec(sprintf("delete from userrequests where userid = %d", $userid));
	}

	public function getRange($start, $num, $orderby, $username = '', $email = '', $host = '', $role = '')
	{
		if ($start === false)
			$limit = "";
		else
			$limit = " LIMIT " . $start . "," . $num;

		$usql = '';
		if ($username != '')
			$usql = sprintf(" and users.username like %s ", $this->pdo->escapeString("%" . $username . "%"));

		$esql = '';
		if ($email != '')
			$esql = sprintf(" and users.email like %s ", $this->pdo->escapeString("%" . $email . "%"));

		$hsql = '';
		if ($host != '')
			$hsql = sprintf(" and users.host like %s ", $this->pdo->escapeString("%" . $host . "%"));

		$rsql = '';
		if ($role != '')
			$rsql = sprintf(" and users.role = %d ", $role);

		$order = $this->getBrowseOrder($orderby);

		return $this->pdo->query(sprintf(" SELECT users.*, userroles.name as rolename from users inner join userroles on userroles.id = users.role where 1=1 %s %s %s %s AND email != 'sharing@nZEDb.com' order by %s %s" . $limit, $usql, $esql, $hsql, $rsql, $order[0], $order[1]));
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
			case 'grabs':
				$orderfield = 'grabs';
				break;
			case 'role':
				$orderfield = 'role';
				break;
			default:
				$orderfield = 'username';
				break;
		}
		$ordersort = (isset($orderArr[1]) && preg_match('/^asc|desc$/i', $orderArr[1])) ? $orderArr[1] : 'desc';

		return array($orderfield, $ordersort);
	}

	public function getCount()
	{
		$res = $this->pdo->queryOneRow("select count(id) as num from users WHERE email != 'sharing@nZEDb.com'");

		return $res["num"];
	}

	public function update($id, $uname, $email, $grabs, $role, $notes, $invites, $movieview, $musicview, $gameview, $xxxview, $consoleview, $bookview, $queueType = '', $nzbgetURL = '', $nzbgetUsername = '', $nzbgetPassword = '', $saburl = '', $sabapikey = '', $sabpriority = '', $sabapikeytype = '', $nzbvortexServerUrl = false, $nzbvortexApiKey = false, $cp_url = false, $cp_api = false, $style = 'None')
	{
		$uname = trim($uname);
		$email = trim($email);

		if (!$this->isValidUsername($uname))
			return Users::ERR_SIGNUP_BADUNAME;

		if (!$this->isValidEmail($email))
			return Users::ERR_SIGNUP_BADEMAIL;

		$res = $this->getByUsername($uname);
		if ($res)
			if ($res["id"] != $id)
				return Users::ERR_SIGNUP_UNAMEINUSE;

		$res = $this->getByEmail($email);
		if ($res)
			if ($res["id"] != $id)
				return Users::ERR_SIGNUP_EMAILINUSE;

		$sql = [];

		$sql[] = sprintf('username = %s', $this->pdo->escapeString($uname));
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

		$this->pdo->queryExec(sprintf("update users set %s where id = %d", implode(', ', $sql), $id));

		return self::SUCCESS;
	}

	public function isValidUsername($uname)
	{
		return preg_match("/^[a-z][a-z0-9]{2,}$/i", $uname);
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

	public function getByUsername($uname)
	{
		return $this->pdo->queryOneRow(sprintf("select users.*, userroles.name as rolename, userroles.apirequests, userroles.downloadrequests from users inner join userroles on userroles.id = users.role where username = %s ", $this->pdo->escapeString($uname)));
	}

	public function getByEmail($email)
	{
		return $this->pdo->queryOneRow(sprintf("select * from users where lower(email) = %s ", $this->pdo->escapeString(strtolower($email))));
	}

	public function updateUserRole($uid, $role)
	{
		$this->pdo->queryExec(sprintf("update users set role = %d where id = %d", $role, $uid));

		return Users::SUCCESS;
	}

	public function updateUserRoleChangeDate($uid, $date)
	{
		$this->pdo->queryExec(sprintf("update users SET rolechangedate = '%s' WHERE id = %d", $date, $uid));

		return Users::SUCCESS;
	}

	public function updateExpiredRoles($uprole, $downrole, $msgsubject, $msgbody)
	{
		$data = $this->pdo->query(sprintf("select id,email from users WHERE role = %d and rolechangedate < now()", $uprole));
		foreach ($data as $u) {
			Utility::sendEmail($u["email"], $msgsubject, $msgbody, $this->pdo->getSetting('email'));
			$this->pdo->queryExec(sprintf("update users SET role = %d, rolechangedate=null WHERE id = %d", $downrole, $u["id"]));
		}

		return Users::SUCCESS;
	}

	public function updateRssKey($uid)
	{

		$this->pdo->queryExec(sprintf("update users set rsstoken = md5(%s) where id = %d", $this->pdo->escapeString(uniqid()), $uid));

		return Users::SUCCESS;
	}

	public function updatePassResetGuid($id, $guid)
	{

		$this->pdo->queryExec(sprintf("update users set resetguid = %s where id = %d", $this->pdo->escapeString($guid), $id));

		return Users::SUCCESS;
	}

	public function updatePassword($id, $password)
	{

		$this->pdo->queryExec(sprintf("update users set password = %s, userseed=md5(%s) where id = %d", $this->pdo->escapeString($this->hashPassword($password)), $this->pdo->escapeString(Utility::generateUuid()), $id));

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


		return $this->pdo->queryOneRow(sprintf("select * from users where resetguid = %s ", $this->pdo->escapeString($guid)));
	}

	public function incrementGrabs($id, $num = 1)
	{

		$this->pdo->queryExec(sprintf("update users set grabs = grabs + %d where id = %d ", $num, $id));
	}

	public function getByIdAndRssToken($id, $rsstoken)
	{

		$res = $this->getById($id);

		return ($res && $res["rsstoken"] == $rsstoken ? $res : null);
	}

	public function getById($id)
	{

		$sql = sprintf("select users.*, userroles.name as rolename, userroles.hideads, userroles.canpreview, userroles.canpre, userroles.apirequests, userroles.downloadrequests, NOW() as now from users inner join userroles on userroles.id = users.role where users.id = %d ", $id);

		return $this->pdo->queryOneRow($sql);
	}

	public function getByRssToken($rsstoken)
	{


		return $this->pdo->queryOneRow(sprintf("select users.*, userroles.apirequests, userroles.downloadrequests, NOW() as now from users inner join userroles on userroles.id = users.role where users.rsstoken = %s ", $this->pdo->escapeString($rsstoken)));
	}

	public function getBrowseOrdering()
	{
		return array('username_asc', 'username_desc', 'email_asc', 'email_desc', 'host_asc', 'host_desc', 'createddate_asc', 'createddate_desc', 'lastlogin_asc', 'lastlogin_desc', 'apiaccess_asc', 'apiaccess_desc', 'grabs_asc', 'grabs_desc', 'role_asc', 'role_desc');
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

	public function signup($uname, $pass, $email, $host, $role = self::ROLE_USER, $notes, $invites = self::DEFAULT_INVITES, $invitecode = '', $forceinvitemode = false)
	{
		$site = new Sites;
		$this->site = $site->get();

		$uname = trim($uname);
		$pass = trim($pass);
		$email = trim($email);

		if (!$this->isValidUsername($uname))
			return Users::ERR_SIGNUP_BADUNAME;

		if (!$this->isValidPassword($pass))
			return Users::ERR_SIGNUP_BADPASS;

		if (!$this->isValidEmail($email)) {
			return self::ERR_SIGNUP_BADEMAIL;
		}

		$res = $this->getByUsername($uname);
		if ($res)
			return Users::ERR_SIGNUP_UNAMEINUSE;

		$res = $this->getByEmail($email);
		if ($res)
			return Users::ERR_SIGNUP_EMAILINUSE;

		//
		// make sure this is the last check, as if a further validation check failed,
		// the invite would still have been used up
		//
		$invitedby = 0;
		if (($this->pdo->getSetting('registerstatus') == Settings::REGISTER_STATUS_INVITE) && !$forceinvitemode) {
			if ($invitecode == '')
				return Users::ERR_SIGNUP_BADINVITECODE;

			$invitedby = $this->checkAndUseInvite($invitecode);
			if ($invitedby < 0)
				return Users::ERR_SIGNUP_BADINVITECODE;
		}

		return $this->add($uname, $pass, $email, $role, $notes, $host, $invites, $invitedby);
	}

	public function isValidPassword($pass)
	{
		return (strlen($pass) > 5);
	}

	public function checkAndUseInvite($invitecode)
	{
		$invite = $this->getInvite($invitecode);
		if (!$invite)
			return -1;


		$this->pdo->queryExec(sprintf("update users set invites = case when invites <= 0 then 0 else invites-1 end where id = %d ", $invite["userid"]));
		$this->deleteInvite($invitecode);

		return $invite["userid"];
	}

	public function getInvite($inviteToken)
	{


		//
		// Tidy any old invites sent greater than DEFAULT_INVITE_EXPIRY_DAYS days ago.
		//
		$this->pdo->queryExec(sprintf("DELETE from userinvite where createddate < now() - INTERVAL %d DAY", self::DEFAULT_INVITE_EXPIRY_DAYS));

		return $this->pdo->queryOneRow(
			sprintf(
				"SELECT * FROM userinvite WHERE guid = %s",
				$this->pdo->escapeString($inviteToken)
			)
		);
	}

	public function deleteInvite($inviteToken)
	{

		$this->pdo->queryExec(sprintf("DELETE from userinvite where guid = %s ", $this->pdo->escapeString($inviteToken)));
	}

	public function add($uname, $pass, $email, $role, $notes, $host, $invites = self::DEFAULT_INVITES, $invitedby = 0)
	{


		$site = new Sites();
		$this->site = $site->get();
		if ($this->pdo->getSetting('storeuserips') != "1")
			$host = "";

		if ($invitedby == 0)
			$invitedby = "null";

		$sql = sprintf("insert into users (username, password, email, role, notes, createddate, host, rsstoken, invites, invitedby, userseed) values (%s, %s, lower(%s), %d, %s, now(), %s, md5(%s), %d, %s, md5(%s))",
			$this->pdo->escapeString($uname), $this->pdo->escapeString($this->hashPassword($pass)), $this->pdo->escapeString($email), $role, $this->pdo->escapeString($notes), $this->pdo->escapeString($host), $this->pdo->escapeString(uniqid()), $invites, $invitedby, $this->pdo->escapeString(uniqid())
		);

		return $this->pdo->queryInsert($sql);
	}

	public function isLoggedIn()
	{
		if (isset($_SESSION['uid'])) {
			return true;
		} elseif (isset($_COOKIE['uid']) && isset($_COOKIE['idh'])) {
			$u = $this->getById($_COOKIE['uid']);

			if (($_COOKIE['idh'] == $this->hashSHA1($u["userseed"] . $_COOKIE['uid'])) && ($u["role"] != Users::ROLE_DISABLED)) {
				$this->login($_COOKIE['uid'], $_SERVER['REMOTE_ADDR']);
			}
		}

		return isset($_SESSION['uid']);
	}

	public function login($uid, $host = "", $remember = "")
	{
		$_SESSION['uid'] = $uid;

		$site = new Sites();
		$this->site = $site->get();

		if ($this->pdo->getSetting('storeuserips') != "1")
			$host = '';

		$this->updateSiteAccessed($uid, $host);

		if ($remember == 1)
			$this->setCookies($uid);
	}

	public function updateSiteAccessed($uid, $host = "")
	{

		$hostSql = '';
		if ($host != '')
			$hostSql = sprintf(', host = %s', $this->pdo->escapeString($host));

		$this->pdo->queryExec(sprintf("update users set lastlogin = now() %s where id = %d ", $hostSql, $uid));
	}

	public function setCookies($uid)
	{
		$u = $this->getById($uid);
		$idh = $this->hashSHA1($u["userseed"] . $uid);
		setcookie('uid', $uid, (time() + 2592000), '/', $_SERVER['SERVER_NAME'], (isset($_SERVER['HTTPS']) ? true : false));
		setcookie('idh', $idh, (time() + 2592000), '/', $_SERVER['SERVER_NAME'], (isset($_SERVER['HTTPS']) ? true : false));
	}

	public function currentUserId()
	{
		return (isset($_SESSION['uid']) ? $_SESSION['uid'] : -1);
	}

	public function logout()
	{
		session_unset();
		session_destroy();
		setcookie('uid', '', (time() - 2592000), '/', $_SERVER['SERVER_NAME'], (isset($_SERVER['HTTPS']) ? true : false));
		setcookie('idh', '', (time() - 2592000), '/', $_SERVER['SERVER_NAME'], (isset($_SERVER['HTTPS']) ? true : false));
	}

	public function updateApiAccessed($uid)
	{

		$this->pdo->queryExec(sprintf("update users set apiaccess = now() where id = %d ", $uid));
	}

	public function addCart($uid, $releaseid)
	{

		$sql = sprintf("insert into usercart (userid, releaseid, createddate) values (%d, %d, now())", $uid, $releaseid);

		return $this->pdo->queryInsert($sql);
	}

	public function getCart($uid, $releaseid = "")
	{

		if ($releaseid != "")
			$releaseid = " and releases.id = " . $this->pdo->escapeString($releaseid);

		return $this->pdo->query(sprintf("select usercart.*, releases.searchname,releases.guid from usercart inner join releases on releases.id = usercart.releaseid where userid = %d %s", $uid, $releaseid));
	}

	public function delCartByGuid($guids, $uid)
	{
		if (!is_array($guids))
			return false;

		$del = [];
		foreach ($guids as $id) {
			$id = sprintf("%s", $this->pdo->escapeString($id));
			if (!empty($id))
				$del[] = $id;
		}

		$this->pdo->query(sprintf("delete from usercart where userid = %d and releaseid IN (select id from releases where guid IN (%s)) ", $uid, implode(',', $del)));
	}

	public function delCartByUserAndRelease($guid, $uid)
	{
		$rel = $this->pdo->queryOneRow(sprintf("select id from releases where guid = %s", $this->pdo->escapeString($guid)));
		if ($rel)
			$this->pdo->queryExec(sprintf("DELETE FROM usercart WHERE userid = %d AND releaseid = %d", $uid, $rel["id"]));
	}

	public function delCartForRelease($rid)
	{
		$this->pdo->queryExec(sprintf("DELETE from usercart where releaseid = %d", $rid));
	}

	public function addCategoryExclusions($uid, $catids)
	{
		$this->delUserCategoryExclusions($uid);
		if (count($catids) > 0) {
			foreach ($catids as $catid) {
				$this->pdo->queryInsert(sprintf("insert into userexcat (userid, categoryid, createddate) values (%d, %d, now())", $uid, $catid));
			}
		}
	}

	public function getRoleCategoryExclusion($role)
	{
		$ret = [];
		$data = $this->pdo->query(sprintf("select categoryid from roleexcat where role = %d", $role));
		foreach ($data as $d)
			$ret[] = $d["categoryid"];

		return $ret;
	}

	public function addRoleCategoryExclusions($role, $catids)
	{
		$this->delRoleCategoryExclusions($role);
		if (count($catids) > 0) {
			foreach ($catids as $catid) {
				$this->pdo->queryInsert(sprintf("insert into roleexcat (role, categoryid, createddate) values (%d, %d, now())", $role, $catid));
			}
		}
	}

	public function delRoleCategoryExclusions($role)
	{
		$this->pdo->queryExec(sprintf("DELETE from roleexcat where role = %d", $role));
	}

	public function getCategoryExclusionNames($uid)
	{
		$data = $this->getCategoryExclusion($uid);
		$ret = [];

		if ($data) {
			$category = new Category();
			$data = $category->getByIds($data);
			foreach ($data as $d)
				$ret[] = $d["title"];
		}

		return $ret;
	}

	public function getCategoryExclusion($uid)
	{
		$ret = [];
		$data = $this->pdo->query(sprintf("select categoryid from userexcat where userid = %d union distinct select categoryid from roleexcat inner join users on users.role = roleexcat.role where users.id = %d", $uid, $uid));
		foreach ($data as $d)
			$ret[] = $d["categoryid"];

		return $ret;
	}

	public function delCategoryExclusion($uid, $catid)
	{
		$this->pdo->queryExec(sprintf("DELETE from userexcat where userid = %d and categoryid = %d", $uid, $catid));
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
		$this->pdo->queryInsert(sprintf("insert into userinvite (guid, userid, createddate) values (%s, %d, now())", $this->pdo->escapeString($inviteToken), $uid));
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

	public function getUsersByMonth()
	{

		return $this->pdo->query("SELECT DATE_FORMAT(createddate, '%M %Y') AS mth, COUNT(*) AS num
							FROM users
							WHERE createddate IS NOT NULL AND createddate != '0000-00-00 00:00:00'
							GROUP BY DATE_FORMAT(createddate, '%M %Y')
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

		$sql = sprintf("select hosthash, group_concat(userid) as user_string, group_concat(username) as user_names
							from
							(
							select hosthash, userid, username from userdownloads left outer join users on users.id = userdownloads.userid where hosthash is not null and hosthash not in %s group by hosthash, userid
							union distinct
							select hosthash, userid, username from userrequests left outer join users on users.id = userrequests.userid where hosthash is not null and hosthash not in %s group by hosthash, userid
							) x
							group by hosthash
							having CAST((LENGTH(group_concat(userid)) - LENGTH(REPLACE(group_concat(userid), ',', ''))) / LENGTH(',') AS UNSIGNED) < 9
							order by CAST((LENGTH(group_concat(userid)) - LENGTH(REPLACE(group_concat(userid), ',', ''))) / LENGTH(',') AS UNSIGNED) desc
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
		return $this->pdo->query("select ur.name, count(u.id) as num from users u
							inner join userroles ur on ur.id = u.role
							group by ur.name
							order by count(u.id) desc;"
		);
	}

	public function getLoginCountsByMonth()
	{
		return $this->pdo->query("select 'Login' as type,
			sum(case when lastlogin > curdate() - INTERVAL 1 DAY then 1 else 0 end) as 1day,
			sum(case when lastlogin > curdate() - INTERVAL 7 DAY and lastlogin < curdate() - INTERVAL 1 DAY then 1 else 0 end) as 7day,
			sum(case when lastlogin > curdate() - INTERVAL 1 MONTH and lastlogin < curdate() - INTERVAL 7 DAY then 1 else 0 end) as 1month,
			sum(case when lastlogin > curdate() - INTERVAL 3 MONTH and lastlogin < curdate() - INTERVAL 1 MONTH then 1 else 0 end) as 3month,
			sum(case when lastlogin > curdate() - INTERVAL 6 MONTH and lastlogin < curdate() - INTERVAL 3 MONTH then 1 else 0 end) as 6month,
			sum(case when lastlogin < curdate() - INTERVAL 6 MONTH then 1 else 0 end) as 12month
			from users
			union
			select 'Api' as type,
			sum(case when apiaccess > curdate() - INTERVAL 1 DAY then 1 else 0 end) as 1day,
			sum(case when apiaccess > curdate() - INTERVAL 7 DAY and apiaccess < curdate() - INTERVAL 1 DAY then 1 else 0 end) as 7day,
			sum(case when apiaccess > curdate() - INTERVAL 1 MONTH and apiaccess < curdate() - INTERVAL 7 DAY then 1 else 0 end) as 1month,
			sum(case when apiaccess > curdate() - INTERVAL 3 MONTH and apiaccess < curdate() - INTERVAL 1 MONTH then 1 else 0 end) as 3month,
			sum(case when apiaccess > curdate() - INTERVAL 6 MONTH and apiaccess < curdate() - INTERVAL 3 MONTH then 1 else 0 end) as 6month,
			sum(case when apiaccess < curdate() - INTERVAL 6 MONTH then 1 else 0 end) as 12month
			from users"
		);
	}

	public function getRoles()
	{
		return $this->pdo->query("select * from userroles");
	}

	public function getRoleById($id)
	{
		$sql = sprintf("select * from userroles where id = %d", $id);

		return $this->pdo->queryOneRow($sql);
	}

	public function addRole($name, $apirequests, $downloadrequests, $defaultinvites, $canpreview, $canpre, $hideads)
	{
		$sql = sprintf("insert into userroles (name, apirequests, downloadrequests, defaultinvites, canpreview, canpre, hideads) VALUES (%s, %d, %d, %d, %d, %d)", $this->pdo->escapeString($name), $apirequests, $downloadrequests, $defaultinvites, $canpreview, $canpre, $hideads);

		return $this->pdo->queryInsert($sql);
	}

	public function updateRole($id, $name, $apirequests, $downloadrequests, $defaultinvites, $isdefault, $canpreview, $canpre, $hideads)
	{
		if ($isdefault == 1) {
			$this->pdo->queryExec("update userroles set isdefault=0");
		}
		return $this->pdo->queryExec(sprintf("update userroles set name=%s, apirequests=%d, downloadrequests=%d, defaultinvites=%d, isdefault=%d, canpreview=%d, canpre=%d, hideads=%d WHERE id=%d", $this->pdo->escapeString($name), $apirequests, $downloadrequests, $defaultinvites, $isdefault, $canpreview, $canpre, $hideads, $id));
	}

	public function deleteRole($id)
	{
		$res = $this->pdo->query(sprintf("select id from users where role = %d", $id));
		if (sizeof($res) > 0) {
			$userids = [];
			foreach ($res as $user)
				$userids[] = $user['id'];
			$defaultrole = $this->getDefaultRole();
			$this->pdo->queryExec(sprintf("update users set role=%d where id IN (%s)", $defaultrole['id'], implode(',', $userids)));
		}

		return $this->pdo->queryExec(sprintf("DELETE from userroles WHERE id=%d", $id));
	}

	public function getDefaultRole()
	{
		return $this->pdo->queryOneRow("select * from userroles where isdefault = 1");
	}

	public function getApiRequests($userid)
	{
		//clear old requests
		//$this->pdo->queryExec(sprintf("DELETE FROM userrequests WHERE (userid = %d AND timestamp < DATE_SUB(NOW(), INTERVAL 1 DAY))", $userid));
		$this->pdo->queryExec(sprintf("DELETE FROM userrequests WHERE timestamp < DATE_SUB(NOW(), INTERVAL 1 DAY)", $userid));
		$sql = sprintf("select COUNT(id) as num, TIME_TO_SEC(TIMEDIFF(DATE_ADD(MIN(TIMESTAMP), INTERVAL 1 DAY), NOW())) AS nextrequest FROM userrequests WHERE userid = %d AND timestamp > DATE_SUB(NOW(), INTERVAL 1 DAY)", $userid);
		return $this->pdo->queryOneRow($sql);
	}

	public function addApiRequest($userid, $request)
	{
		$sql = sprintf("insert into userrequests (userid, request, timestamp) VALUES (%d, %s, now())", $userid, $this->pdo->escapeString($request));
		return $this->pdo->queryInsert($sql);
	}

	/**
	 * deletes old rows from the userrequest and userdownloads tables.
	 * if site->userdownloadpurgedays set to 0 then all release history is removed but
	 * the download/request rows must remain for at least one day to allow the role based
	 * limits to apply.
	 */
	public function pruneRequestHistory($days = 0)
	{
		if ($days == 0) {
			$days = 1;
			$this->pdo->queryExec("update userdownloads set releaseid = null");
		}

		$this->pdo->queryExec(sprintf("DELETE from userrequests where timestamp < DATE_SUB(NOW(), INTERVAL %d DAY)", $days));
		$this->pdo->queryExec(sprintf("DELETE from userdownloads where timestamp < DATE_SUB(NOW(), INTERVAL %d DAY)", $days));
	}

	/**
	 * Get the count of how many NZB's the user has downloaded in the past day.
	 *
	 * @param int $userID
	 *
	 * @return array|bool
	 */
	public function getDownloadRequests($userID)
	{
		// Clear old requests.
		$this->pdo->queryExec(
			sprintf(
				'DELETE FROM userdownloads WHERE userid = %d AND timestamp < DATE_SUB(NOW(), INTERVAL 1 DAY)',
				$userID
			)
		);
		return $this->pdo->queryOneRow(
			sprintf(
				'SELECT COUNT(id) AS num FROM userdownloads WHERE userid = %d AND timestamp > DATE_SUB(NOW(), INTERVAL 1 DAY)',
				$userID
			)
		);
	}

	public function getDownloadRequestsForUserAndAllHostHashes($userid)
	{
		/*$sql = sprintf("select distinct hosthash from userdownloads where userid = %d and hosthash is not null and hosthash != ''", $userid);
		$rows = $this->pdo->query($sql);
		$hashsql = "";
		foreach ($rows as $row)
			$hashsql .= sprintf("userdownloads.hosthash = %s or ", $this->pdo->escapeString($row["hosthash"]));

		$hashsql .= " 1=2 ";
		*/
		$sql = sprintf("select userdownloads.*, releases.guid, releases.searchname from userdownloads left outer join releases on releases.id = userdownloads.releaseid where userdownloads.userid = %d order by userdownloads.timestamp desc", $userid);

		return $this->pdo->query($sql);
	}

	/**
	 * If a user downloads a NZB, log it.
	 *
	 * @param int $userID id of the user.
	 *
	 * @return bool|int
	 */
	public function addDownloadRequest($userID, $releaseID)
	{
		return $this->pdo->queryInsert(
			sprintf(
				"INSERT INTO userdownloads (userid, releaseid, timestamp) VALUES (%d, %d, NOW())",
				$userID,
				$releaseID
			)
		);
	}

	public function delDownloadRequestsForRelease($releaseID)
	{
		return $this->pdo->queryInsert(sprintf("delete from userdownloads where releaseid = %d", $releaseID));
	}

	/**
	 * Checks if a user is a specific role.
	 *
	 * @notes Uses type of $user to denote identifier. if string: username, if int: userid
	 * @param int $roleID
	 * @param string|int $user
	 * @return bool
	 */
	public function roleCheck($roleID, $user) {

		if (is_string($user) && strlen($user) > 0) {
			$user = $this->pdo->escapeString($user);
			$querySuffix = "username = '$user'";
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