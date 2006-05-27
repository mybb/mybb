<?php
/**
 * MyBB 1.2
 * Copyright © 2006 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.com
 * License: http://www.mybboard.com/eula.html
 *
 * $Id$
 */

class session
{
	var $sid = 0;
	var $uid = 0;
	var $ipaddress = "";
	var $useragent = "";
	var $botgroup = 1;
	var $is_spider = false;
	var $logins = 1;
	var $failedlogin = 0;

	var $bots = array(
		"google" => "GoogleBot",
		"lycos" => "Lycos.com",
		"ask jeeves" => "Ask Jeeves",
		"slurp@inktomi" => "Hot Bot",
		"whatuseek" => "What You Seek",
		"is_archiver" => "Archive.org",
		"scooter" => "Altavista",
		"fast-webcrawler" => "AlltheWeb",
		"grub.org" => "Grub Client",
		"turnitinbot" => "Turnitin.com",
		"msnbot" => "MSN Search",
		"yahoo" => "Yahoo! Slurp"
		);

	function init()
	{
		global $ipaddress, $db, $mybb, $noonline;

		// Get our visitor's IP.
		$this->ipaddress = $ipaddress = get_ip();

		// Find out the user agent.
		$this->useragent = $_SERVER['HTTP_USER_AGENT'];
		if(strlen($this->useragent) > 100)
		{
			$this->useragent = substr($this->useragent, 0, 100);
		}

		// Attempt to find a session id in the cookies.
		if(isset($_COOKIE['sid']))
		{
			$this->sid = $db->escape_string($_COOKIE['sid']);
		}
		else
		{
			$this->sid = 0;
		}

		// Attempt to load the session from the database.
		$query = $db->simple_select(TABLE_PREFIX."sessions", "sid, uid", "sid='".$this->sid."' AND ip='".$this->ipaddress."'", 1);
		$session = $db->fetch_array($query);
		if($session['sid'])
		{
			$this->sid = $session['sid'];
			$this->uid = $session['uid'];
			$this->logins = $session['loginattempts'];
			$this->failedlogin = $session['failedlogin'];
		}
		else
		{
			$this->sid = 0;
			$this->uid = 0;
			$this->logins = 1;
			$this->failedlogin = 0;
		}

		// If we have a valid session id and user id, load that users session.
		$logon = explode("_", $_COOKIE['mybbuser'], 2);
		if($_COOKIE['mybbuser'])
		{
			$this->load_user($logon[0], $logon[1]);
		}

		// If no user still, then we have a guest.
		if(!isset($mybb->user['uid']))
		{
			// Detect if this guest is a search engine spider.
			$spiders = strtolower(implode("|", array_keys($this->bots)));
			if(preg_match("#(".$spiders.")#i", $this->useragent, $match))
			{
				$this->load_spider(strtolower($match[0]));
			}

			// Just a plain old guest.
			else
			{
				$this->load_guest();
			}
		}

		// As a token of our appreciation for getting this far, give the user a cookie
		mysetcookie("sid", $this->sid, -1);
	}

