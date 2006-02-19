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

require "./inc/init.php";

$shutdown_queries = array();

$groupscache = $cache->read("usergroups");
$mcache = $cache->read("moderators");

if(!is_array($groupscache))
{
	$cache->updateusergroups();
	$groupscache = $cache->read("usergroups");
}
$fpermissioncache = $cache->read("forumpermissions");


pageheaders();


if(is_dir("install") && !file_exists("install/lock"))
{
	$mybb->trigger_generic_error("install_directory");
}

//
// Create this users session
//
if(isset($nosession[$mybb->input['action']]))
{
	define("NO_ONLINE", 1);
}
require "./inc/class_session.php";
$session = new session;
$session->init();

$plugins->run_hooks("global_start");

//
// Set and load the language
//
if(!$mybb->settings['bblanguage'])
{
	$mybb->settings['bblanguage'] = "english";
}
if($mybb->user['language'])
{
	$mybb->settings['bblanguage'] = $mybb->user['language'];
}
$lang->setLanguage($mybb->settings['bblanguage']);
$lang->load("global");
$lang->load("messages");

// Remove slashes from bbname
$mybb->settings['bbname'] = stripslashes($mybb->settings['bbname']);
$settings['bbname'] = stripslashes($mybb->settings['bbname']);

// Which thread mode is our user using?
if(!$mybb->input['mode'])
{
	if($mybb->user['threadmode'])
	{
		$mybb->input['mode'] = $mybb->user['threadmode'];
	}
	elseif($mybb->settings['threadusenetstyle'] == "yes")
	{
		$mybb->input['mode'] = "threaded";
	}
	else
	{
		$mybb->input['mode'] = "linear";
	}
}

$loadstyle = "";
$style = array();
if($mybb->user['style'] != "" && $mybb->user['style'] != "0")
{
	$loadstyle = "tid='".$mybb->user['style']."'";
}
if($mybb->input['pid'] > 0 && $mybb->input['tid'])
{
	$query = $db->query("SELECT f.style, f.overridestyle FROM ".TABLE_PREFIX."forums f, ".TABLE_PREFIX."posts p WHERE f.fid=p.fid AND p.pid='".intval($mybb->input['pid'])."'");
	$style = $db->fetch_array($query);
}
if($mybb->input['pid'] > 0 && !$mybb->input['tid'])
{
	$query = $db->query("SELECT p.fid, f.style, f.overridestyle FROM ".TABLE_PREFIX."posts p LEFT JOIN ".TABLE_PREFIX."forums f ON (f.fid=p.fid) WHERE p.pid='".intval($mybb->input['pid'])."'");
	$style = $db->fetch_array($query);
}
if($mybb->input['tid'] > 0 && $mybb->input['fid'])
{
	$query = $db->query("SELECT f.style, f.overridestyle FROM ".TABLE_PREFIX."forums f, ".TABLE_PREFIX."threads t WHERE f.fid=t.fid AND t.tid='".intval($mybb->input['tid'])."'");
	$style = $db->fetch_array($query);
}
if($mybb->input['tid'] > 0 && !$mybb->input['fid'])
{
	$query = $db->query("SELECT t.fid, f.style, f.overridestyle FROM ".TABLE_PREFIX."threads t LEFT JOIN ".TABLE_PREFIX."forums f ON (f.fid=t.fid) WHERE t.tid='".intval($mybb->input['tid'])."'");
	$style = $db->fetch_array($query);
}
if($mybb->input['fid'] > 0)
{
	$query = $db->query("SELECT f.style, f.overridestyle FROM ".TABLE_PREFIX."forums f WHERE f.fid='".intval($mybb->input['fid'])."'");
	$style = $db->fetch_array($query);
}
if(is_numeric($style['style']) && $style['style'] > 0)
{
	if($style['overridestyle'] == "yes" || !$mybb->user['style'])
	{
		$loadstyle = "tid='".$style['style']."'";
	}
}
if(!$loadstyle)
{
	$loadstyle = "def='1'";
}

