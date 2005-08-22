<?php
/**
 * MyBB 1.0
 * Copyright © 2005 MyBulletinBoard Group, All Rights Reserved
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

	var $bots = array(
		"googlebot" => "GoogleBot",
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
		//
		// Get our visitors IP
		//
		$this->ipaddress = $ipaddress = getip();

		//
		// User-agent
		//
		$this->useragent = $_SERVER['HTTP_USER_AGENT'];

		//
		// Attempt to find a session id in the cookies
		//
		if($_COOKIE['sid'])
		{
			$this->sid = addslashes($_COOKIE['sid']);
		}
		else
		{
			$this->sid = 0;
		}

		//
		// Attempt to load the session from the database
		//
		$query = $db->query("SELECT sid,uid FROM ".TABLE_PREFIX."sessions WHERE sid='".$this->sid."' AND ip='".$this->ipaddress."'");
		$session = $db->fetch_array($query);
		if($session['sid'])
		{
			$this->sid = $session['sid'];
			$this->uid = $session['uid'];
		}
		else
		{
			$this->sid = 0;
			$this->uid = 0;
		}

		//
		// If we have a valid session id and user id, load that users session
		//
		$logon = explode("_", $_COOKIE['mybbuser'], 2);
		if($_COOKIE['mybbuser'])
		{
			$this->load_user($logon[0], $logon[1]);
		}

		//
		// If no user still, then we have a guest.
		//
		if(!$mybb->user['uid'])
		{
			//
			// Detect if this guest is a search engine spider
			//
			$spiders = strtolower(implode("|", array_keys($this->bots)));
			if(preg_match("#(".$spiders.")#i", $this->useragent, $match))
			{
				$this->load_spider(strtolower($match[0]));
			}

			//
			// Just a plain old guest
			//
			else
			{
				$this->load_guest();
			}
		}

		//
		// As a token of our appreciation for getting this far, give the user a cookie
		//
		mysetcookie("sid", $this->sid, -1);
	}

	function load_user($uid, $password="")
	{
		global $_COOKIE, $mybbuser, $mybb, $settings, $mybbgroup, $db, $noonline, $ipaddress, $useragent, $time, $lang, $mybbgroups, $loadpmpopup, $session;

		$query = $db->query("SELECT u.*, f.*, COUNT(pms.pmid) AS pms_total, SUM(IF(pms.dateline>u.lastvisit AND pms.folder='1','1','0')) AS pms_new, SUM(IF(pms.status='0' AND pms.folder='1','1','0')) AS pms_unread, b.dateline AS bandate, b.lifted AS banlifted, b.oldgroup AS banoldgroup FROM ".TABLE_PREFIX."users u LEFT JOIN ".TABLE_PREFIX."privatemessages pms ON (pms.uid=u.uid) LEFT JOIN ".TABLE_PREFIX."userfields f ON (f.ufid=u.uid) LEFT JOIN ".TABLE_PREFIX."banned b ON (b.uid=u.uid) WHERE u.uid='$uid' GROUP BY u.uid");
		$mybb->user = $db->fetch_array($query);
		//
		// Check the password if we're not using a session
		//
		//if($password != $mybb->user['loginkey'] && !$this->uid)
		if($password != $mybb->user['loginkey'])
		{
			unset($mybb->user);
			$this->uid = 0;
			return false;
		}

		$this->uid = $mybb->user['uid'];

		//
		// Setup some common variables
		//
		if($mybb->user['pms_unread'] == "")
		{
			$mybb->user['pms_unread'] = 0;
		}

		//
		// Check if this user has a new private message
		//
		if($mybb->user['pmpopup'] == "new")
		{
			$popupadd = ", pmpopup='yes'";
			$loadpmpopup = 1;
		}
		else
		{
			$loadpmpopup = 0;
		}

		//
		// If the last visit was over 900 seconds (session time out) ago then update lastvisit
		//
		$time = time();
		if($time - $mybb->user['lastactive'] > 900)
		{
			$db->shutdown_query("UPDATE ".TABLE_PREFIX."users SET lastvisit='".$mybb->user['lastactive']."', lastactive='$time' $popupadd WHERE uid='".$mybb->user[uid]."'");
			$mybb->user['lastvisit'] = $mybb->user['lastactive'];
		}
		else
		{
			$mybb->user['lastvisit'] = $mybb->user['lastvisit'];
			$timespent = time() - $mybb->user['lastactive'];
			$db->shutdown_query("UPDATE ".TABLE_PREFIX."users SET lastactive='$time', timeonline=timeonline+$timespent $popupadd WHERE uid='".$mybb->user[uid]."'");
		}

		//
		// Language and forum preferences
		//
		if($mybb->user['language'] && $lang->languageExists($user['language']))
		{
			$mybb->settings['bblanguage'] = $user['language'];
		}
		if($mybb->user['dateformat'] != "0" || $mybb->user['dateformat'] != "")
		{
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
					$mybb->settings['dateformat'] = "d.m.y";
					break;
				case "7":
					$mybb->settings['dateformat'] = "d.m.y";
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
		
		if($mybb->user['dst'] == "yes")
		{
			$mybb->user['timezone']++;
			if(substr($mybb->user['timezone'], 0, 1) != "-")
			{
				$mybb->user['timezone'] = "+".$mybb->user['timezone'];
			}
		}

		if($mybb->user['tpp'])
		{
			$mybb->settings['threadsperpage'] = $mybb->user['tpp'];
		}
		if($mybb->user['ppp'])
		{
			$mybb->settings['postsperpage'] = $mybb->user['ppp'];
		}

		//
		// Check if this user is currently banned and if we have to lift it
		//
		if($mybb->user['bandate'] && $mybb->user['banlifted'])  // hmmm...bad user... how did you get banned =/
		{
			if($mybb->user['banlifted']<$time) // must have been good.. bans up :D
			{
				$db->shutdown_query("UPDATE ".TABLE_PREFIX."users SET usergroup='".$mybb->user['banoldgroup']."' WHERE uid='".$mybb->user[uid]."'");
				$db->shutdown_query("DELETE FROM ".TABLE_PREFIX."banned WHERE uid='".$mybb->user[uid]."'");
				$query = $db->query("SELECT * FROM ".TABLE_PREFIX."usergroups WHERE gid='".$mybb->user['banoldgroup']."'"); // we better do this..otherwise they have dodgy permissions
				$group = $db->fetch_array($query);
				$mybb->user['usergroup'] = $group['usergroup'];
			}
		}

		//
		// Gather a full permission set for this user and the groups they are in
		//
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
		
		//
		// Update or create the session
		//
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
		// Legacy code
		$mybbuser = $mybb->user;
		$mybbgroup = $mybb->usergroup;
		return true;
	}

	function load_guest()
	{
		global $_COOKIE, $mybbuser, $mybb, $time, $settings, $mybbgroup, $db, $noonline, $ipaddress, $useragent, $lang;
		//
		// Set up some defaults
		//
		$time = time();
		$mybb->user['usergroup'] = 1;
		$mybb->user['username'] = "";
		$mybb->user['username'] = "";
		$mybb->user['uid'] = 0;
		$mybbgroups = 1;
		$mybb->user['displaygroup'] = 1;

		//
		// Has this user visited before? Lastvisit need updating?
		//
		if($_COOKIE['mybb']['lastvisit'])
		{
			if(!$_COOKIE['mybb']['lastactive'])
			{
				$mybb->user['lastactive'] = time();
			}
			if($time - $_COOKIE['mybb']['lastactive'] > 900)
			{
				mysetcookie("mybb[lastvisit]", $mybb->user['lastactive']);
				$mybb->user['lastvisit'] = $mybb->user['lastactive'];
			}
		}

		//
		// No last visit cookie, create one
		//
		else
		{
			mysetcookie("mybb[lastvisit]", $time);
			$mybb->user['lastvisit'] = $time;
		}

		//
		// Update last active cookie
		//
		mysetcookie("mybb[lastactive]", $time);

		//
		// Gather a full permission set for this guest
		//
		$mybb->usergroup = usergroup_permissions($mybbgroups);
		$mydisplaygroup = usergroup_displaygroup(1);
		$mybb->usergroup = array_merge($mybb->usergroup, $mydisplaygroup);

		//
		// Update the online data
		//
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
		// Legacy code
		$mybbuser = $mybb->user;
		$mybbgroup = $mybb->usergroup;
	}

	function load_spider($spider)
	{
		global $_COOKIE, $mybbuser, $mybb, $time, $settings, $mybbgroup, $db, $noonline, $ipaddress, $useragent, $bots, $lang, $botgroup;
		//
		// Set up some defaults
		//
		$time = time();
		$this->is_spider = true;
		$spidername = $bots[$spider];
		$mybb->user['usergroup'] = $botgroup;
		$mybb->user['username'] = "";
		$mybb->user['username'] = "";
		$mybb->user['uid'] = 0;
		$mybbgroups = $this->botgroup;
		$mybb->user['displaygroup'] = $botgroup;

		//
		// Gather a full permission set for this spider
		//
		$mybb->usergroup = usergroup_permissions($mybbgroups);
		$mydisplaygroup = usergroup_displaygroup(1);
		$mybb->usergroup = array_merge($mybb->usergroup, $mydisplaygroup);

		$db->query("DELETE FROM ".TABLE_PREFIX."sessions WHERE sid='bot=".$spider."'");

		//
		// Update the online data
		//
		if(!defined("NO_ONLINE"))
		{
			$this->sid = "bot=".$spider;
			$this->create_session();
		}
		// Legacy code
		$mybbuser = $mybb->user;
		$mybbgroup = $mybb->usergroup;
	}

	function update_session($sid, $uid="")
	{
		global $db;
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
		$onlinedata['location'] = addslashes(get_current_location());
		$onlinedata['useragent'] = addslashes($this->useragent);
		$onlinedata['location1'] = $speciallocs['1'];
		$onlinedata['location2'] = $speciallocs['2'];
		$sid = addslashes($sid);

		$db->update_query(TABLE_PREFIX."sessions", $onlinedata, "sid='".$sid."'");
	}

	function create_session($uid=0)
	{
		global $db;
		$speciallocs = $this->get_special_locations();
		if($uid > 0)
		{
			$db->query("DELETE FROM ".TABLE_PREFIX."sessions WHERE uid='".$uid."'");
			$onlinedata['uid'] = $uid;
		}
		else
		{
			$db->query("DELETE FROM ".TABLE_PREFIX."sessions WHERE ip='".$this->ipaddress."'");
			$onlinedata['uid'] = 0;
		}
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
		$onlinedata['location'] = addslashes(get_current_location());
		$onlinedata['useragent'] = addslashes($this->useragent);
		$onlinedata['location1'] = $speciallocs['1'];
		$onlinedata['location2'] = $speciallocs['2'];
		$db->insert_query(TABLE_PREFIX."sessions", $onlinedata);
		$this->sid = $onlinedata['sid'];
		$this->uid = $onlinedata['uid'];
	}

	function get_special_locations()
	{
		global $mybb;
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