	/**
	* Load a user via the user credentials.
	*
	* @param int The user id.
	* @param string The user's password.
	*/
	function load_user($uid, $password="")
	{
		global $mybbuser, $mybb, $settings, $mybbgroup, $db, $noonline, $ipaddress, $useragent, $time, $lang, $mybbgroups, $loadpmpopup, $session;

		$query = $db->query("SELECT u.*, f.*, b.dateline AS bandate, b.lifted AS banlifted, b.oldgroup AS banoldgroup FROM ".TABLE_PREFIX."users u LEFT JOIN ".TABLE_PREFIX."userfields f ON (f.ufid=u.uid) LEFT JOIN ".TABLE_PREFIX."banned b ON (b.uid=u.uid) WHERE u.uid='$uid'");
		$mybb->user = $db->fetch_array($query);

		// Check the password if we're not using a session
		if($password != $mybb->user['loginkey'])
		{
			unset($mybb->user);
			$this->uid = 0;
			return false;
		}
		$this->uid = $mybb->user['uid'];

		// Sort out the private message count for this user.
		if(($mybb->user['totalpms'] == -1 || $mybb->user['unreadpms'] == -1 || $mybb->user['newpms'] == -1) && $mybb->settings['enablepms'] != "no") // Forced recount
		{
			$update = 0;
			if($mybb->user['totalpms'] == -1) $update += 1;
			if($mybb->user['newpms'] == -1) $update += 2;
			if($mybb->user['unreadpms'] == -1) $update += 4;

			require_once MYBB_ROOT."inc/functions_user.php";
			$pmcount = update_pm_count("", $update);
			if(is_array($pmcount))
			{
				$mybb->user = array_merge($mybb->user, $pmcount);
			}
		}
		$mybb->user['pms_total'] = $mybb->user['totalpms'];
		$mybb->user['pms_new'] = $mybb->user['newpms'];
		$mybb->user['pms_unread'] = $mybb->user['unreadpms'];

		// Check if this user has a new private message.
		if($mybb->user['pmpopup'] == "new" && $mybb->settings['enablepms'] != "no")
		{
			$popupadd = ", pmpopup='yes'";
			$loadpmpopup = 1;
		}
		else
		{
			$popupadd = '';
			$loadpmpopup = 0;
		}

		// If the last visit was over 900 seconds (session time out) ago then update lastvisit.
		$time = time();
		if($time - $mybb->user['lastactive'] > 900)
		{
			$db->shutdown_query("UPDATE ".TABLE_PREFIX."users SET lastvisit='".$mybb->user['lastactive']."', lastactive='$time' $popupadd WHERE uid='".$mybb->user['uid']."'");
			$mybb->user['lastvisit'] = $mybb->user['lastactive'];
		}
		else
		{
			$mybb->user['lastvisit'] = $mybb->user['lastvisit'];
			$timespent = time() - $mybb->user['lastactive'];
			$db->shutdown_query("UPDATE ".TABLE_PREFIX."users SET lastactive='$time', timeonline=timeonline+$timespent $popupadd WHERE uid='".$mybb->user['uid']."'");
		}

		// Sort out the language and forum preferences.
		if($mybb->user['language'] && $lang->language_exists($user['language']))
		{
			$mybb->settings['bblanguage'] = $user['language'];
		}
		if($mybb->user['dateformat'] != "0" || $mybb->user['dateformat'] != "")
		{
			// Choose date format.
			switch($mybb->user['dateformat'])
			{
				case "1":
					$mybb->settings['dateformat'] = "m-d-Y";
					break;
				case "2":
					$mybb->settings['dateformat'] = "m-d-y";
					break;
				case "3":
					$mybb->settings['dateformat'] = "m.d.Y";
					break;
				case "4":
					$mybb->settings['dateformat'] = "m.d.y";
					break;
				case "5":
					$mybb->settings['dateformat'] = "d-m-Y";
					break;
				case "6":
					$mybb->settings['dateformat'] = "d-m-y";
					break;
				case "7":
					$mybb->settings['dateformat'] = "d.m.Y";
					break;
				case "8":
					$mybb->settings['dateformat'] = "d.m.y";
					break;
				case "9":
					$mybb->settings['dateformat'] = "F jS, Y";
					break;
				case "10":
					$mybb->settings['dateformat'] = "l, F jS, Y";
					break;
				case "11":
					$mybb->settings['dateformat'] = "jS F Y";
					break;
				case "12":
					$mybb->settings['dateformat'] = "l, jS F Y";
					break;
				default:
					break;
			}
		}

		// Choose time format.
		if($mybb->user['timeformat'] != "0" || $mybb->user['timeformat'] != "")
		{
			switch($mybb->user['timeformat']) {
				case "1":
					$mybb->settings['timeformat'] = "h:i a";
					break;
				case "2":
					$mybb->settings['timeformat'] = "h:i A";
					break;
				case "3":
					$mybb->settings['timeformat'] = "H:i";
					break;
			}
		}

		// Find out the threads per page preference.
		if($mybb->user['tpp'])
		{
			$mybb->settings['threadsperpage'] = $mybb->user['tpp'];
		}

		// Find out the posts per page preference.
		if($mybb->user['ppp'])
		{
			$mybb->settings['postsperpage'] = $mybb->user['ppp'];
		}

		// Check if this user is currently banned and if we have to lift it.
		if($mybb->user['bandate'] && $mybb->user['banlifted'])  // hmmm...bad user... how did you get banned =/
		{
			if($mybb->user['banlifted'] < $time) // must have been good.. bans up :D
			{
				$db->shutdown_query("UPDATE ".TABLE_PREFIX."users SET usergroup='".$mybb->user['banoldgroup']."' WHERE uid='".$mybb->user[uid]."'");
				$db->shutdown_query("DELETE FROM ".TABLE_PREFIX."banned WHERE uid='".$mybb->user[uid]."'");
				$query = $db->query("SELECT * FROM ".TABLE_PREFIX."usergroups WHERE gid='".$mybb->user['banoldgroup']."' LIMIT 1"); // we better do this..otherwise they have dodgy permissions
				$group = $db->fetch_array($query);
				$mybb->user['usergroup'] = $group['usergroup'];
			}
		}

		// Gather a full permission set for this user and the groups they are in.
		$mybbgroups = $mybb->user['usergroup'].",".$mybb->user['additionalgroups'];
		$mybb->usergroup = usergroup_permissions($mybbgroups);
		if(!$mybb->user['displaygroup'])
		{
			$mybb->user['displaygroup'] = $mybb->user['usergroup'];
		}
		$mydisplaygroup = usergroup_displaygroup($mybb->user['displaygroup']);
		$mybb->usergroup = array_merge($mybb->usergroup, $mydisplaygroup);

		if(!$mybb->user['usertitle'])
		{
			$mybb->user['usertitle'] = $mybb->usergroup['usertitle'];
		}

		// Update or create the session.
		if(!defined("NO_ONLINE"))
		{
			if($this->sid > 0)
			{
				$this->update_session($this->sid, $mybb->user['uid']);
			}
			else
			{
				$this->create_session($mybb->user['uid']);
			}
		}

		// Deprecated...
		$mybbuser = $mybb->user;
		$mybbgroup = $mybb->usergroup;
		return true;
	}

