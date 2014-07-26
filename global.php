<?php
/**
 * MyBB 1.6
 * Copyright 2010 MyBB Group, All Rights Reserved
 *
 * Website: http://mybb.com
 * License: http://mybb.com/about/license
 *
 * $Id$
 */

$working_dir = dirname(__FILE__);
if(!$working_dir)
{
	$working_dir = '.';
}

// Load main MyBB core file which begins all of the magic
require_once $working_dir."/inc/init.php";

$shutdown_queries = array();

// Read the usergroups cache as well as the moderators cache
$groupscache = $cache->read("usergroups");

// If the groups cache doesn't exist, update it and re-read it
if(!is_array($groupscache))
{
	$cache->update_usergroups();
	$groupscache = $cache->read("usergroups");
}

if(!defined('THIS_SCRIPT'))
{
	define('THIS_SCRIPT', '');
}

$current_page = my_strtolower(basename(THIS_SCRIPT));

// Send page headers - don't send no-cache headers for attachment.php
if($current_page != "attachment.php")
{
	send_page_headers();
}

// Do not use session system for defined pages
if((@isset($mybb->input['action']) && @isset($nosession[$mybb->input['action']])) || (@isset($mybb->input['thumbnail']) && $current_page == 'attachment.php'))
{
	define("NO_ONLINE", 1);
}

// Create session for this user
require_once MYBB_ROOT."inc/class_session.php";
$session = new session;
$session->init();
$mybb->session = &$session;

$mybb->user['ismoderator'] = is_moderator("", "", $mybb->user['uid']);

// Set our POST validation code here
$mybb->post_code = generate_post_check();

// Set and load the language
if(!empty($mybb->input['language']) && $lang->language_exists($mybb->input['language']) && verify_post_check($mybb->input['my_post_key'], true))
{
	$mybb->settings['bblanguage'] = $mybb->input['language'];
	// If user is logged in, update their language selection with the new one
	if($mybb->user['uid'])
	{
		if($mybb->cookies['mybblang'])
		{
			my_unsetcookie("mybblang");
		}

		$db->update_query("users", array("language" => $db->escape_string($mybb->settings['bblanguage'])), "uid='{$mybb->user['uid']}'");
	}
	// Guest = cookie
	else
	{
		my_setcookie("mybblang", $mybb->settings['bblanguage']);
	}
	$mybb->user['language'] = $mybb->settings['bblanguage'];
}
// Cookied language!
else if(!$mybb->user['uid'] && !empty($mybb->cookies['mybblang']) && $lang->language_exists($mybb->cookies['mybblang']))
{
	$mybb->settings['bblanguage'] = $mybb->cookies['mybblang'];
}
else if(!isset($mybb->settings['bblanguage']))
{
	$mybb->settings['bblanguage'] = "english";
}

// Load language
$lang->set_language($mybb->settings['bblanguage']);
$lang->load("global");
$lang->load("messages");
$newpmmsg = '';

// Run global_start plugin hook now that the basics are set up
$plugins->run_hooks("global_start");

if(function_exists('mb_internal_encoding') && !empty($lang->settings['charset']))
{
	@mb_internal_encoding($lang->settings['charset']);
}

// Select the board theme to use.
$loadstyle = '';
$load_from_forum = 0;
$style = array();

// This user has a custom theme set in their profile
if(isset($mybb->user['style']) && intval($mybb->user['style']) != 0)
{
	$loadstyle = "tid='".$mybb->user['style']."'";
}

$valid = array(
	"showthread.php", 
	"forumdisplay.php",
	"newthread.php",
	"newreply.php",
	"ratethread.php",
	"editpost.php",
	"polls.php",
	"sendthread.php",
	"printthread.php",
	"moderation.php"
);