$query = $db->query("SELECT name,tid,themebits FROM ".TABLE_PREFIX."themes WHERE $loadstyle");
$theme = $db->fetch_array($query);

$theme = @array_merge($theme, unserialize($theme['themebits']));

if(!empty($mybb->user['language']) && is_dir($theme['imgdir'].'/'.$mybb->user['language']))
{
	$theme['imglangdir'] = $theme['imgdir'].'/'.$mybb->user['language'];
}
else
{
	if(is_dir($theme['imgdir'].'/'.$mybb->settings['bblanguage']))
	{
		$theme['imglangdir'] = $theme['imgdir'].'/'.$mybb->settings['bblanguage'];
	}
	else
	{
		$theme['imglangdir'] = $theme['imgdir'];
	}
}

// Load Main Templates and Cached Templates
if($templatelist)
{
	$templatelist .= ",";
}
$templatelist .= "css,headerinclude,header,footer,gobutton,htmldoctype,header_welcomeblock_member,header_welcomeblock_guest,header_welcomeblock_member_admin";
$templatelist .= ",header_toplinks_weblogs,header_toplinks_gallery,header_toplinks_shoutbox,header_toplinks_arcade";
$templatelist .= ",nav,nav_sep,nav_bit,nav_sep_active,nav_bit_active";
$templates->cache(addslashes($templatelist));

$datenow = mydate($mybb->settings['dateformat'], time(), '', false);
$timenow = mydate($mybb->settings['timeformat'], time());

// Make the users last visit look purtty
if($mybb->user['lastvisit'])
{
	$lastvisit = mydate($mybb->settings['dateformat'], $mybb->user['lastvisit']) . ", " . mydate($mybb->settings['timeformat'], $mybb->user['lastvisit']);
}
else
{
	$lastvisit = $lang->lastvisit_never;
}

if($mybb->settings['boardclosed'] == "yes")
{
	if($mybb->usergroup['cancp'] == "yes")
	{
		eval("\$bbclosedwarning = \"".$templates->get("global_boardclosed_warning")."\";");
	}
}

// Prepare the main templates for use
unset($admincplink);

$lang->welcome_current_time = sprintf($lang->welcome_current_time, $datenow.", ".$timenow);

if($mybb->user['uid'] != 0)
{
	if($mybb->usergroup['cancp'] == "yes")
	{
		eval("\$admincplink = \"".$templates->get("header_welcomeblock_member_admin")."\";");
	}
	$lang->welcome_back = sprintf($lang->welcome_back, $mybb->user['username'], $lastvisit);
	$lang->welcome_pms_usage = sprintf($lang->welcome_pms_usage, mynumberformat($mybb->user['pms_new']), mynumberformat($mybb->user['pms_unread']), mynumberformat($mybb->user['pms_total']));
	eval("\$welcomeblock = \"".$templates->get("header_welcomeblock_member")."\";");
}
else
{
	eval("\$welcomeblock = \"".$templates->get("header_welcomeblock_guest")."\";");
}
$unreadreports = "";
if($mybb->usergroup['cancp'] == "yes" || $mybb->usergroup['issupermod'] == "yes" || $mybb->usergroup['gid'] == 6)
{
	$reported = $cache->read("reportedposts");
	if($reported['unread'] > 0)
	{
		if($reported['unread'] == 1)
		{
			$lang->unread_reports = $lang->unread_report;
		}
		else
		{
			$lang->unread_reports = sprintf($lang->unread_reports, $reported['unread']);
		}
		eval("\$unreadreports = \"".$templates->get("global_unreadreports")."\";");
	}
}
if($lang->settings['charset'])
{
	$charset = $lang->settings['charset'];
}
else
{
	$charset = "iso-8859-1";
}

