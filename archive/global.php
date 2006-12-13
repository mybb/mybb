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

// If archive mode deos not work, uncomment the line below and try again
// define("ARCHIVE_QUERY_STRINGS", 1);

// Lets pretend we're a level higher
chdir('./../');

require_once dirname(dirname(__FILE__))."/inc/init.php";

require_once MYBB_ROOT."inc/functions_archive.php";
require_once MYBB_ROOT."inc/class_session.php";
require_once MYBB_ROOT."inc/class_parser.php";
$parser = new postParser;

$groupscache = $cache->read("usergroups");
if(!is_array($groupscache))
{
	$cache->updateusergroups();
	$groupscache = $cache->read("usergroups");
}
$fpermissioncache = $cache->read("forumpermissions");

// Send headers before anything else.
send_page_headers();

// If the installer has not been removed and no lock exists, die.
if(is_dir(MYBB_ROOT."install") && !file_exists(MYBB_ROOT."install/lock"))
{
	echo "Please remove the install directory from your server, or create a file called 'lock' in the install directory. Until you do so, your board will remain unaccessable";
	exit;
}

// If the server OS is not Windows and not Apache or the PHP is running as a CGI or we have defined ARCHIVE_QUERY_STRINGS, use query strings
if((preg_match("#win#i", PHP_OS) && stripos($_SERVER['SERVER_SOFTWARE'], "apache") == false) || stripos(SAPI_NAME, "cgi") !== false || defined("ARCHIVE_QUERY_STRINGS"))
{
	$url = $_SERVER['QUERY_STRING'];
	$base_url = $mybb->settings['bburl']."/archive/index.php?";
	$endpart = $url;
}
// Otherwise, we're using 100% friendly URLs
else
{
	if($_SERVER['REDIRECT_URL'])
	{
		$url = $_SERVER['REDIRECT_URL'];
	}
	elseif($_SERVER['PATH_INFO'])
	{
		$url = $_SERVER['PATH_INFO'];
	}
	else
	{
		$url = $_SERVER['PHP_SELF'];
	}
	$base_url = $mybb->settings['bburl']."/archive/index.php/";
	$endpart = my_substr(strrchr($url, "/"), 1);
}

$action = "index";

// This seems to work the same as the block below except without the css bugs O_o
$archiveurl = $mybb->settings['bburl'].'/archive';

if($endpart != "index.php")
{
	$endpart = str_replace(".html", "", $endpart);
	$todo = explode("-", $endpart, 3);
	if($todo[0])
	{
		$action = $todo[0];
	}
	$page = $todo[2];
	$id = intval($todo[1]);

	// Get the thread, announcement or forum information.
	if($action == "announcement")
	{
		$time = time();
		$query = $db->query("
			SELECT a.*, u.username
			FROM ".TABLE_PREFIX."announcements a
			LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=a.uid)
			WHERE a.aid='{$id}' AND startdate < '{$time}'  AND (enddate > '{$time}' OR enddate = 0)
		");
		$announcement = $db->fetch_array($query);
		if(!$announcement['aid'])
		{
			$action = "404";
		}
	}
	elseif($action == "thread")
	{
		$query = $db->simple_select(TABLE_PREFIX."threads", "*", "tid='{$id}' AND visible='1' AND closed NOT LIKE 'moved|%'");
		$thread = $db->fetch_array($query);
		if(!$thread['tid'])
		{
			$action = "404";
		}
	}
	elseif($action == "forum")
	{
		$query = $db->simple_select(TABLE_PREFIX."forums", "*", "fid='{$id}' AND active!='no' AND password=''");
		$forum = $db->fetch_array($query);
		if(!$forum['fid'])
		{
			$action = "404";
		}
	}
	else if($action != 'index')
	{
		$action = "404";
	}
}

// Define the full MyBB version location of this page.
if($action == "thread")
{
	define(MYBB_LOCATION, "showthread.php?tid={$id}");
}
elseif($action == "forum")
{
	define(MYBB_LOCATION, "forumdisplay.php?fid={$id}");
}
elseif($action == "announcement")
{
	define(MYBB_LOCATION, "announcement.php?aid={$id}");
}
else
{
	define(MYBB_LOCATION, "index.php");
}

// Initialise session
$session = new session;
$session->init();

if(!$mybb->settings['bblanguage'])
{
	$mybb->settings['bblanguage'] = "english";
}
$lang->set_language($mybb->settings['bblanguage']);

// Load global language phrases
$lang->load("global");
$lang->load("messages");
$lang->load("archive");

// Draw up the basic part of our naviagation
$navbits[0]['name'] = $mybb->settings['bbname'];
$navbits[0]['url'] = $mybb->settings['bburl']."/archive/index.php";

// Check banned ip addresses
if(is_banned_ip($session->ipaddress))
{
	archive_error($lang->error_banned);
}

// If our board is closed..
if($mybb->settings['boardclosed'] == "yes")
{
	if($mybb->usergroup['cancp'] != "yes")
	{
		$lang->error_boardclosed .= "<blockquote>".$mybb->settings['boardclosed_reason']."</blockquote>";
		archive_error($lang->error_boardclosed);
	}
}

// Load Limiting
if(strtolower(substr(PHP_OS, 0, 3)) !== 'win')
{
	if($uptime = @exec('uptime'))
	{
		preg_match("/averages?: ([0-9\.]+),[\s]+([0-9\.]+),[\s]+([0-9\.]+)/", $uptime, $regs);
		$load = $regs[1];
		if($mybb->usergroup['cancp'] != "yes" && $load > $mybb->settings['load'] && $mybb->settings['load'] > 0)
		{
			archive_error($lang->error_loadlimit);
		}
	}
}

if($mybb->usergroup['canview'] == "no")
{
	archive_error_no_permission();
}
?>