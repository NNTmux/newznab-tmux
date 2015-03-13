<?php
require_once(WWW_DIR . "/lib/framework/db.php");
require_once(WWW_DIR . "/lib/site.php");
require_once(WWW_DIR . "/lib/releases.php");
require_once(WWW_DIR . "/lib/forum.php");
require_once(WWW_DIR . "/lib/util.php");
require_once(WWW_DIR . "/lib/releasecomments.php");
require_once(WWW_DIR . "/lib/usermovies.php");
require_once(WWW_DIR . "/lib/userseries.php");

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

	const DEFAULT_INVITES = 1;
	const DEFAULT_INVITE_EXPIRY_DAYS = 7;

	const SALTLEN = 4;
	const SHA1LEN = 40;

	/**
	 * Users select queue type.
	 */
	const QUEUE_NONE = 0;
	const QUEUE_SABNZBD = 1;
	const QUEUE_NZBGET = 2;

	public static function checkPassword($password, $hash)
	{
		$salt = substr($hash, -self::SALTLEN);
		$site = new Sites();
		$s = $site->get();

		return self::hashSHA1($s->siteseed . $password . $salt . $s->siteseed) === substr($hash, 0, self::SHA1LEN);
	}

	public function get()
	{
		$db = new DB();

		return $db->query("select * from users");
	}

	public function delete($id)
	{
		$db = new DB();
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

		$db->exec(sprintf("DELETE from users where id = %d", $id));
	}

	public function delCartForUser($uid)
	{
		$db = new DB();
		$db->exec(sprintf("DELETE from usercart where userid = %d", $uid));
	}

	public function delUserCategoryExclusions($uid)
	{
		$db = new DB();
		$db->exec(sprintf("DELETE from userexcat where userid = %d", $uid));
	}

	public function delDownloadRequests($userid)
	{
		$db = new DB();

		return $db->queryInsert(sprintf("delete from userdownloads where userid = %d", $userid));
	}

	public function delApiRequests($userid)
	{
		$db = new DB();

		return $db->queryInsert(sprintf("delete from userrequests where userid = %d", $userid));
	}

	public function getRange($start, $num, $orderby, $username = '', $email = '', $host = '', $role = '')
	{
		$db = new DB();

		if ($start === false)
			$limit = "";
		else
			$limit = " LIMIT " . $start . "," . $num;

		$usql = '';
		if ($username != '')
			$usql = sprintf(" and users.username like %s ", $db->escapeString("%" . $username . "%"));

		$esql = '';
		if ($email != '')
			$esql = sprintf(" and users.email like %s ", $db->escapeString("%" . $email . "%"));

		$hsql = '';
		if ($host != '')
			$hsql = sprintf(" and users.host like %s ", $db->escapeString("%" . $host . "%"));

		$rsql = '';
		if ($role != '')
			$rsql = sprintf(" and users.role = %d ", $role);

		$order = $this->getBrowseOrder($orderby);

		return $db->query(sprintf(" SELECT users.*, userroles.name as rolename from users inner join userroles on userroles.id = users.role where 1=1 %s %s %s %s AND email != 'sharing@nZEDb.com' order by %s %s" . $limit, $usql, $esql, $hsql, $rsql, $order[0], $order[1]));
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
		$db = new DB();
		$res = $db->queryOneRow("select count(id) as num from users WHERE email != 'sharing@nZEDb.com'");

		return $res["num"];
	}

	public function update($id, $uname, $email, $grabs, $role, $notes, $invites, $movieview, $musicview, $gameview, $xxxview, $consoleview, $bookview, $queueType = '', $nzbgetURL = '', $nzbgetUsername = '', $nzbgetPassword = '', $saburl = '', $sabapikey = '', $sabpriority = '', $sabapikeytype = '', $nzbvortexServerUrl = false, $nzbvortexApiKey = false, $cp_url = false, $cp_api = false)
	{
		$db = new DB();

		$uname = trim($uname);
		$email = trim($email);

		if (!$this->isValidUsername($uname))
			return Users::ERR_SIGNUP_BADUNAME;

		if (!$this->isValidEmail($email))
			return Users::ERR_SIGNUP_BADEMAIL;

		$res = $this->getByUsername($uname);
		if ($res)
			if ($res["ID"] != $id)
				return Users::ERR_SIGNUP_UNAMEINUSE;

		$res = $this->getByEmail($email);
		if ($res)
			if ($res["ID"] != $id)
				return Users::ERR_SIGNUP_EMAILINUSE;

		$sql = array();

		$sql[] = sprintf('username = %s', $db->escapeString($uname));
		$sql[] = sprintf('email = %s', $db->escapeString($email));
		$sql[] = sprintf('grabs = %d', $grabs);
		$sql[] = sprintf('role = %d', $role);
		$sql[] = sprintf('notes = %s', $db->escapeString(substr($notes, 0, 255)));
		$sql[] = sprintf('invites = %d', $invites);
		$sql[] = sprintf('movieview = %d', $movieview);
		$sql[] = sprintf('musicview = %d', $musicview);
		$sql[] = sprintf('gameview = %d', $gameview);
		$sql[] = sprintf('xxxview = %d', $xxxview);
		$sql[] = sprintf('consoleview = %d', $consoleview);
		$sql[] = sprintf('bookview = %d', $bookview);

		if ($queueType !== '') {
			$sql[] = sprintf('queuetype = %d', $queueType);
		}

		if ($nzbgetURL !== '') {
			$sql[] = sprintf('nzbgeturl = %s', $db->escapeString($nzbgetURL));
		}

		$sql[] = sprintf('nzbgetusername = %s', $db->escapeString($nzbgetUsername));
		$sql[] = sprintf('nzbgetpassword = %s', $db->escapeString($nzbgetPassword));

		if ($saburl !== '') {
			$sql[] = sprintf('saburl = %s', $db->escapeString($saburl));
		}
		if ($sabapikey !== '') {
			$sql[] = sprintf('sabapikey = %s', $db->escapeString($sabapikey));
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
			$sql[] = sprintf('cp_url = %s', $db->escapeString($cp_url));
		}
		if ($cp_api !== false) {
			$sql[] = sprintf('cp_api = %s', $db->escapeString($cp_api));
		}

		$db->queryExec(sprintf("update users set %s where id = %d", implode(', ', $sql), $id));

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
		$db = new DB();

		return $db->queryOneRow(sprintf("select users.*, userroles.name as rolename, userroles.apirequests, userroles.downloadrequests from users inner join userroles on userroles.id = users.role where username = %s ", $db->escapeString($uname)));
	}

	public function getByEmail($email)
	{
		$db = new DB();

		return $db->queryOneRow(sprintf("select * from users where lower(email) = %s ", $db->escapeString(strtolower($email))));
	}

	public function updateUserRole($uid, $role)
	{
		$db = new DB();
		$db->exec(sprintf("update users set role = %d where id = %d", $role, $uid));

		return Users::SUCCESS;
	}

	public function updateUserRoleChangeDate($uid, $date)
	{
		$db = new DB();
		$db->exec(sprintf("update users SET rolechangedate = '%s' WHERE id = %d", $date, $uid));

		return Users::SUCCESS;
	}

	public function updateExpiredRoles($uprole, $downrole, $msgsubject, $msgbody)
	{
		$db = new DB();

		$site = new Sites;
		$s = $site->get();

		$data = $db->query(sprintf("select id,email from users WHERE role = %d and rolechangedate < now()", $uprole));
		foreach ($data as $u) {
			Utility::sendEmail($u["email"], $msgsubject, $msgbody, $s->email);
			$db->exec(sprintf("update users SET role = %d, rolechangedate=null WHERE id = %d", $downrole, $u["id"]));
		}

		return Users::SUCCESS;
	}

	public function updateRssKey($uid)
	{
		$db = new DB();
		$db->exec(sprintf("update users set rsstoken = md5(%s) where id = %d", $db->escapeString(uniqid()), $uid));

		return Users::SUCCESS;
	}

	public function updatePassResetGuid($id, $guid)
	{
		$db = new DB();
		$db->exec(sprintf("update users set resetguid = %s where id = %d", $db->escapeString($guid), $id));

		return Users::SUCCESS;
	}

	public function updatePassword($id, $password)
	{
		$db = new DB();
		$db->exec(sprintf("update users set password = %s, userseed=md5(%s) where id = %d", $db->escapeString($this->hashPassword($password)), $db->escapeString(generateUuid()), $id));

		return Users::SUCCESS;
	}

	public static function hashPassword($password)
	{
		$salt = Users::randomKey(Users::SALTLEN);
		$site = new Sites();
		$s = $site->get();

		return Users::hashSHA1($s->siteseed . $password . $salt . $s->siteseed) . $salt;
	}

	function randomKey($amount)
	{
		$keyset = "abcdefghijklmABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
		$randkey = "";
		for ($i = 0; $i < $amount; $i++)
			$randkey .= substr($keyset, rand(0, strlen($keyset) - 1), 1);

		return $randkey;
	}

	public static function hashSHA1($string)
	{
		return sha1($string);
	}

	public function getByPassResetGuid($guid)
	{
		$db = new DB();

		return $db->queryOneRow(sprintf("select * from users where resetguid = %s ", $db->escapeString($guid)));
	}

	public function incrementGrabs($id, $num = 1)
	{
		$db = new DB();
		$db->exec(sprintf("update users set grabs = grabs + %d where id = %d ", $num, $id));
	}

	public function getByIdAndRssToken($id, $rsstoken)
	{
		$db = new DB();
		$res = $this->getById($id);

		return ($res && $res["rsstoken"] == $rsstoken ? $res : null);
	}

	public function getById($id)
	{
		$db = new DB();
		$sql = sprintf("select users.*, userroles.name as rolename, userroles.hideads, userroles.canpreview, userroles.canpre, userroles.apirequests, userroles.downloadrequests, NOW() as now from users inner join userroles on userroles.id = users.role where users.id = %d ", $id);

		return $db->queryOneRow($sql);
	}

	public function getByRssToken($rsstoken)
	{
		$db = new DB();

		return $db->queryOneRow(sprintf("select users.*, userroles.apirequests, userroles.downloadrequests, NOW() as now from users inner join userroles on userroles.id = users.role where users.rsstoken = %s ", $db->escapeString($rsstoken)));
	}

	public function getBrowseOrdering()
	{
		return array('username_asc', 'username_desc', 'email_asc', 'email_desc', 'host_asc', 'host_desc', 'createddate_asc', 'createddate_desc', 'lastlogin_asc', 'lastlogin_desc', 'apiaccess_asc', 'apiaccess_desc', 'grabs_asc', 'grabs_desc', 'role_asc', 'role_desc');
	}

	public function isDisabled($username)
	{
		$db = new DB();
		$role = $db->queryOneRow(sprintf("select role as role from users where username = %s ", $db->escapeString($username)));

		return ($role["role"] == Users::ROLE_DISABLED);
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

	public function signup($uname, $pass, $email, $host, $role = self::ROLE_USER, $notes, $invites = self::DEFAULT_INVITES, $invitecode = '', $forceinvitemode = false, $recaptcha_challenge = false, $recaptcha_response = false, $skip_recaptcha = false)
	{
		$site = new Sites;
		$s = $site->get();

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

		// Check Captcha
		if (!$skip_recaptcha) {
			if (!$this->isValidCaptcha($s, $recaptcha_challenge, $recaptcha_response))
				return Users::ERR_SIGNUP_BADCAPTCHA;
		}

		//
		// make sure this is the last check, as if a further validation check failed,
		// the invite would still have been used up
		//
		$invitedby = 0;
		if (($s->registerstatus == Sites::REGISTER_STATUS_INVITE) && !$forceinvitemode) {
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

	public function isValidCaptcha($site, $challenge, $response)
	{
		if ($site->registerrecaptcha != 1)
			return true;

		require_once(WWW_DIR . "/lib/recaptchalib.php");

		$resp = recaptcha_check_answer($site->recaptchaprivatekey, $_SERVER["REMOTE_ADDR"], $challenge, $response);

		return $resp->is_valid;
	}

	public function checkAndUseInvite($invitecode)
	{
		$invite = $this->getInvite($invitecode);
		if (!$invite)
			return -1;

		$db = new DB();
		$db->exec(sprintf("update users set invites = case when invites <= 0 then 0 else invites-1 end where id = %d ", $invite["userid"]));
		$this->deleteInvite($invitecode);

		return $invite["userid"];
	}

	public function getInvite($inviteToken)
	{
		$db = new DB();

		//
		// Tidy any old invites sent greater than DEFAULT_INVITE_EXPIRY_DAYS days ago.
		//
		$db->queryExec(sprintf("DELETE from userinvite where createddate < now() - INTERVAL %d DAY", self::DEFAULT_INVITE_EXPIRY_DAYS));

		return $db->queryOneRow(
			sprintf(
				"SELECT * FROM userinvite WHERE guid = %s",
				$db->escapeString($inviteToken)
			)
		);
	}

	public function deleteInvite($inviteToken)
	{
		$db = new DB();
		$db->exec(sprintf("DELETE from userinvite where guid = %s ", $db->escapeString($inviteToken)));
	}

	public function add($uname, $pass, $email, $role, $notes, $host, $invites = self::DEFAULT_INVITES, $invitedby = 0)
	{
		$db = new DB();

		$site = new Sites();
		$s = $site->get();
		if ($s->storeuserips != "1")
			$host = "";

		if ($invitedby == 0)
			$invitedby = "null";

		$sql = sprintf("insert into users (username, password, email, role, notes, createddate, host, rsstoken, invites, invitedby, userseed) values (%s, %s, lower(%s), %d, %s, now(), %s, md5(%s), %d, %s, md5(%s))",
			$db->escapeString($uname), $db->escapeString($this->hashPassword($pass)), $db->escapeString($email), $role, $db->escapeString($notes), $db->escapeString($host), $db->escapeString(uniqid()), $invites, $invitedby, $db->escapeString(uniqid())
		);

		return $db->queryInsert($sql);
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
		$s = $site->get();

		if ($s->storeuserips != "1")
			$host = '';

		$this->updateSiteAccessed($uid, $host);

		if ($remember == 1)
			$this->setCookies($uid);
	}

	public function updateSiteAccessed($uid, $host = "")
	{
		$db = new DB();
		$hostSql = '';
		if ($host != '')
			$hostSql = sprintf(', host = %s', $db->escapeString($host));

		$db->exec(sprintf("update users set lastlogin = now() %s where id = %d ", $hostSql, $uid));
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
		$db = new DB();
		$db->exec(sprintf("update users set apiaccess = now() where id = %d ", $uid));
	}

	public function addCart($uid, $releaseid)
	{
		$db = new DB();
		$sql = sprintf("insert into usercart (userid, releaseid, createddate) values (%d, %d, now())", $uid, $releaseid);

		return $db->queryInsert($sql);
	}

	public function getCart($uid, $releaseid = "")
	{
		$db = new DB();
		if ($releaseid != "")
			$releaseid = " and releases.id = " . $db->escapeString($releaseid);

		return $db->query(sprintf("select usercart.*, releases.searchname,releases.guid from usercart inner join releases on releases.id = usercart.releaseid where userid = %d %s", $uid, $releaseid));
	}

	public function delCartByGuid($guids, $uid)
	{
		$db = new DB();
		if (!is_array($guids))
			return false;

		$del = array();
		foreach ($guids as $id) {
			$id = sprintf("%s", $db->escapeString($id));
			if (!empty($id))
				$del[] = $id;
		}

		$sql = sprintf("delete from usercart where userid = %d and releaseid IN (select id from releases where guid IN (%s)) ", $uid, implode(',', $del));
		$db->query($sql);
	}

	public function delCartByUserAndRelease($guid, $uid)
	{
		$db = new DB();
		$rel = $db->queryOneRow(sprintf("select id from releases where guid = %s", $db->escapeString($guid)));
		if ($rel)
			$db->exec(sprintf("DELETE FROM usercart WHERE userid = %d AND releaseid = %d", $uid, $rel["id"]));
	}

	public function delCartForRelease($rid)
	{
		$db = new DB();
		$db->exec(sprintf("DELETE from usercart where releaseid = %d", $rid));
	}

	public function addCategoryExclusions($uid, $catids)
	{
		$db = new DB();
		$this->delUserCategoryExclusions($uid);
		if (count($catids) > 0) {
			foreach ($catids as $catid) {
				$db->queryInsert(sprintf("insert into userexcat (userid, categoryID, createddate) values (%d, %d, now())", $uid, $catid));
			}
		}
	}

	public function getRoleCategoryExclusion($role)
	{
		$db = new DB();
		$ret = array();
		$data = $db->query(sprintf("select categoryID from roleexcat where role = %d", $role));
		foreach ($data as $d)
			$ret[] = $d["categoryID"];

		return $ret;
	}

	public function addRoleCategoryExclusions($role, $catids)
	{
		$db = new DB();
		$this->delRoleCategoryExclusions($role);
		if (count($catids) > 0) {
			foreach ($catids as $catid) {
				$db->queryInsert(sprintf("insert into roleexcat (role, categoryID, createddate) values (%d, %d, now())", $role, $catid));
			}
		}
	}

	public function delRoleCategoryExclusions($role)
	{
		$db = new DB();
		$db->exec(sprintf("DELETE from roleexcat where role = %d", $role));
	}

	public function getCategoryExclusionNames($uid)
	{
		$data = $this->getCategoryExclusion($uid);
		$ret = array();

		if ($data) {
			$db = new DB();
			$category = new Category();
			$data = $category->getByIds($data);
			foreach ($data as $d)
				$ret[] = $d["title"];
		}

		return $ret;
	}

	public function getCategoryExclusion($uid)
	{
		$db = new DB();
		$ret = array();
		$data = $db->query(sprintf("select categoryID from userexcat where userid = %d union distinct select categoryID from roleexcat inner join users on users.role = roleexcat.role where users.id = %d", $uid, $uid));
		foreach ($data as $d)
			$ret[] = $d["categoryID"];

		return $ret;
	}

	public function delCategoryExclusion($uid, $catid)
	{
		$db = new DB();
		$db->exec(sprintf("DELETE from userexcat where userid = %d and categoryID = %d", $uid, $catid));
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
		$db = new DB();
		$db->queryInsert(sprintf("insert into userinvite (guid, userid, createddate) values (%s, %d, now())", $db->escapeString($inviteToken), $uid));
	}

	public function getTopGrabbers()
	{
		$db = new DB();

		return $db->query("SELECT id, username, SUM(grabs) as grabs FROM users
							GROUP BY id, username
							HAVING SUM(grabs) > 0
							ORDER BY grabs DESC
							LIMIT 10"
		);
	}

	public function getUsersByMonth()
	{
		$db = new DB();

		return $db->query("SELECT DATE_FORMAT(createddate, '%M %Y') AS mth, COUNT(*) AS num
							FROM users
							WHERE createddate IS NOT NULL AND createddate != '0000-00-00 00:00:00'
							GROUP BY DATE_FORMAT(createddate, '%M %Y')
							ORDER BY createddate DESC"
		);
	}

	public function getUsersByHostHash()
	{
		$db = new DB();

		$site = new Sites();
		$s = $site->get();
		$ipsql = "('-1')";

		if ($s->userhostexclusion != '') {
			$ipsql = "";
			$ips = explode(",", $s->userhostexclusion);
			foreach ($ips as $ip) {
				$ipsql .= $db->escapeString($this->getHostHash($ip, $s->siteseed)) . ",";
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

		return $db->query($sql);
	}

	public static function getHostHash($host, $siteseed = "")
	{
		if ($siteseed == "") {
			$site = new Sites();
			$s = $site->get();
			$siteseed = $s->siteseed;
		}

		return self::hashSHA1($siteseed . $host . $siteseed);
	}

	public function getUsersByRole()
	{
		$db = new DB();

		return $db->query("select ur.name, count(u.id) as num from users u
							inner join userroles ur on ur.id = u.role
							group by ur.name
							order by count(u.id) desc;"
		);
	}

	public function getLoginCountsByMonth()
	{
		$db = new DB();

		return $db->query("select 'Login' as type,
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
		$db = new DB();

		return $db->query("select * from userroles");
	}

	public function getRoleById($id)
	{
		$db = new DB();
		$sql = sprintf("select * from userroles where id = %d", $id);

		return $db->queryOneRow($sql);
	}

	public function addRole($name, $apirequests, $downloadrequests, $defaultinvites, $canpreview, $canpre, $hideads)
	{
		$db = new DB();
		$sql = sprintf("insert into userroles (name, apirequests, downloadrequests, defaultinvites, canpreview, canpre, hideads) VALUES (%s, %d, %d, %d, %d, %d)", $db->escapeString($name), $apirequests, $downloadrequests, $defaultinvites, $canpreview, $canpre, $hideads);

		return $db->queryInsert($sql);
	}

	public function updateRole($id, $name, $apirequests, $downloadrequests, $defaultinvites, $isdefault, $canpreview, $canpre, $hideads)
	{
		$db = new DB();
		if ($isdefault == 1)
			$db->exec("update userroles set isdefault=0");

		return $db->exec(sprintf("update userroles set name=%s, apirequests=%d, downloadrequests=%d, defaultinvites=%d, isdefault=%d, canpreview=%d, canpre=%d, hideads=%d WHERE id=%d", $db->escapeString($name), $apirequests, $downloadrequests, $defaultinvites, $isdefault, $canpreview, $canpre, $hideads, $id));
	}

	public function deleteRole($id)
	{
		$db = new DB();
		$res = $db->query(sprintf("select id from users where role = %d", $id));
		if (sizeof($res) > 0) {
			$userids = array();
			foreach ($res as $user)
				$userids[] = $user['id'];

			$defaultrole = $this->getDefaultRole();
			$db->exec(sprintf("update users set role=%d where id IN (%s)", $defaultrole['id'], implode(',', $userids)));
		}

		return $db->exec(sprintf("DELETE from userroles WHERE id=%d", $id));
	}

	public function getDefaultRole()
	{
		$db = new DB();

		return $db->queryOneRow("select * from userroles where isdefault = 1");
	}

	public function getApiRequests($userid)
	{
		$db = new DB();
		//clear old requests
		//$db->exec(sprintf("DELETE FROM userrequests WHERE (userid = %d AND timestamp < DATE_SUB(NOW(), INTERVAL 1 DAY))", $userid));
		$db->exec(sprintf("DELETE FROM userrequests WHERE timestamp < DATE_SUB(NOW(), INTERVAL 1 DAY)", $userid));

		$sql = sprintf("select COUNT(id) as num, TIME_TO_SEC(TIMEDIFF(DATE_ADD(MIN(TIMESTAMP), INTERVAL 1 DAY), NOW())) AS nextrequest FROM userrequests WHERE userid = %d AND timestamp > DATE_SUB(NOW(), INTERVAL 1 DAY)", $userid);

		return $db->queryOneRow($sql);
	}

	public function addApiRequest($userid, $request)
	{
		$db = new DB();

		$sql = sprintf("insert into userrequests (userid, request, timestamp) VALUES (%d, %s, now())", $userid, $db->escapeString($request));

		return $db->queryInsert($sql);
	}

	/**
	 * deletes old rows from the userrequest and userdownloads tables.
	 * if site->userdownloadpurgedays set to 0 then all release history is removed but
	 * the download/request rows must remain for at least one day to allow the role based
	 * limits to apply.
	 */
	public function pruneRequestHistory($days = 0)
	{
		$db = new DB();
		if ($days == 0) {
			$days = 1;
			$db->exec("update userdownloads set releaseid = null");
		}

		$db->exec(sprintf("DELETE from userrequests where timestamp < DATE_SUB(NOW(), INTERVAL %d DAY)", $days));
		$db->exec(sprintf("DELETE from userdownloads where timestamp < DATE_SUB(NOW(), INTERVAL %d DAY)", $days));
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
		$db = new DB();
		// Clear old requests.
		$db->queryExec(
			sprintf(
				'DELETE FROM userdownloads WHERE userid = %d AND timestamp < DATE_SUB(NOW(), INTERVAL 1 DAY)',
				$userID
			)
		);
		return $db->queryOneRow(
			sprintf(
				'SELECT COUNT(id) AS num FROM userdownloads WHERE userid = %d AND timestamp > DATE_SUB(NOW(), INTERVAL 1 DAY)',
				$userID
			)
		);
	}

	public function getDownloadRequestsForUserAndAllHostHashes($userid)
	{
		$db = new DB();

		$sql = sprintf("select distinct hosthash from userdownloads where userid = %d and hosthash is not null and hosthash != ''", $userid);
		$rows = $db->query($sql);
		$hashsql = "";
		foreach ($rows as $row)
			$hashsql .= sprintf("userdownloads.hosthash = %s or ", $db->escapeString($row["hosthash"]));

		$hashsql .= " 1=2 ";

		$sql = sprintf("select userdownloads.*, releases.guid, releases.searchname from userdownloads left outer join releases on releases.id = userdownloads.releaseid where userdownloads.userid = %d or %s order by userdownloads.timestamp desc", $userid, $hashsql);

		return $db->query($sql);
	}

	/**
	 * If a user downloads a NZB, log it.
	 *
	 * @param int $userID id of the user.
	 *
	 * @return bool|int
	 */
	public function addDownloadRequest($userID)
	{
		$db = new DB();
		return $db->queryInsert(
			sprintf(
				"INSERT INTO userdownloads (userid, timestamp) VALUES (%d, NOW())",
				$userID
			)
		);
	}

	public function delDownloadRequestsForRelease($releaseID)
	{
		$db = new DB();

		return $db->queryInsert(sprintf("delete from userdownloads where releaseid = %d", $releaseID));
	}
}