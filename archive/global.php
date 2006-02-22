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

// Lets pretend we're a level higher
chdir('./../');

require "./inc/init.php";
require "./inc/functions_archive.php";
require "./inc/class_session.php";
require "./inc/class_parser.php";
$parser = new postParser;

$groupscache = $cache->read("usergroups");
if(!is_array($groupscache))
{
	$cache->updateusergroups();
	$groupscache = $cache->read("usergroups");
}
$fpermissioncache = $cache->read("forumpermissions");

pageheaders();

if(is_dir("install") && !file_exists("install/lock"))
{
	echo "Please remove the install directory from your server, or create a file called 'lock' in the install directory. Until you do so, your board will remain unaccessable";
	exit;
}
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

$endpart = substr(strrchr($url, "/"), 1);
$action = "index";

// This seems to work the same as the block below except without the css bugs O_o
$archiveurl = $mybb->settings['bburl'].'/archive';

if($endpart != "index.php")
{
	$endpart = str_replace(".html", "", $endpart);
	$todo = explode("-", $endpart, 3);
	$action = $todo[0];
	$page = $todo[2];
	$id = intval($todo[1]);
	if($action == "thread")
	{
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."threads WHERE tid='$id' AND visible='1' AND closed NOT LIKE 'moved|%'");
		$thread = $db->fetch_array($query);
		if(!$thread['tid'])
		{
			$action = "index";
		}
	}
	elseif($action == "forum")
	{
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."forums WHERE fid='$id' AND active!='no' AND type='f' AND password=''");
		$forum = $db->fetch_array($query);
		if(!$forum['fid'])
		{
			$action = "index";
		}
	}
}

if($action == "thread")
{
	define(MYBB_LOCATION, "showthread.php?tid=$id");
}
elseif($action == "forum")
{
	define(MYBB_LOCATION, "forumdisplay.php?fid=$id");
}
else
{
	define(MYBB_LOCATION, "index.php");
}

// Initialise session
$session = new session();
$session->init();

if(!$mybb->settings['bblanguage'])
{
	$mybb->settings['bblanguage'] = "english";
}
$lang->setLanguage($mybb->settings['bblanguage']);

// Load global language phrases
$lang->load("global");
$lang->load("messages");
$lang->load("archive");

// Get our visitors IP
$ipaddress = getip();

// Draw up the basic part of our naviagation
$navbits[0]['name'] = $mybb->settings['bbname'];
$navbits[0]['url'] = $mybb->settings['bburl']."/archive/index.php";

// Check banned ip addresses
$bannedips = explode(" ", $mybb->settings['ipban']);
if(is_array($bannedips))
{
	foreach($bannedips as $key => $bannedip)
	{
		$bannedip = trim($bannedip);
		if($bannedip != "")
		{
			if(strstr("$ipaddress", $bannedip))
			{
				archive_error($lang->error_banned);
			}
		}
	}
}

// If our board is closed..
if($mybb->settings['boardclosed'] == "yes")
{
	if($mybb->group['cancp'] != "yes")
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
		if($mybb->user['cancp'] != "yes" && $load > $mybb->settings['load'] && $mybb->settings['load'] > 0)
		{
			archive_error($lang->error_loadlimit);
		}
	}
}

if($mybb->user['canview'] == "no" && $action != "register" && $action != "do_register" && $action != "login" && $action != "do_login")
{
	archive_nopermission();
}
?>