if(in_array($current_page, $valid))
{
	cache_forums();

	// If we're accessing a post, fetch the forum theme for it and if we're overriding it
	if(!empty($mybb->input['pid']) && THIS_SCRIPT != "polls.php")
	{
		$query = $db->simple_select("posts", "fid", "pid = '".intval($mybb->input['pid'])."'", array("limit" => 1));
		$fid = $db->fetch_field($query, "fid");

		if($fid)
		{
			$style = $forum_cache[$fid];
			$load_from_forum = 1;
		}
	}
	// We have a thread id and a forum id, we can easily fetch the theme for this forum
	else if(!empty($mybb->input['tid']))
	{
		$query = $db->simple_select("threads", "fid", "tid = '".intval($mybb->input['tid'])."'", array("limit" => 1));
		$fid = $db->fetch_field($query, "fid");

		if($fid)
		{
			$style = $forum_cache[$fid];
			$load_from_forum = 1;
		}
	}
	// If we're accessing poll results, fetch the forum theme for it and if we're overriding it
	else if(!empty($mybb->input['pid']) && THIS_SCRIPT == "polls.php")
	{
		$query = $db->simple_select("threads", "fid", "poll = '".intval($mybb->input['pid'])."'", array("limit" => 1));
		$fid = $db->fetch_field($query, "fid");

		if($fid)
		{
			$style = $forum_cache[$fid];
			$load_from_forum = 1;
		}
	}
	// We have a forum id - simply load the theme from it
	else if($mybb->input['fid'])
	{
		$style = $forum_cache[intval($mybb->input['fid'])];
		$load_from_forum = 1;
	}
}
unset($valid);