	/**
	* Load a guest user.
	*
	*/
	function load_guest()
	{
		global $mybbuser, $mybb, $time, $settings, $mybbgroup, $db, $noonline, $ipaddress, $useragent, $lang;

		// Set up some defaults
		$time = time();
		$mybb->user['usergroup'] = 1;
		$mybb->user['username'] = "";
		$mybb->user['username'] = "";
		$mybb->user['uid'] = 0;
		$mybbgroups = 1;
		$mybb->user['displaygroup'] = 1;

		// Has this user visited before? Lastvisit need updating?
		if(isset($_COOKIE['mybb']['lastvisit']))
		{
			if(!isset($_COOKIE['mybb']['lastactive']))
			{
				$mybb->user['lastactive'] = time();
				$_COOKIE['mybb']['lastactive'] = $mybb->user['lastactive'];
			}
			else
			{
				$mybb->user['lastactive'] = $_COOKIE['mybb']['lastactive'];
			}
			if($time - $_COOKIE['mybb']['lastactive'] > 900)
			{
				mysetcookie("mybb[lastvisit]", $mybb->user['lastactive']);
				$mybb->user['lastvisit'] = $mybb->user['lastactive'];
			}
		}

		// No last visit cookie, create one.
		else
		{
			mysetcookie("mybb[lastvisit]", $time);
			$mybb->user['lastvisit'] = $time;
		}

		// Update last active cookie.
		mysetcookie("mybb[lastactive]", $time);

		//
		// Gather a full permission set for this guest
		//
		$mybb->usergroup = usergroup_permissions($mybbgroups);
		$mydisplaygroup = usergroup_displaygroup($mybb->user['displaygroup']);
		$mybb->usergroup = array_merge($mybb->usergroup, $mydisplaygroup);

		// Update the online data.
		if(!defined("NO_ONLINE"))
		{
			if($this->sid > 0)
			{
				$this->update_session($this->sid);
			}
			else
			{
				$this->create_session();
			}
		}

		// Deprecated...
		$mybbuser = $mybb->user;
		$mybbgroup = $mybb->usergroup;
	}

