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

// Load main MyBB core file which begins all of the magic
require dirname(__FILE__)."/inc/init.php";

$shutdown_queries = array();

// Read the usergroups cache as well as the moderators cache
$groupscache = $cache->read("usergroups");
$mcache = $cache->read("moderators");

// If the groups cache doesn't exist, update it and re-read it
if(!is_array($groupscache))
{
	$cache->updateusergroups();
	$groupscache = $cache->read("usergroups");
}

// Read forum permissions cache
$fpermissioncache = $cache->read("forumpermissions");

// Send page headers
send_page_headers();

// Trigger an error if the installation directory exists
if(is_dir(MYBB_ROOT."install") && !file_exists(MYBB_ROOT."install/lock"))
{
//	$mybb->trigger_generic_error("install_directory");
}

// Do not use session system for defined pages
if((isset($mybb->input['action']) && isset($nosession[$mybb->input['action']])) || isset($mybb->input['thumbnail']))
{
	define("NO_ONLINE", 1);
}

// Create session for this user
require MYBB_ROOT."inc/class_session.php";
$session = new session;
$session->init();

// Run global_start plugin hook now that the basics are set up
$plugins->run_hooks("global_start");

// Set and load the language
if(!isset($mybb->settings['bblanguage']))
{
	$mybb->settings['bblanguage'] = "english";
}

// Load language
$lang->set_language($mybb->settings['bblanguage']);
$lang->load("global");
$lang->load("messages");

// Which thread mode is our user using?
if(!isset($mybb->input['mode']))
{
	if(isset($mybb->user['threadmode']))
	{
		$mybb->input['mode'] = $mybb->user['threadmode'];
	}
	else if($mybb->settings['threadusenetstyle'] == "yes")
	{
		$mybb->input['mode'] = "threaded";
	}
	else
	{
		$mybb->input['mode'] = "linear";
	}
}

// Select the board theme to use.
$loadstyle = '';
$style = array();

// This user has a custom theme set in their profile
if(isset($mybb->user['style']) && intval($mybb->user['style']) != 0)
{
	$loadstyle = "tid='".$mybb->user['style']."'";
}

// If we're accessing a post, fetch the forum theme for it and if we're overriding it
if(isset($mybb->input['pid']))
{
	$query = $db->simple_select(TABLE_PREFIX."forums f, ".TABLE_PREFIX."posts p", "f.style, f.overridestyle", "f.fid=p.fid AND p.pid='".intval($mybb->input['pid'])."'");
	$style = $db->fetch_array($query);
}

// We have a thread id and a forum id, we can easily fetch the theme for this forum
else if(isset($mybb->input['tid']))
{
	$query = $db->simple_select(TABLE_PREFIX."forums f, ".TABLE_PREFIX."threads t", "f.style, f.overridestyle", "f.fid=t.fid AND t.tid='".intval($mybb->input['tid'])."'");
	$style = $db->fetch_array($query);
}

// We have a forum id - simply load the theme from it
else if(isset($mybb->input['fid']))
{
	$query = $db->simple_select(TABLE_PREFIX."forums", "style, overridestyle", "fid='".intval($mybb->input['fid'])."'");
	$style = $db->fetch_array($query);
}

// From all of the above, a theme was found
if(isset($style['style']) && $style['style'] > 0)
{
	// This theme is forced upon the user, overriding their selection
	if($style['overridestyle'] == "yes" || !isset($mybb->user['style']))
	{
		$loadstyle = "tid='".intval($style['style'])."'";
	}
}

// After all of that no theme? Load the board default
if(empty($loadstyle))
{
	$loadstyle = "def='1'";
}

// Fetch the theme to load from the database
$query = $db->simple_select(TABLE_PREFIX."themes", "name, tid, themebits, csscached", $loadstyle);
$theme = $db->fetch_array($query);

$theme = @array_merge($theme, unserialize($theme['themebits']));

// Loading CSS from a file or from the server?
if($theme['csscached'] > 0 && $mybb->settings['cssmedium'] == 'file')
{
	$theme['css_url'] = $settings['bburl']."/css/theme_{$theme['tid']}.css";
}
else
{
	$theme['css_url'] = $settings['bburl']."/css.php?tid={$theme['tid']}";
}

