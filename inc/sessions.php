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

//
// Get our visitors IP
//
$ipaddress = getip();

//
// Current time
//
$time = time();

//
// User-agent
//
$useragent = $_SERVER['HTTP_USER_AGENT'];

//
// Listing of the search engine spider bots MyBB supports
//
$bots = array(
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
	"msnbot" => "MSN Search"
	);
//
// Treat bots as guests
//
$botgroup = 1;

unset($mybbuser);
unset($logon);
$valid = false;

//
// Check for a logged in user, check authentication, build profile
//
if($_COOKIE['mybbuser'])
{
	$valid = create_user_session();
}

//
// This user must be a guest or a spider
//
if($valid == false || !$_COOKIE['mybbuser'] || !$mybb->user['uid'])
{
	//
	// Detect if this guest is a search engine spider
	//
	$spiders = strtolower(implode("|", array_keys($bots)));
	if(preg_match("#(".$spiders.")#i", $useragent, $match))
	{
		create_spider_session(strtolower($match[0]));
	}

	//
	// Just a plain old guest
	//
	else
	{
		create_guest_session();
	}
}


// 
// Authenticates the current user, sets up profile, etc
//

function create_user_session()
{
	global $_COOKIE, $mybbuser, $mybb, $time, $settings, $mybbgroup, $db, $noonline, $ipaddress, $useragent, $time, $lang, $mybbgroups, $loadpmpopup;
	$logon = explode("_", $_COOKIE['mybbuser'], 2);
	$query = $db->query("SELECT u.*, f.*, COUNT(pms.pmid) AS pms_total, SUM(IF(pms.dateline>u.lastvisit AND pms.folder='1','1','0')) AS pms_new, SUM(IF(pms.status='0' AND pms.folder='1','1','0')) AS pms_unread, b.dateline AS bandate, b.lifted AS banlifted, b.oldgroup AS banoldgroup FROM ".TABLE_PREFIX."users u LEFT JOIN ".TABLE_PREFIX."privatemessages pms ON (pms.uid=u.uid) LEFT JOIN ".TABLE_PREFIX."userfields f ON (f.ufid=u.uid) LEFT JOIN ".TABLE_PREFIX."banned b ON (b.uid=u.uid) WHERE u.uid='$logon[0]' GROUP BY u.uid");
	$mybb->user = $db->fetch_array($query);
	if($mybb->user['loginkey'] != $logon[1])
	{
		return false;
	}
	unset($logon);

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
	if($time - $mybb->user['lastactive'] > 900)
	{
		$db->shutdown_query("UPDATE ".TABLE_PREFIX."users SET lastvisit=lastactive, lastactive='$time' $popupadd WHERE uid='".$mybb->user[uid]."'");
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
	// Update the online data
	//
	if(!$noonline)
	{
		$sid = md5($mybb->user['uid'].$ipaddress);
		$onlinedata = array(
			"sid" => $sid,
			"uid" => $mybb->user['uid'],
			"time" => $time,
			"location" => get_current_location(),
			"ip" => $ipaddress,
			"useragent" => $useragent
			);
		
		$db->update_query(TABLE_PREFIX."online", $onlinedata, "uid=".$mybb->user['uid']." OR ip='$ipaddress'");
		if(!$db->affected_rows())
		{
			$db->insert_query(TABLE_PREFIX."online", $onlinedata);
		}
	}
	// Legacy code
	$mybbuser = $mybb->user;
	$mybbgroup = $mybb->usergroup;
	return true;
}


function create_guest_session()
{
	global $_COOKIE, $mybbuser, $mybb, $time, $settings, $mybbgroup, $db, $noonline, $ipaddress, $useragent, $time, $lang;
	//
	// Set up some defaults
	//
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
	if(!$noonline)
	{
		$sid = md5($ipaddress);
		$onlinedata = array(
			"sid" => $sid,
			"uid" => 0,
			"time" => $time,
			"location" => get_current_location(),
			"ip" => $ipaddress,
			"useragent" => $useragent
			);
		
		$db->update_query(TABLE_PREFIX."online", $onlinedata, "sid='$sid' OR ip='$ipaddress'");
		if(!$db->affected_rows())
		{
			$db->insert_query(TABLE_PREFIX."online", $onlinedata);
		}
	}
	// Legacy code
	$mybbuser = $mybb->user;
	$mybbgroup = $mybb->usergroup;
}

function create_spider_session($spider)
{
	global $_COOKIE, $mybbuser, $mybb, $time, $settings, $mybbgroup, $db, $noonline, $ipaddress, $useragent, $time, $bots, $lang, $botgroup;
	//
	// Set up some defaults
	//
	$spidername = $bots[$spider];
	$mybb->user['usergroup'] = $botgroup;
	$mybb->user['username'] = "";
	$mybb->user['username'] = "";
	$mybb->user['uid'] = 0;
	$mybbgroups = $botgroup;
	$mybb->user['displaygroup'] = $botgroup;

	//
	// Gather a full permission set for this spider
	//
	$mybb->usergroup = usergroup_permissions($mybbgroups);
	$mydisplaygroup = usergroup_displaygroup(1);
	$mybb->usergroup = array_merge($mybb->usergroup, $mydisplaygroup);

	//
	// Update the online data
	//
	if(!$noonline)
	{
		$sid = "bot=".$spider;
		$onlinedata = array(
			"sid" => $sid,
			"uid" => 0,
			"time" => $time,
			"location" => get_current_location(),
			"ip" => $ipaddress,
			"useragent" => $useragent
			);
		
		$db->update_query(TABLE_PREFIX."online", $onlinedata, "sid='$sid' OR ip='$ipaddress'");
		if(!$db->affected_rows())
		{
			$db->insert_query(TABLE_PREFIX."online", $onlinedata);
		}
	}
	// Legacy code
	$mybbuser = $mybb->user;
	$mybbgroup = $mybb->usergroup;
}

?>