// Banned warning
if($mybb->usergroup['isbannedgroup'] == "yes")
{
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."banned WHERE uid = ".$mybb->user['uid']." LIMIT 1");
	if($query)
	{
		$ban = $db->fetch_array($query);
		if($ban['lifted'] > 0)
		{
			$banlift = mydate($mybb->settings['dateformat'], $ban['lifted']) . ", " . mydate($mybb->settings['timeformat'], $ban['lifted']);
		}
		else {
			$banlift = $lang->banned_lifted_never;
		}
		$reason = htmlspecialchars_uni($ban['reason']);
	}
	if(empty($reason))
	{
		$reason = $lang->unknown;
	}
	if(empty($banlift))
	{
		$banlift = $lang->unknown;
	}
	eval("\$bannedwarning = \"".$templates->get("global_bannedwarning")."\";");
}
eval("\$headerinclude = \"".$templates->get("headerinclude")."\";");
eval("\$gobutton = \"".$templates->get("gobutton")."\";");
eval("\$htmldoctype = \"".$templates->get("htmldoctype", 1, 0)."\";");
eval("\$header = \"".$templates->get("header")."\";");
$copy_year = date("Y");
$settings['homename'] = stripslashes($settings['homename']);
if($mybb->settings['showvernum'] == "on")
{
	$mybbversion = $mybboard['internalver'];
}
else
{
	$mybbversion = "";
}
eval("\$footer = \"".$templates->get("footer")."\";");

$navbits[0]['name'] = $mybb->settings['bbname'];
$navbits[0]['url'] = $mybb->settings['bburl']."/index.php";

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
				error($lang->error_banned);
				$db->query("DELETE FROM ".TABLE_PREFIX."sessions WHERE ip='$ipaddress' OR uid='".$mybb->user['uid']."'");
			}
		}
	}
}

// Board closed
if($mybb->settings['boardclosed'] == "yes" && $mybb->usergroup['cancp'] != "yes" && !(basename($_SERVER['PHP_SELF']) == "member.php" && ($mybb->input['action'] == "login" || $mybb->input['action'] == "do_login" || $mybb->input['action'] == "logout")))
{
	// Show error
	$lang->error_boardclosed .= "<blockquote>".stripslashes($mybb->settings['boardclosed_reason'])."</blockquote>";
	error($lang->error_boardclosed);
	exit;
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
			error($lang->error_loadlimit);
		}
	}
}

// Referrals system
if(!$mybb->user['uid'] && $mybb->settings['usereferrals'] == "yes" && intval($mybb->input['referrer']) > 0 && !$_COOKIE['mybb']['referrer'])
{
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."users WHERE uid='".intval($mybb->input['referrer'])."'");
	$referrer = $db->fetch_array($query);
	if($referrer['uid'])
	{
		mysetcookie("mybb[referrer]", $referrer['uid']);
	}
}

// Check pages allowable even when not allowed to view board
$allowable_actions = array(
	"member.php" => array("register", "do_register", "login", "do_login", "logout", "lostpw", "do_lostpw", "activate", "resendactivation", "do_resendactivation", "resetpassword"),
	"image.php" => array("regimage"),
	);
if($mybb->usergroup['canview'] != "yes" && !(basename($_SERVER['PHP_SELF']) == "member.php" && in_array($mybb->input['action'], $allowable_actions['member.php'])) && !(basename($_SERVER['PHP_SELF']) == "image.php" && in_array($mybb->input['action'], $allowable_actions['image.php'])))
{ 
	nopermission();
}

// work out which items the user has collapsed
$colcookie = $_COOKIE['collapsed'];
// set up collapsable items (to automatically show them us expanded)
if($_COOKIE['collapsed'])
{
	$col = explode("|", $colcookie);
	if(!is_array($col))
	{
		$col[0] = $colcookie; // only one item
	}
	unset($collapsed);
	foreach($col as $key => $val)
	{
		$ex = $val."_e";
		$co = $val."_c";
		$collapsed[$co] = "display: show;";
		$collapsed[$ex] = "display: none;";
		$collapsedimg[$val] = "_collapsed";
	}
}

$plugins->run_hooks("global_end");
$globaltime = $maintimer->gettime();
?>