	/**
	* Load a search engine spider.
	*
	* @param string The spider name.
	*/
	function load_spider($spider)
	{
		global $mybbuser, $mybb, $time, $settings, $mybbgroup, $db, $noonline, $ipaddress, $useragent, $bots, $lang;

		// Set up some defaults
		$time = time();
		$this->is_spider = true;
		$spidername = $bots[$spider];
		$mybb->user['usergroup'] = $this->botgroup;
		$mybb->user['username'] = "";
		$mybb->user['username'] = "";
		$mybb->user['uid'] = 0;
		$mybbgroups = $this->botgroup;
		$mybb->user['displaygroup'] = $this->botgroup;

		// Gather a full permission set for this spider.
		$mybb->usergroup = usergroup_permissions($mybbgroups);
		$mydisplaygroup = usergroup_displaygroup($mybb->user['displaygroup']);
		$mybb->usergroup = array_merge($mybb->usergroup, $mydisplaygroup);

		$db->query("DELETE FROM ".TABLE_PREFIX."sessions WHERE sid='bot=".$spider."'");
		$db->delete_query(TABLE_PREFIX."sessions", "sid='bot".$spider."'", 1);

		// Update the online data.
		if(!defined("NO_ONLINE"))
		{
			$this->sid = "bot=".$spider;
			$this->create_session();
		}

		// Deprecated...
		$mybbuser = $mybb->user;
		$mybbgroup = $mybb->usergroup;
	}

	/**
	* Update a user session.
	*
	* @param int The session id.
	* @param int The user id.
	*/
	function update_session($sid, $uid="")
	{
		global $db;

		// Find out what the special locations are.
		$speciallocs = $this->get_special_locations();
		if($uid)
		{
			$onlinedata['uid'] = $uid;
		}
		else
		{
			$onlinedata['uid'] = 0;
		}
		$onlinedata['time'] = time();
		$onlinedata['location'] = $db->escape_string(get_current_location());
		$onlinedata['useragent'] = $db->escape_string($this->useragent);
		$onlinedata['location1'] = intval($speciallocs['1']);
		$onlinedata['location2'] = intval($speciallocs['2']);
		$sid = $db->escape_string($sid);

		$db->update_query(TABLE_PREFIX."sessions", $onlinedata, "sid='".$sid."'");
	}

	/**
	* Create a new session.
	*
	* @param int The user id to bind the session to.
	*/
	function create_session($uid=0)
	{
		global $db;
		$speciallocs = $this->get_special_locations();

		// If there is a proper uid, delete by uid.
		if($uid > 0)
		{
			$db->delete_query(TABLE_PREFIX."sessions", "uid=".$uid);
			$onlinedata['uid'] = $uid;
		}
		// Else delete by ip.
		else
		{
			$db->delete_query(TABLE_PREFIX."sessions", "ip='".$this->ipaddress."'");
			$onlinedata['uid'] = 0;
		}

		// If the user is a search enginge spider, ...
		if($this->is_spider == true)
		{
			//$onlinedata['sid'] = "bot=".$this->useragent;
			$onlinedata['sid'] = $this->sid;
		}
		else
		{
			$onlinedata['sid'] = md5(uniqid(microtime()));
		}
		$onlinedata['time'] = time();
		$onlinedata['ip'] = $this->ipaddress;
		$onlinedata['location'] = $db->escape_string(get_current_location());
		$onlinedata['useragent'] = $db->escape_string($this->useragent);
		$onlinedata['location1'] = intval($speciallocs['1']);
		$onlinedata['location2'] = intval($speciallocs['2']);
		$db->insert_query(TABLE_PREFIX."sessions", $onlinedata);
		$this->sid = $onlinedata['sid'];
		$this->uid = $onlinedata['uid'];
	}

	/**
	* Find out the special locations.
	*
	* @return array Special locations array.
	*/
	function get_special_locations()
	{
		global $mybb;
		$array = array("1" => "", "2" => "");
		if(preg_match("#forumdisplay.php#", $_SERVER['PHP_SELF']) && intval($mybb->input['fid']) > 0)
		{
			$array[1] = intval($mybb->input['fid']);
			$array[2] = "";
		}
		elseif(preg_match("#showthread.php#", $_SERVER['PHP_SELF']) && intval($mybb->input['tid']) > 0)
		{
			$array[1] = "";
			$array[2] = intval($mybb->input['tid']);
		}
		return $array;
	}
}