// If a language directory for the current language exists within the theme - we use it
if(!empty($mybb->user['language']) && is_dir($theme['imgdir'].'/'.$mybb->user['language']))
{
	$theme['imglangdir'] = $theme['imgdir'].'/'.$mybb->user['language'];
}
else
{
	// Check if a custom language directory exists for this theme
	if(is_dir($theme['imgdir'].'/'.$mybb->settings['bblanguage']))
	{
		$theme['imglangdir'] = $theme['imgdir'].'/'.$mybb->settings['bblanguage'];
	}
	// Otherwise, the image language directory is the same as the language directory for the theme
	else
	{
		$theme['imglangdir'] = $theme['imgdir'];
	}
}

// Load Main Templates and Cached Templates
if(isset($templatelist))
{
	$templatelist .= ',';
}
$templatelist .= "css,headerinclude,header,footer,gobutton,htmldoctype,header_welcomeblock_member,header_welcomeblock_guest,header_welcomeblock_member_admin";
$templatelist .= ",header_toplinks_weblogs,header_toplinks_gallery,header_toplinks_shoutbox,header_toplinks_arcade";
$templatelist .= ",nav,nav_sep,nav_bit,nav_sep_active,nav_bit_active";
$templates->cache($db->escape_string($templatelist));

// Set the current date and time now
$datenow = mydate($mybb->settings['dateformat'], time(), '', false);
$timenow = mydate($mybb->settings['timeformat'], time());
$lang->welcome_current_time = sprintf($lang->welcome_current_time, $datenow.', '.$timenow);

// Format the last visit date of this user appropriately
if(isset($mybb->user['lastvisit']))
{
	$lastvisit = mydate($mybb->settings['dateformat'], $mybb->user['lastvisit']) . ', ' . mydate($mybb->settings['timeformat'], $mybb->user['lastvisit']);
}

// Otherwise, they've never visited before
else
{
	$lastvisit = $lang->lastvisit_never;
}

// If the board is closed and we have an Administrator, show board closed warning
$bbclosedwarning = '';
if($mybb->settings['boardclosed'] == "yes" && $mybb->usergroup['cancp'] == "yes")
{
	eval("\$bbclosedwarning = \"".$templates->get("global_boardclosed_warning")."\";");
}

// Prepare the main templates for use
unset($admincplink);

// Load appropriate welcome block for the current logged in user
if($mybb->user['uid'] != 0)
{
	// User can access the admin cp and we're not hiding admin cp links, fetch it
	if($mybb->usergroup['cancp'] == "yes" && $mybb->config['hide_admin_links'] != 1)
	{
		eval("\$admincplink = \"".$templates->get("header_welcomeblock_member_admin")."\";");
	}
	// Format the welcome back message
	$lang->welcome_back = sprintf($lang->welcome_back, $mybb->user['username'], $lastvisit);

	// Tell the user their PM usage
	$lang->welcome_pms_usage = sprintf($lang->welcome_pms_usage, mynumberformat($mybb->user['pms_new']), mynumberformat($mybb->user['pms_unread']), mynumberformat($mybb->user['pms_total']));
	eval("\$welcomeblock = \"".$templates->get("header_welcomeblock_member")."\";");
}
// Otherwise, we have a guest
else
{
	eval("\$welcomeblock = \"".$templates->get("header_welcomeblock_guest")."\";");
}