// From all of the above, a theme was found
if(isset($style['style']) && $style['style'] > 0)
{
	// This theme is forced upon the user, overriding their selection
	if($style['overridestyle'] == 1 || !isset($mybb->user['style']))
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
$query = $db->simple_select("themes", "name, tid, properties, stylesheets", $loadstyle, array('limit' => 1));
$theme = $db->fetch_array($query);

// No theme was found - we attempt to load the master or any other theme
if(!$theme['tid'])
{
	// Missing theme was from a forum, run a query to set any forums using the theme to the default
	if($load_from_forum == 1)
	{
		$db->update_query("forums", array("style" => 0), "style='{$style['style']}'");
	}
	// Missing theme was from a user, run a query to set any users using the theme to the default
	else if($load_from_user == 1)
	{
		$db->update_query("users", array("style" => 0), "style='{$style['style']}'");
	}
	// Attempt to load the master or any other theme if the master is not available
	$query = $db->simple_select("themes", "name, tid, properties, stylesheets", "", array("order_by" => "tid", "limit" => 1));
	$theme = $db->fetch_array($query);
}
$theme = @array_merge($theme, unserialize($theme['properties']));

// Fetch all necessary stylesheets
$stylesheets = '';
$theme['stylesheets'] = unserialize($theme['stylesheets']);
$stylesheet_scripts = array("global", basename($_SERVER['PHP_SELF']));
foreach($stylesheet_scripts as $stylesheet_script)
{
	$stylesheet_actions = array("global");
	if(!empty($mybb->input['action']))
	{
		$stylesheet_actions[] = $mybb->input['action'];
	}
	// Load stylesheets for global actions and the current action
	foreach($stylesheet_actions as $stylesheet_action)
	{
		if(!$stylesheet_action)
		{
			continue;
		}
		
		if(!empty($theme['stylesheets'][$stylesheet_script][$stylesheet_action]))
		{
			// Actually add the stylesheets to the list
			foreach($theme['stylesheets'][$stylesheet_script][$stylesheet_action] as $page_stylesheet)
			{
				if(!empty($already_loaded[$page_stylesheet]))
				{
					continue;
				}
				$stylesheets .= "<link type=\"text/css\" rel=\"stylesheet\" href=\"{$mybb->settings['bburl']}/{$page_stylesheet}\" />\n";
				$already_loaded[$page_stylesheet] = 1;
			}
		}
	}
}

// Are we linking to a remote theme server?
if(substr($theme['imgdir'], 0, 7) == "http://" || substr($theme['imgdir'], 0, 8) == "https://")
{
	// If a language directory for the current language exists within the theme - we use it
	if(!empty($mybb->user['language']))
	{
		$theme['imglangdir'] = $theme['imgdir'].'/'.$mybb->user['language'];
	}
	else
	{
		// Check if a custom language directory exists for this theme
		if(!empty($mybb->settings['bblanguage']))
		{
			$theme['imglangdir'] = $theme['imgdir'].'/'.$mybb->settings['bblanguage'];
		}
		// Otherwise, the image language directory is the same as the language directory for the theme
		else
		{
			$theme['imglangdir'] = $theme['imgdir'];
		}
	}
}
else
{
	if(!@is_dir($theme['imgdir']))
	{
		$theme['imgdir'] = "images";
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
}

// Theme logo - is it a relative URL to the forum root? Append bburl
if(!preg_match("#^(\.\.?(/|$)|([a-z0-9]+)://)#i", $theme['logo']) && substr($theme['logo'], 0, 1) != "/")
{
	$theme['logo'] = $mybb->settings['bburl']."/".$theme['logo'];
}

// Load Main Templates and Cached Templates
if(isset($templatelist))
{
	$templatelist .= ',';
}
$templatelist .= "headerinclude,header,footer,gobutton,htmldoctype,header_welcomeblock_member,header_welcomeblock_guest,header_welcomeblock_member_admin,global_pm_alert,global_unreadreports";
$templatelist .= ",global_pending_joinrequests,nav,nav_sep,nav_bit,nav_sep_active,nav_bit_active,footer_languageselect,header_welcomeblock_member_moderator,redirect,error";
$templatelist .= ",global_boardclosed_warning,global_bannedwarning,error_inline,error_nopermission_loggedin,error_nopermission";
$templates->cache($db->escape_string($templatelist));

// Set the current date and time now
$datenow = my_date($mybb->settings['dateformat'], TIME_NOW, '', false);
$timenow = my_date($mybb->settings['timeformat'], TIME_NOW);
$lang->welcome_current_time = $lang->sprintf($lang->welcome_current_time, $datenow.', '.$timenow);

// Format the last visit date of this user appropriately
if(isset($mybb->user['lastvisit']))
{
	$lastvisit = my_date($mybb->settings['dateformat'], $mybb->user['lastvisit']) . ', ' . my_date($mybb->settings['timeformat'], $mybb->user['lastvisit']);
}

// Otherwise, they've never visited before
else
{
	$lastvisit = $lang->lastvisit_never;
}

// If the board is closed and we have an Administrator, show board closed warning
$bbclosedwarning = '';
if($mybb->settings['boardclosed'] == 1 && $mybb->usergroup['cancp'] == 1)
{
	eval("\$bbclosedwarning = \"".$templates->get("global_boardclosed_warning")."\";");
}

// Prepare the main templates for use
unset($admincplink);

// Load appropriate welcome block for the current logged in user
if($mybb->user['uid'] != 0)
{
	// User can access the admin cp and we're not hiding admin cp links, fetch it
	if($mybb->usergroup['cancp'] == 1 && $mybb->config['hide_admin_links'] != 1)
	{
		$admin_dir = $config['admin_dir'];
		eval("\$admincplink = \"".$templates->get("header_welcomeblock_member_admin")."\";");
	}
	
	if($mybb->usergroup['canmodcp'] == 1)
	{
		eval("\$modcplink = \"".$templates->get("header_welcomeblock_member_moderator")."\";");
	}
	
	// Format the welcome back message
	$lang->welcome_back = $lang->sprintf($lang->welcome_back, build_profile_link($mybb->user['username'], $mybb->user['uid']), $lastvisit);

	// Tell the user their PM usage
	$lang->welcome_pms_usage = $lang->sprintf($lang->welcome_pms_usage, my_number_format($mybb->user['pms_unread']), my_number_format($mybb->user['pms_total']));
	eval("\$welcomeblock = \"".$templates->get("header_welcomeblock_member")."\";");
}
// Otherwise, we have a guest
else
{
	switch($mybb->settings['username_method'])
	{
		case 0:
			$login_username = $lang->login_username;
			break;
		case 1:
			$login_username = $lang->login_username1;
			break;
		case 2:
			$login_username = $lang->login_username2;
			break;
		default:
			$login_username = $lang->login_username;
			break;
	}
	eval("\$welcomeblock = \"".$templates->get("header_welcomeblock_guest")."\";");
}

$pending_joinrequests = '';

// Read the group leaders cache
$groupleaders = $cache->read("groupleaders");
if($mybb->user['uid'] != 0 && is_array($groupleaders) && array_key_exists($mybb->user['uid'], $groupleaders))
{
	$groupleader = $groupleaders[$mybb->user['uid']];
	
	$gids = "";
	foreach($groupleader as $user)
	{
		if($user['canmanagerequests'] != 1)
		{
			continue;
		}
		
		$gids .= ",{$user['gid']}";
	}
	
	$query = $db->simple_select("joinrequests", "COUNT(uid) as total", "gid IN (0{$gids})");
	$total_joinrequests = $db->fetch_field($query, "total");
	
	$pending_joinrequests = "";
	if($total_joinrequests > 0)
	{
		if($total_joinrequests == 1)
		{
			$lang->pending_joinrequests = $lang->pending_joinrequest;
		}
		else
		{
			$lang->pending_joinrequests = $lang->sprintf($lang->pending_joinrequests, $total_joinrequests);
		}
		eval("\$pending_joinrequests = \"".$templates->get("global_pending_joinrequests")."\";");
	}
}

$unreadreports = '';
// This user is a moderator, super moderator or administrator
if($mybb->usergroup['cancp'] == 1 || ($mybb->user['ismoderator'] && $mybb->usergroup['canmodcp']))
{
	// Only worth checking if we are here because we have ACP permissions and the other condition fails
	if($mybb->usergroup['cancp'] == 1 && !($mybb->user['ismoderator'] && $mybb->usergroup['canmodcp']))
	{
		// First we check if the user's a super admin: if yes, we don't care about permissions
		$can_access_moderationqueue = true;
		$is_super_admin = is_super_admin($recipient['uid']);
		if(!$is_super_admin)
		{
			// Include admin functions
			if(!file_exists(MYBB_ROOT.$mybb->config['admin_dir']."/inc/functions.php"))
			{
				$can_access_moderationqueue = false;
			}

			require_once MYBB_ROOT.$mybb->config['admin_dir']."/inc/functions.php";

			// Verify if we have permissions to access forum-moderation_queue
			require_once MYBB_ROOT.$mybb->config['admin_dir']."/modules/forum/module_meta.php";
			if(function_exists("forum_admin_permissions"))
			{
				// Get admin permissions
				$adminperms = get_admin_permissions($mybb->user['uid']);

				$permissions = forum_admin_permissions();
				if(array_key_exists('moderation_queue', $permissions['permissions']) && $adminperms['forum']['moderation_queue'] != 1)
				{
					$can_access_moderationqueue = false;
				}
			}
		}
	}
	else
	{
		$can_access_moderationqueue = false;
	}
	
	if($can_access_moderationqueue || ($mybb->user['ismoderator'] && $mybb->usergroup['canmodcp']))
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
				$lang->unread_reports = $lang->sprintf($lang->unread_reports, $reported['unread']);
			}
			eval("\$unreadreports = \"".$templates->get("global_unreadreports")."\";");
		}
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
if($mybb->usergroup['isbannedgroup'] == 1)
{
	// Fetch details on their ban
	$query = $db->simple_select("banned", "*", "uid='{$mybb->user['uid']}'", array('limit' => 1));
	$ban = $db->fetch_array($query);
	if($ban['uid'])
	{
		// Format their ban lift date and reason appropriately
		if($ban['lifted'] > 0)
		{
			$banlift = my_date($mybb->settings['dateformat'], $ban['lifted']) . ", " . my_date($mybb->settings['timeformat'], $ban['lifted']);
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
	// Display a nice warning to the user
	eval("\$bannedwarning = \"".$templates->get("global_bannedwarning")."\";");
}

$lang->ajax_loading = str_replace("'", "\\'", $lang->ajax_loading);

// Check if this user has a new private message.
$pm_notice = '';
if(isset($mybb->user['pmnotice']) && $mybb->user['pmnotice'] == 2 && $mybb->user['pms_unread'] > 0 && $mybb->settings['enablepms'] != 0 && $mybb->usergroup['canusepms'] != 0 && $mybb->usergroup['canview'] != 0 && ($current_page != "private.php" || $mybb->input['action'] != "read"))
{
	if(!$parser)
	{
		require_once MYBB_ROOT.'inc/class_parser.php';
		$parser = new postParser;
	}

	$query = $db->query("
		SELECT pm.subject, pm.pmid, fu.username AS fromusername, fu.uid AS fromuid
		FROM ".TABLE_PREFIX."privatemessages pm
		LEFT JOIN ".TABLE_PREFIX."users fu ON (fu.uid=pm.fromid)
		WHERE pm.folder='1' AND pm.uid='{$mybb->user['uid']}' AND pm.status='0'
		ORDER BY pm.dateline DESC
		LIMIT 1
	");

	$pm = $db->fetch_array($query);
	$pm['subject'] = $parser->parse_badwords($pm['subject']);
	
	if($pm['fromuid'] == 0)
	{
		$pm['fromusername'] = $lang->mybb_engine;
		$user_text = $pm['fromusername'];
	}
	else
	{
		$user_text = build_profile_link($pm['fromusername'], $pm['fromuid']);
	}

	if($mybb->user['pms_unread'] == 1)
	{
		$privatemessage_text = $lang->sprintf($lang->newpm_notice_one, $user_text, $pm['pmid'], htmlspecialchars_uni($pm['subject']));
	}
	else
	{
		$privatemessage_text = $lang->sprintf($lang->newpm_notice_multiple, $mybb->user['pms_unread'], $user_text, $pm['pmid'], htmlspecialchars_uni($pm['subject']));
	}
	eval("\$pm_notice = \"".$templates->get("global_pm_alert")."\";");
}

// Set up some of the default templates
eval("\$headerinclude = \"".$templates->get("headerinclude")."\";");
eval("\$gobutton = \"".$templates->get("gobutton")."\";");
eval("\$htmldoctype = \"".$templates->get("htmldoctype", 1, 0)."\";");
eval("\$header = \"".$templates->get("header")."\";");

$copy_year = my_date("Y", TIME_NOW);

// Are we showing version numbers in the footer?
if($mybb->settings['showvernum'] == 1)
{
	$mybbversion = ' '.$mybb->version;
}
else
{
	$mybbversion = '';
}

// Check to see if we have any tasks to run
$task_cache = $cache->read("tasks");
if(!$task_cache['nextrun'])
{
	$task_cache['nextrun'] = TIME_NOW;
}
if($task_cache['nextrun'] <= TIME_NOW)
{
	$task_image = "<img src=\"{$mybb->settings['bburl']}/task.php\" border=\"0\" width=\"1\" height=\"1\" alt=\"\" />";
}
else
{
	$task_image = '';
}

// Are we showing the quick language selection box?
$lang_select = $lang_options = '';
if($mybb->settings['showlanguageselect'] != 0)
{
	$languages = $lang->get_languages();
	foreach($languages as $key => $language)
	{
		$language = htmlspecialchars_uni($language);
		// Current language matches
		if($lang->language == $key)
		{
			$lang_options .= "<option value=\"{$key}\" selected=\"selected\">&nbsp;&nbsp;&nbsp;{$language}</option>\n";
		}
		else
		{
			$lang_options .= "<option value=\"{$key}\">&nbsp;&nbsp;&nbsp;{$language}</option>\n";
		}
	}
	
	$lang_redirect_url = get_current_location(true, 'language');
	
	eval("\$lang_select = \"".$templates->get("footer_languageselect")."\";");
}

// DST Auto detection enabled?
$auto_dst_detection = '';
if($mybb->user['uid'] > 0 && $mybb->user['dstcorrection'] == 2)
{
	$auto_dst_detection = "<script type=\"text/javascript\">if(MyBB) { Event.observe(window, 'load', function() { MyBB.detectDSTChange('".($mybb->user['timezone']+$mybb->user['dst'])."'); }); }</script>\n";
}

eval("\$footer = \"".$templates->get("footer")."\";");

// Add our main parts to the navigation
$navbits = array();
$navbits[0]['name'] = $mybb->settings['bbname_orig'];
$navbits[0]['url'] = $mybb->settings['bburl']."/index.php";

// Set the link to the archive.
$archive_url = $mybb->settings['bburl']."/archive/index.php";

// Check banned ip addresses
if(is_banned_ip($session->ipaddress, true))
{
	if ($mybb->user['uid'])
    {
        $db->delete_query("sessions", "ip='".$db->escape_string($session->ipaddress)."' OR uid='{$mybb->user['uid']}'");
    }
    else
    {
        $db->delete_query("sessions", "ip='".$db->escape_string($session->ipaddress)."'");
    }
	error($lang->error_banned);
}

$closed_bypass = array(
	"member.php" => array(
		"login",
		"do_login",
		"logout",
	),
	"captcha.php",
);

// If the board is closed, the user is not an administrator and they're not trying to login, show the board closed message
if($mybb->settings['boardclosed'] == 1 && $mybb->usergroup['cancp'] != 1 && !in_array($current_page, $closed_bypass) && (!is_array($closed_bypass[$current_page]) || !in_array($mybb->input['action'], $closed_bypass[$current_page])))
{
	// Show error
	$lang->error_boardclosed .= "<blockquote>{$mybb->settings['boardclosed_reason']}</blockquote>";
	error($lang->error_boardclosed);
	exit;
}

// Load Limiting
if($mybb->usergroup['cancp'] != 1 && $mybb->settings['load'] > 0 && ($load = get_server_load()) && $load != $lang->unknown && $load > $mybb->settings['load'])
{
	// User is not an administrator and the load limit is higher than the limit, show an error
	error($lang->error_loadlimit);
}

// If there is a valid referrer in the URL, cookie it
if(!$mybb->user['uid'] && $mybb->settings['usereferrals'] == 1 && (isset($mybb->input['referrer']) || isset($mybb->input['referrername'])))
{
	if(isset($mybb->input['referrername']))
	{
		$condition = "username='".$db->escape_string($mybb->input['referrername'])."'";
	}
	else
	{
		$condition = "uid='".intval($mybb->input['referrer'])."'";
	}
	$query = $db->simple_select("users", "uid", $condition, array('limit' => 1));
	$referrer = $db->fetch_array($query);
	if($referrer['uid'])
	{
		my_setcookie("mybb[referrer]", $referrer['uid']);
	}
}

if($mybb->usergroup['canview'] != 1)
{
	// Check pages allowable even when not allowed to view board
	if(defined("ALLOWABLE_PAGE"))
	{
		if(is_string(ALLOWABLE_PAGE))
		{
			$allowable_actions = explode(',', ALLOWABLE_PAGE);
			
			if(!in_array($mybb->input['action'], $allowable_actions))
			{
				error_no_permission();
			}
			
			unset($allowable_actions);
		}
		else if(ALLOWABLE_PAGE !== 1)
		{
			error_no_permission();
		}
	}
	else
	{
		error_no_permission();
	}
}

// Find out if this user of ours is using a banned email address.
// If they are, redirect them to change it
if($mybb->user['uid'] && is_banned_email($mybb->user['email']) && $mybb->settings['emailkeep'] != 1)
{
	if(THIS_SCRIPT != "usercp.php" || THIS_SCRIPT == "usercp.php" && $mybb->input['action'] != "email" && $mybb->input['action'] != "do_email")
	{
		redirect("usercp.php?action=email");
	}
	else if($mybb->request_method != "post")
	{
		$banned_email_error = inline_error(array($lang->banned_email_warning));
	}
}

// work out which items the user has collapsed
$colcookie = empty($mybb->cookies['collapsed']) ? false : $mybb->cookies['collapsed'];

// set up collapsable items (to automatically show them us expanded)
$collapsed = array('boardstats' => '', 'boardstats_e' => '', 'quickreply' => '', 'quickreply_e' => '');
$collapsedimg = $collapsed;

if($colcookie)
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

// Run hooks for end of global.php
$plugins->run_hooks("global_end");

$globaltime = $maintimer->getTime();
?>