$unreadreports = '';
// This user is a moderator, super moderator or administrator
if($mybb->usergroup['cancp'] == "yes" || $mybb->usergroup['issupermod'] == "yes" || $mybb->usergroup['gid'] == 6)
{
	// Read the reported posts cache
	$reported = $cache->read("reportedposts");

	// 0 or more reported posts currently exist
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

// Got a character set?
if($lang->settings['charset'])
{
	$charset = $lang->settings['charset'];
}
// If not, revert to UTF-8
else
{
	$charset = "UTF-8";
}

// Is this user apart of a banned group?
$bannedwarning = '';
if($mybb->usergroup['isbannedgroup'] == "yes")
{
	// Fetch details on their ban
	$query = $db->simple_select(TABLE_PREFIX."banned", "*", "uid='{$mybb->user['uid']}'");
	$ban = $db->fetch_array($query);
	if($ban['uid'])
	{
		// Format their ban lift date and reason appropriately
		if($ban['lifted'] > 0)
		{
			$banlift = mydate($mybb->settings['dateformat'], $ban['lifted']) . ", " . mydate($mybb->settings['timeformat'], $ban['lifted']);
		}
		else 
		{
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
	if($ban['uid'])
	{
		// Display a nice warning to the user
	}	eval("\$bannedwarning = \"".$templates->get("global_bannedwarning")."\";");
}

// Set up some of the default templates
eval("\$headerinclude = \"".$templates->get("headerinclude")."\";");
eval("\$gobutton = \"".$templates->get("gobutton")."\";");
eval("\$htmldoctype = \"".$templates->get("htmldoctype", 1, 0)."\";");
eval("\$header = \"".$templates->get("header")."\";");

$copy_year = mydate("Y", time());

// Are we showing version numbers in the footer?
if($mybb->settings['showvernum'] == "on")
{
	$mybbversion = $mybboard['internalver'];
}
else
{
	$mybbversion = '';
}
eval("\$footer = \"".$templates->get("footer")."\";");

// Add our main parts to the navigation
$navbits[0]['name'] = $mybb->settings['bbname'];
$navbits[0]['url'] = $mybb->settings['bburl']."/index.php";

// Check banned ip addresses
$bannedips = explode(",", $mybb->settings['bannedips']);
if(is_array($bannedips))
{
	foreach($bannedips as $key => $bannedip)
	{
		$bannedip = trim($bannedip);
		if($bannedip != '')
		{
			// This address is banned, show an error and delete the session
			if(strstr($ipaddress, $bannedip))
			{
				$db->delete_query(TABLE_PREFIX."sessions", "ip='".$db->escape_string($ipaddress)."' OR uid='{$mybb->user['uid']}'");
				error($lang->error_banned);
			}
		}
	}
}
// If the board is closed, the user is not an administrator and they're not trying to login, show the board closed message
if($mybb->settings['boardclosed'] == "yes" && $mybb->usergroup['cancp'] != "yes" && !(basename($_SERVER['PHP_SELF']) == "member.php" && ($mybb->input['action'] == "login" || $mybb->input['action'] == "do_login" || $mybb->input['action'] == "logout")))
{
	// Show error
	$lang->error_boardclosed .= "<blockquote>{$mybb->settings['boardclosed_reason']}</blockquote>";
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
		// User is not an administrator and the load limit is higher than the limit, show an error
		if($mybb->usergroup['cancp'] != "yes" && $load > $mybb->settings['load'] && $mybb->settings['load'] > 0)
		{
			error($lang->error_loadlimit);
		}
	}
}

// If there is a valid referrer in the URL, cookie it
if(!$mybb->user['uid'] && $mybb->settings['usereferrals'] == "yes" && (isset($mybb->input['referrer']) || isset($mybb->input['referrername'])))
{
	if(isset($mybb->input['referrername']))
	{
		$condition = "username='".$db->escape_string($mybb->input['referrername'])."'";
	}
	else
	{
		$condition = "uid='".intval($mybb->input['referrer'])."'";
	}
	$query = $db->simple_select(TABLE_PREFIX."users", "uid", $condition);
	$referrer = $db->fetch_array($query);
	if($referrer['uid'])
	{
		mysetcookie("mybb[referrer]", $referrer['uid']);
	}
}

// Check pages allowable even when not allowed to view board
$allowable_actions = array(
	"member.php" => array(
		"register",
		"do_register",
		"login",
		"do_login",
		"logout",
		"lostpw",
		"do_lostpw",
		"activate",
		"resendactivation",
		"do_resendactivation",
		"resetpassword"
	),
);
if($mybb->usergroup['canview'] != "yes" && !(strtolower(basename($_SERVER['PHP_SELF'])) == "member.php" && in_array($mybb->input['action'], $allowable_actions['member.php'])) && strtolower(basename($_SERVER['PHP_SELF'])) != "captcha.php")
{
	error_no_permission();
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

// Randomly expire threads
if($rand > 8 || isset($mybb->input['force_thread_expiry']))
{
	$db->delete_query(TABLE_PREFIX."threads", "deletetime != '0' AND deletetime < '".time()."'");
}

// Set the link to the archive.
$archive_url = $mybb->settings['bburl']."/archive/index.php";

// Run hooks for end of global.php
$plugins->run_hooks("global_end");

$globaltime = $maintimer->gettime();
?>
