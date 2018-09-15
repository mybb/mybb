<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

$working_dir = dirname(__FILE__);
if(!$working_dir)
{
	$working_dir = '.';
}

$shutdown_queries = $shutdown_functions = array();

// Load main MyBB core file which begins all of the magic
require_once $working_dir.'/inc/init.php';

// Read the usergroups cache as well as the moderators cache
$groupscache = $cache->read('usergroups');

// If the groups cache doesn't exist, update it and re-read it
if(!is_array($groupscache))
{
	$cache->update_usergroups();
	$groupscache = $cache->read('usergroups');
}

$current_page = my_strtolower(basename(THIS_SCRIPT));

// Send page headers - don't send no-cache headers for attachment.php
if($current_page != 'attachment.php')
{
	send_page_headers();
}

// Do not use session system for defined pages
if((isset($mybb->input['action']) && isset($nosession[$mybb->input['action']])) || (isset($mybb->input['thumbnail']) && $current_page == 'attachment.php'))
{
	define('NO_ONLINE', 1);
}

// Create session for this user
require_once MYBB_ROOT.'inc/class_session.php';
$session = new session;
$session->init();
$mybb->session = &$session;

$mybb->user['ismoderator'] = is_moderator(0, '', $mybb->user['uid']);

// Set our POST validation code here
$mybb->post_code = generate_post_check();

// Set and load the language
if(isset($mybb->input['language']) && $lang->language_exists($mybb->get_input('language')) && verify_post_check($mybb->get_input('my_post_key'), true))
{
	$mybb->settings['bblanguage'] = $mybb->get_input('language');
	// If user is logged in, update their language selection with the new one
	if($mybb->user['uid'])
	{
		if(isset($mybb->cookies['mybblang']))
		{
			my_unsetcookie('mybblang');
		}

		$db->update_query('users', array('language' => $db->escape_string($mybb->settings['bblanguage'])), "uid = '{$mybb->user['uid']}'");
	}
	// Guest = cookie
	else
	{
		my_setcookie('mybblang', $mybb->settings['bblanguage']);
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
	$mybb->settings['bblanguage'] = 'english';
}

// Load language
$lang->set_language($mybb->settings['bblanguage']);
$lang->load('global');
$lang->load('messages');

// Wipe lockout cookie if enough time has passed
if($mybb->cookies['lockoutexpiry'] && $mybb->cookies['lockoutexpiry'] < TIME_NOW)
{
	my_unsetcookie('lockoutexpiry');
}

// Run global_start plugin hook now that the basics are set up
$plugins->run_hooks('global_start');

if(function_exists('mb_internal_encoding') && !empty($lang->settings['charset']))
{
	@mb_internal_encoding($lang->settings['charset']);
}

// Select the board theme to use.
$loadstyle = '';
$load_from_forum = $load_from_user = 0;
$style = array();

// The user used our new quick theme changer
if(isset($mybb->input['theme']) && verify_post_check($mybb->get_input('my_post_key'), true))
{
	// Set up user handler.
	require_once MYBB_ROOT.'inc/datahandlers/user.php';
	$userhandler = new UserDataHandler('update');

	$user = array(
		'uid'	=> $mybb->user['uid'],
		'style'	=> $mybb->get_input('theme', MyBB::INPUT_INT),
		'usergroup'	=> $mybb->user['usergroup'],
		'additionalgroups'	=> $mybb->user['additionalgroups']
	);

	$userhandler->set_data($user);

	// validate_user verifies the style if it is set in the data array.
	if($userhandler->validate_user())
	{
		$mybb->user['style'] = $user['style'];

		// If user is logged in, update their theme selection with the new one
		if($mybb->user['uid'])
		{
			if(isset($mybb->cookies['mybbtheme']))
			{
				my_unsetcookie('mybbtheme');
			}

			$userhandler->update_user();
		}
		// Guest = cookie
		else
		{
			my_setcookie('mybbtheme', $user['style']);
		}
	}
}
// Cookied theme!
else if(!$mybb->user['uid'] && !empty($mybb->cookies['mybbtheme']))
{
	$mybb->user['style'] = (int)$mybb->cookies['mybbtheme'];
}

// This user has a custom theme set in their profile
if(isset($mybb->user['style']) && (int)$mybb->user['style'] != 0)
{
	$mybb->user['style'] = (int)$mybb->user['style'];

	$loadstyle = "tid = '{$mybb->user['style']}'";
	$load_from_user = 1;
}

$valid = array(
	'showthread.php',
	'forumdisplay.php',
	'newthread.php',
	'newreply.php',
	'ratethread.php',
	'editpost.php',
	'polls.php',
	'sendthread.php',
	'printthread.php',
	'moderation.php'
);

if(in_array($current_page, $valid))
{
	cache_forums();

	// If we're accessing a post, fetch the forum theme for it and if we're overriding it
	if(isset($mybb->input['pid']) && THIS_SCRIPT != "polls.php")
	{
		$query = $db->simple_select("posts", "fid", "pid = '{$mybb->input['pid']}'", array("limit" => 1));
		$fid = $db->fetch_field($query, 'fid');

		if($fid)
		{
			$style = $forum_cache[$fid];
			$load_from_forum = 1;
		}
	}
	// We have a thread id and a forum id, we can easily fetch the theme for this forum
	else if(isset($mybb->input['tid']))
	{
		$query = $db->simple_select('threads', 'fid', "tid = '{$mybb->input['tid']}'", array('limit' => 1));
		$fid = $db->fetch_field($query, 'fid');

		if($fid)
		{
			$style = $forum_cache[$fid];
			$load_from_forum = 1;
		}
	}
	// If we're accessing poll results, fetch the forum theme for it and if we're overriding it
	else if(isset($mybb->input['pid']) && THIS_SCRIPT == "polls.php")
	{
		$query = $db->simple_select('threads', 'fid', "poll = '{$mybb->input['pid']}'", array('limit' => 1));
		$fid = $db->fetch_field($query, 'fid');

		if($fid)
		{
			$style = $forum_cache[$fid];
			$load_from_forum = 1;
		}
	}
	// We have a forum id - simply load the theme from it
	else if(isset($mybb->input['fid']) && isset($forum_cache[$mybb->input['fid']]))
	{
		$style = $forum_cache[$mybb->input['fid']];
		$load_from_forum = 1;
	}
}
unset($valid);

// From all of the above, a theme was found
if(isset($style['style']) && $style['style'] > 0)
{
	$style['style'] = (int)$style['style'];

	// This theme is forced upon the user, overriding their selection
	if($style['overridestyle'] == 1 || !isset($mybb->user['style']))
	{
		$loadstyle = "tid = '{$style['style']}'";
	}
}

// After all of that no theme? Load the board default
if(empty($loadstyle))
{
	$loadstyle = "def='1'";
}

// Fetch the theme to load from the cache
if($loadstyle != "def='1'")
{
	$query = $db->simple_select('themes', 'name, tid, properties, stylesheets, allowedgroups', $loadstyle, array('limit' => 1));
	$theme = $db->fetch_array($query);

	if(isset($theme['tid']) && !$load_from_forum && !is_member($theme['allowedgroups']) && $theme['allowedgroups'] != 'all')
	{
		if($load_from_user == 1)
		{
			$db->update_query('users', array('style' => 0), "style='{$mybb->user['style']}' AND uid='{$mybb->user['uid']}'");
		}

		if(isset($mybb->cookies['mybbtheme']))
		{
			my_unsetcookie('mybbtheme');
		}

		$loadstyle = "def='1'";
	}
}

if($loadstyle == "def='1'")
{
	if(!$cache->read('default_theme'))
	{
		$cache->update_default_theme();
	}

	$theme = $cache->read('default_theme');

	$load_from_forum = $load_from_user = 0;
}

// No theme was found - we attempt to load the master or any other theme
if(!isset($theme['tid']) || isset($theme['tid']) && !$theme['tid'])
{
	// Missing theme was from a forum, run a query to set any forums using the theme to the default
	if($load_from_forum == 1)
	{
		$db->update_query('forums', array('style' => 0), "style = '{$style['style']}'");
	}
	// Missing theme was from a user, run a query to set any users using the theme to the default
	else if($load_from_user == 1)
	{
		$db->update_query('users', array('style' => 0), "style = '{$mybb->user['style']}'");
	}

	// Attempt to load the master or any other theme if the master is not available
	$query = $db->simple_select('themes', 'name, tid, properties, stylesheets', '', array('order_by' => 'tid', 'limit' => 1));
	$theme = $db->fetch_array($query);
}
$theme = @array_merge($theme, my_unserialize($theme['properties']));

// Fetch all necessary stylesheets
$stylesheets = '';
$theme['stylesheets'] = my_unserialize($theme['stylesheets']);
$stylesheet_scripts = array("global", basename($_SERVER['PHP_SELF']));
if(!empty($theme['color']))
{
	$stylesheet_scripts[] = $theme['color'];
}
$stylesheet_actions = array("global");
if(!empty($mybb->input['action']))
{
	$stylesheet_actions[] = $mybb->get_input('action');
}
foreach($stylesheet_scripts as $stylesheet_script)
{
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

				if(strpos($page_stylesheet, 'css.php') !== false)
				{
					$stylesheet_url = $mybb->settings['bburl'] . '/' . $page_stylesheet;
				}
				else
				{
					$stylesheet_url = $mybb->get_asset_url($page_stylesheet);
				}

				if($mybb->settings['minifycss'])
				{
					$stylesheet_url = str_replace('.css', '.min.css', $stylesheet_url);
				}

				if(strpos($page_stylesheet, 'css.php') !== false)
				{
					// We need some modification to get it working with the displayorder
					$query_string = parse_url($stylesheet_url, PHP_URL_QUERY);
					$id = (int) my_substr($query_string, 11);
					$query = $db->simple_select("themestylesheets", "name", "sid={$id}");
					$real_name = $db->fetch_field($query, "name");
					$theme_stylesheets[$real_name] = "<link type=\"text/css\" rel=\"stylesheet\" href=\"{$stylesheet_url}\" />\n";
				}
				else
				{
					$theme_stylesheets[basename($page_stylesheet)] = "<link type=\"text/css\" rel=\"stylesheet\" href=\"{$stylesheet_url}\" />\n";
				}

				$already_loaded[$page_stylesheet] = 1;
			}
		}
	}
}
unset($actions);

if(!empty($theme_stylesheets) && is_array($theme['disporder']))
{
	foreach($theme['disporder'] as $style_name => $order)
	{
		if(!empty($theme_stylesheets[$style_name]))
		{
			$stylesheets .= $theme_stylesheets[$style_name];
		}
	}
}

// Are we linking to a remote theme server?
if(my_validate_url($theme['imgdir']))
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
	$img_directory = $theme['imgdir'];

	if($mybb->settings['usecdn'] && !empty($mybb->settings['cdnpath']))
	{
		$img_directory = rtrim($mybb->settings['cdnpath'], '/') . '/' . ltrim($theme['imgdir'], '/');
	}

	if(!@is_dir($img_directory))
	{
		$theme['imgdir'] = 'images';
	}

	// If a language directory for the current language exists within the theme - we use it
	if(!empty($mybb->user['language']) && is_dir($img_directory.'/'.$mybb->user['language']))
	{
		$theme['imglangdir'] = $theme['imgdir'].'/'.$mybb->user['language'];
	}
	else
	{
		// Check if a custom language directory exists for this theme
		if(is_dir($img_directory.'/'.$mybb->settings['bblanguage']))
		{
			$theme['imglangdir'] = $theme['imgdir'].'/'.$mybb->settings['bblanguage'];
		}
		// Otherwise, the image language directory is the same as the language directory for the theme
		else
		{
			$theme['imglangdir'] = $theme['imgdir'];
		}
	}

	$theme['imgdir'] = $mybb->get_asset_url($theme['imgdir']);
	$theme['imglangdir'] = $mybb->get_asset_url($theme['imglangdir']);
}

// Theme logo - is it a relative URL to the forum root? Append bburl
if(!preg_match("#^(\.\.?(/|$)|([a-z0-9]+)://)#i", $theme['logo']) && substr($theme['logo'], 0, 1) != '/')
{
	$theme['logo'] = $mybb->get_asset_url($theme['logo']);
}

// Load Main Templates and Cached Templates
if(isset($templatelist))
{
	$templatelist .= ',';
}
else
{
	$templatelist = '';
}

$templatelist .= "headerinclude,header,footer,gobutton,htmldoctype,header_welcomeblock_member,header_welcomeblock_member_user,header_welcomeblock_member_moderator,header_welcomeblock_member_admin,error";
$templatelist .= ",global_pending_joinrequests,global_awaiting_activation,nav,nav_sep,nav_bit,nav_sep_active,nav_bit_active,footer_languageselect,footer_themeselect,global_unreadreports,footer_contactus";
$templatelist .= ",global_boardclosed_warning,global_bannedwarning,error_inline,error_inline_item,error_nopermission_loggedin,error_nopermission,global_pm_alert,header_menu_search,header_menu_portal,redirect,footer_languageselect_option";
$templatelist .= ",video_dailymotion_embed,video_facebook_embed,video_liveleak_embed,video_metacafe_embed,video_myspacetv_embed,video_mixer_embed,video_vimeo_embed,video_yahoo_embed,video_youtube_embed,debug_summary";
$templatelist .= ",smilieinsert_row,smilieinsert_row_empty,smilieinsert,smilieinsert_getmore,smilieinsert_smilie,global_board_offline_modal,footer_themeselector,task_image,usercp_themeselector_option,php_warnings";
$templatelist .= ",mycode_code,mycode_email,mycode_img,mycode_php,mycode_quote_post,mycode_size_int,mycode_url,global_no_permission_modal,global_boardclosed_reason,nav_dropdown,global_remote_avatar_notice";
$templatelist .= ",header_welcomeblock_member_pms,header_welcomeblock_member_search,header_welcomeblock_guest,header_welcomeblock_guest_login_modal,header_welcomeblock_guest_login_modal_lockout";
$templatelist .= ",header_menu_calendar,header_menu_memberlist,global_dst_detection,header_quicksearch,smilie";
$templates->cache($db->escape_string($templatelist));

// Set the current date and time now
$datenow = my_date($mybb->settings['dateformat'], TIME_NOW, '', false);
$timenow = my_date($mybb->settings['timeformat'], TIME_NOW);
$lang->welcome_current_time = $lang->sprintf($lang->welcome_current_time, $datenow . $lang->comma . $timenow);

// Format the last visit date of this user appropriately
if(isset($mybb->user['lastvisit']))
{
	$lastvisit = my_date('relative', $mybb->user['lastvisit'], '', 2);
}
// Otherwise, they've never visited before
else
{
	$lastvisit = $lang->lastvisit_never;
}

$plugins->run_hooks('global_intermediate');

// If the board is closed and we have a usergroup allowed to view the board when closed, then show board closed warning
$bbclosedwarning = '';
if($mybb->settings['boardclosed'] == 1 && $mybb->usergroup['canviewboardclosed'] == 1)
{
	eval('$bbclosedwarning = "'.$templates->get('global_boardclosed_warning').'";');
}

// Prepare the main templates for use
$admincplink = $modcplink = $usercplink = '';

// Load appropriate welcome block for the current logged in user
if($mybb->user['uid'] != 0)
{
	// User can access the admin cp and we're not hiding admin cp links, fetch it
	if($mybb->usergroup['cancp'] == 1 && $mybb->config['hide_admin_links'] != 1)
	{
		$admin_dir = $config['admin_dir'];
		eval('$admincplink = "'.$templates->get('header_welcomeblock_member_admin').'";');
	}

	if($mybb->usergroup['canmodcp'] == 1)
	{
		eval('$modcplink = "'.$templates->get('header_welcomeblock_member_moderator').'";');
	}

	if($mybb->usergroup['canusercp'] == 1)
	{
		eval('$usercplink = "'.$templates->get('header_welcomeblock_member_user').'";');
	}

	// Format the welcome back message
	$lang->welcome_back = $lang->sprintf($lang->welcome_back, build_profile_link(htmlspecialchars_uni($mybb->user['username']), $mybb->user['uid']), $lastvisit);

	$searchlink = '';
	if($mybb->usergroup['cansearch'] == 1)
	{
		eval('$searchlink = "'.$templates->get('header_welcomeblock_member_search').'";');
	}

	// Tell the user their PM usage
	$pmslink = '';
	if($mybb->settings['enablepms'] != 0 && $mybb->usergroup['canusepms'] == 1)
	{
		$lang->welcome_pms_usage = $lang->sprintf($lang->welcome_pms_usage, my_number_format($mybb->user['pms_unread']), my_number_format($mybb->user['pms_total']));

		eval('$pmslink = "'.$templates->get('header_welcomeblock_member_pms').'";');
	}

	eval('$welcomeblock = "'.$templates->get('header_welcomeblock_member').'";');
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

	if($mybb->cookies['lockoutexpiry'])
	{
		$secsleft = (int)($mybb->cookies['lockoutexpiry'] - TIME_NOW);
		$hoursleft = floor($secsleft / 3600);
		$minsleft = floor(($secsleft / 60) % 60);
		$secsleft = floor($secsleft % 60);

		$lang->failed_login_wait = $lang->sprintf($lang->failed_login_wait, $hoursleft, $minsleft, $secsleft);

		eval('$loginform = "'.$templates->get('header_welcomeblock_guest_login_modal_lockout').'";');
	}
	else
	{
		eval('$loginform = "'.$templates->get('header_welcomeblock_guest_login_modal').'";');
	}

	eval('$welcomeblock = "'.$templates->get('header_welcomeblock_guest').'";');
}

// Display menu links and quick search if user has permission
$menu_search = $menu_memberlist = $menu_portal = $menu_calendar = $quicksearch = '';
if($mybb->usergroup['cansearch'] == 1)
{
	eval('$menu_search = "'.$templates->get('header_menu_search').'";');
	eval('$quicksearch = "'.$templates->get('header_quicksearch').'";');
}

if($mybb->settings['enablememberlist'] == 1 && $mybb->usergroup['canviewmemberlist'] == 1)
{
	eval('$menu_memberlist = "'.$templates->get('header_menu_memberlist').'";');
}

if($mybb->settings['enablecalendar'] == 1 && $mybb->usergroup['canviewcalendar'] == 1)
{
	eval('$menu_calendar = "'.$templates->get('header_menu_calendar').'";');
}

if($mybb->settings['portal'] == 1)
{
	eval('$menu_portal = "'.$templates->get('header_menu_portal').'";');
}

// See if there are any pending join requests for group leaders
$pending_joinrequests = '';
$groupleaders = $cache->read('groupleaders');
if($mybb->user['uid'] != 0 && is_array($groupleaders) && array_key_exists($mybb->user['uid'], $groupleaders))
{
	$groupleader = $groupleaders[$mybb->user['uid']];
	$showjoinnotice = false;

	$gids = "'0'";
	foreach($groupleader as $user)
	{
		if($user['canmanagerequests'] != 1)
		{
			continue;
		}

		$user['gid'] = (int)$user['gid'];

		if(!empty($groupscache[$user['gid']]['joinable']) && $groupscache[$user['gid']]['joinable'] == 1)
		{
			$showjoinnotice = true;
			$gids .= ",'{$user['gid']}'";
		}
	}

	if($showjoinnotice)
	{
		$query = $db->simple_select('joinrequests', 'COUNT(uid) as total', "gid IN ({$gids}) AND invite='0'");
		$total_joinrequests = $db->fetch_field($query, 'total');

		if($total_joinrequests > 0)
		{
			if($total_joinrequests == 1)
			{
				$lang->pending_joinrequests = $lang->pending_joinrequest;
			}
			else
			{
				$total_joinrequests = my_number_format($total_joinrequests);
				$lang->pending_joinrequests = $lang->sprintf($lang->pending_joinrequests, $total_joinrequests);
			}

			eval('$pending_joinrequests = "'.$templates->get('global_pending_joinrequests').'";');
		}
	}
}

$unreadreports = '';
// This user is a moderator, super moderator or administrator
if($mybb->settings['reportmethod'] == "db" && ($mybb->usergroup['cancp'] == 1 || ($mybb->user['ismoderator'] && $mybb->usergroup['canmodcp'] == 1 && $mybb->usergroup['canmanagereportedcontent'] == 1)))
{
	// Only worth checking if we are here because we have ACP permissions and the other condition fails
	if($mybb->usergroup['cancp'] == 1 && !($mybb->user['ismoderator'] && $mybb->usergroup['canmodcp'] == 1 && $mybb->usergroup['canmanagereportedcontent'] == 1))
	{
		// First we check if the user's a super admin: if yes, we don't care about permissions
		$can_access_moderationqueue = true;
		$is_super_admin = is_super_admin($mybb->user['uid']);
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

	if($can_access_moderationqueue || ($mybb->user['ismoderator'] && $mybb->usergroup['canmodcp'] == 1 && $mybb->usergroup['canmanagereportedcontent'] == 1))
	{
		// Read the reported content cache
		$reported = $cache->read('reportedcontent');

		// 0 or more reported items currently exist
		if($reported['unread'] > 0)
		{
			// We want to avoid one extra query for users that can moderate any forum
			if($mybb->usergroup['cancp'] || $mybb->usergroup['issupermod'])
			{
				$unread = (int)$reported['unread'];
			}
			else
			{
				$unread = 0;
				$query = $db->simple_select('reportedcontent', 'id3', "reportstatus='0' AND (type = 'post' OR type = '')");

				while($fid = $db->fetch_field($query, 'id3'))
				{
					if(is_moderator($fid, "canmanagereportedposts"))
					{
						++$unread;
					}
				}
			}

			if($unread > 0)
			{
				if($unread == 1)
				{
					$lang->unread_reports = $lang->unread_report;
				}
				else
				{
					$lang->unread_reports = $lang->sprintf($lang->unread_reports, my_number_format($unread));
				}

				eval('$unreadreports = "'.$templates->get('global_unreadreports').'";');
			}
		}
	}
}

// Got a character set?
$charset = 'UTF-8';
if(isset($lang->settings['charset']) && $lang->settings['charset'])
{
	$charset = $lang->settings['charset'];
}

// Is this user apart of a banned group?
$bannedwarning = '';
if($mybb->usergroup['isbannedgroup'] == 1)
{
	// Fetch details on their ban
	$query = $db->simple_select('banned', '*', "uid = '{$mybb->user['uid']}'", array('limit' => 1));
	$ban = $db->fetch_array($query);

	if($ban['uid'])
	{
		// Format their ban lift date and reason appropriately
		$banlift = $lang->banned_lifted_never;
		$reason = htmlspecialchars_uni($ban['reason']);

		if($ban['lifted'] > 0)
		{
			$banlift = my_date('normal', $ban['lifted']);
		}
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
	eval('$bannedwarning = "'.$templates->get('global_bannedwarning').'";');
}

$lang->ajax_loading = str_replace("'", "\\'", $lang->ajax_loading);

// Check if this user has a new private message.
$pm_notice = '';
if(isset($mybb->user['pmnotice']) && $mybb->user['pmnotice'] == 2 && $mybb->user['pms_unread'] > 0 && $mybb->settings['enablepms'] != 0 && $mybb->usergroup['canusepms'] != 0 && $mybb->usergroup['canview'] != 0 && ($current_page != "private.php" || $mybb->get_input('action') != "read"))
{
	if(!isset($parser))
	{
		require_once MYBB_ROOT.'inc/class_parser.php';
		$parser = new postParser;
	}

	$query = $db->query("
		SELECT pm.subject, pm.pmid, fu.username AS fromusername, fu.uid AS fromuid
		FROM ".TABLE_PREFIX."privatemessages pm
		LEFT JOIN ".TABLE_PREFIX."users fu on (fu.uid=pm.fromid)
		WHERE pm.folder = '1' AND pm.uid = '{$mybb->user['uid']}' AND pm.status = '0'
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
		$pm['fromusername'] = htmlspecialchars_uni($pm['fromusername']);
		$user_text = build_profile_link($pm['fromusername'], $pm['fromuid']);
	}

	if($mybb->user['pms_unread'] == 1)
	{
		$privatemessage_text = $lang->sprintf($lang->newpm_notice_one, $user_text, $mybb->settings['bburl'], $pm['pmid'], htmlspecialchars_uni($pm['subject']));
	}
	else
	{
		$privatemessage_text = $lang->sprintf($lang->newpm_notice_multiple, $mybb->user['pms_unread'], $user_text, $mybb->settings['bburl'], $pm['pmid'], htmlspecialchars_uni($pm['subject']));
	}
	eval('$pm_notice = "'.$templates->get('global_pm_alert').'";');
}

$remote_avatar_notice = '';
if(($mybb->user['avatartype'] === 'remote' || $mybb->user['avatartype'] === 'gravatar') && !$mybb->settings['allowremoteavatars'])
{
	eval('$remote_avatar_notice = "'.$templates->get('global_remote_avatar_notice').'";');
}

if($mybb->settings['awactialert'] == 1 && $mybb->usergroup['cancp'] == 1)
{
	$awaitingusers = $cache->read('awaitingactivation');

	if(isset($awaitingusers['time']) && $awaitingusers['time'] + 86400 < TIME_NOW)
	{
		$cache->update_awaitingactivation();
		$awaitingusers = $cache->read('awaitingactivation');
	}

	if(!empty($awaitingusers['users']))
	{
		$awaitingusers = (int)$awaitingusers['users'];
	}
	else
	{
		$awaitingusers = 0;
	}

	if($awaitingusers < 1)
	{
		$awaitingusers = 0;
	}
	else
	{
		$awaitingusers = my_number_format($awaitingusers);
	}

	if($awaitingusers > 0)
	{
		if($awaitingusers == 1)
		{
			$awaiting_message = $lang->awaiting_message_single;
		}
		else
		{
			$awaiting_message = $lang->sprintf($lang->awaiting_message_plural, $awaitingusers);
		}

		if($admincplink)
		{
			$awaiting_message .= $lang->sprintf($lang->awaiting_message_link, $mybb->settings['bburl'], $admin_dir);
		}

		eval('$awaitingusers = "'.$templates->get('global_awaiting_activation').'";');
	}
	else
	{
		$awaitingusers = '';
	}
}

// Set up some of the default templates
eval('$headerinclude = "'.$templates->get('headerinclude').'";');
eval('$gobutton = "'.$templates->get('gobutton').'";');
eval('$htmldoctype = "'.$templates->get('htmldoctype', 1, 0).'";');
eval('$header = "'.$templates->get('header').'";');

$copy_year = my_date('Y', TIME_NOW);

// Are we showing version numbers in the footer?
$mybbversion = '';
if($mybb->settings['showvernum'] == 1)
{
	$mybbversion = ' '.$mybb->version;
}

// Check to see if we have any tasks to run
$task_image = '';
$task_cache = $cache->read('tasks');
if(!$task_cache['nextrun'])
{
	$task_cache['nextrun'] = TIME_NOW;
}

if($task_cache['nextrun'] <= TIME_NOW)
{
	eval("\$task_image = \"".$templates->get("task_image")."\";");
}

// Post code
$post_code_string = '';
if($mybb->user['uid'])
{
	$post_code_string = '&amp;my_post_key='.$mybb->post_code;
}

// Are we showing the quick language selection box?
$lang_select = $lang_options = '';
if($mybb->settings['showlanguageselect'] != 0)
{
	$languages = $lang->get_languages();

	if(count($languages) > 1)
	{
		foreach($languages as $key => $language)
		{
			$language = htmlspecialchars_uni($language);

			// Current language matches
			if($lang->language == $key)
			{
				$selected = " selected=\"selected\"";
			}
			else
			{
				$selected = '';
			}

			eval('$lang_options .= "'.$templates->get('footer_languageselect_option').'";');
		}

		$lang_redirect_url = get_current_location(true, 'language');
		eval('$lang_select = "'.$templates->get('footer_languageselect').'";');
	}
}

// Are we showing the quick theme selection box?
$theme_select = $theme_options = '';
if($mybb->settings['showthemeselect'] != 0)
{
	$theme_options = build_theme_select("theme", $mybb->user['style'], 0, '', false, true);

	if(!empty($theme_options))
	{
		$theme_redirect_url = get_current_location(true, 'theme');
		eval('$theme_select = "'.$templates->get('footer_themeselect').'";');
	}
}

// If we use the contact form, show 'Contact Us' link when appropriate
$contact_us = '';
if(($mybb->settings['contactlink'] == "contact.php" && $mybb->settings['contact'] == 1 && ($mybb->settings['contact_guests'] != 1 && $mybb->user['uid'] == 0 || $mybb->user['uid'] > 0)) || $mybb->settings['contactlink'] != "contact.php")
{
	if(!my_validate_url($mybb->settings['contactlink'], true) && my_substr($mybb->settings['contactlink'], 0, 7) != 'mailto:')
	{
		$mybb->settings['contactlink'] = $mybb->settings['bburl'].'/'.$mybb->settings['contactlink'];
	}

	eval('$contact_us = "'.$templates->get('footer_contactus').'";');
}

// DST Auto detection enabled?
$auto_dst_detection = '';
if($mybb->user['uid'] > 0 && $mybb->user['dstcorrection'] == 2)
{
	$timezone = (float)$mybb->user['timezone'] + $mybb->user['dst'];
	eval('$auto_dst_detection = "'.$templates->get('global_dst_detection').'";');
}

eval('$footer = "'.$templates->get('footer').'";');

// Add our main parts to the navigation
$navbits = array();
$navbits[0]['name'] = $mybb->settings['bbname_orig'];
$navbits[0]['url'] = $mybb->settings['bburl'].'/index.php';

// Set the link to the archive.
$archive_url = build_archive_link();

// Check banned ip addresses
if(is_banned_ip($session->ipaddress, true))
{
	if($mybb->user['uid'])
	{
		$db->delete_query('sessions', "ip = ".$db->escape_binary($session->packedip)." OR uid='{$mybb->user['uid']}'");
	}
	else
	{
		$db->delete_query('sessions', "ip = ".$db->escape_binary($session->packedip));
	}
	error($lang->error_banned);
}

$closed_bypass = array(
	'member.php' => array(
		'login',
		'do_login',
		'logout',
	),
	'captcha.php',
	'contact.php',
);

// If the board is closed, the user is not an administrator and they're not trying to login, show the board closed message
if($mybb->settings['boardclosed'] == 1 && $mybb->usergroup['canviewboardclosed'] != 1 && !in_array($current_page, $closed_bypass) && (!is_array($closed_bypass[$current_page]) || !in_array($mybb->get_input('action'), $closed_bypass[$current_page])))
{
	// Show error
	if(!$mybb->settings['boardclosed_reason'])
	{
		$mybb->settings['boardclosed_reason'] = $lang->boardclosed_reason;
	}

	eval('$reason = "'.$templates->get('global_boardclosed_reason').'";');
	$lang->error_boardclosed .= $reason;

	if(!$mybb->get_input('modal'))
	{
		error($lang->error_boardclosed);
	}
	else
	{
		$output = '';
		eval('$output = "'.$templates->get('global_board_offline_modal', 1, 0).'";');
		echo($output);
	}
	exit;
}

$force_bypass = array(
	'member.php' => array(
		'login',
		'do_login',
		'logout',
		'register',
		'do_register',
		'lostpw',
		'do_lostpw',
		'activate',
		'resendactivation',
		'do_resendactivation',
		'resetpassword',
	),
	'captcha.php',
	'contact.php',
);

// If the board forces user to login/register, and the user is a guest, show the force login message
if($mybb->settings['forcelogin'] == 1 && $mybb->user['uid'] == 0 && !in_array($current_page, $force_bypass) && (!is_array($force_bypass[$current_page]) || !in_array($mybb->get_input('action'), $force_bypass[$current_page])))
{
	// Show error
	error_no_permission();
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
		$condition = "username = '".$db->escape_string($mybb->get_input('referrername'))."'";
	}
	else
	{
		$condition = "uid = '".$mybb->get_input('referrer', MyBB::INPUT_INT)."'";
	}

	$query = $db->simple_select('users', 'uid', $condition, array('limit' => 1));
	$referrer = $db->fetch_array($query);

	if($referrer['uid'])
	{
		my_setcookie('mybb[referrer]', $referrer['uid']);
	}
}

$output = '';
$notallowed = false;
if($mybb->usergroup['canview'] != 1)
{
	// Check pages allowable even when not allowed to view board
	if(defined('ALLOWABLE_PAGE'))
	{
		if(is_string(ALLOWABLE_PAGE))
		{
			$allowable_actions = explode(',', ALLOWABLE_PAGE);
			if(!in_array($mybb->get_input('action'), $allowable_actions))
			{
				$notallowed = true;
			}

			unset($allowable_actions);
		}
		else if(ALLOWABLE_PAGE !== 1)
		{
			$notallowed = true;
		}
	}
	else
	{
		$notallowed = true;
	}

	if($notallowed == true)
	{
		if(!$mybb->get_input('modal'))
		{
			error_no_permission();
		}
		else
		{
			eval('$output = "'.$templates->get('global_no_permission_modal', 1, 0).'";');
			echo($output);
			exit;
		}
	}
}

// Find out if this user of ours is using a banned email address.
// If they are, redirect them to change it
if($mybb->user['uid'] && is_banned_email($mybb->user['email']) && $mybb->settings['emailkeep'] != 1)
{
	if(THIS_SCRIPT != 'usercp.php' || THIS_SCRIPT == 'usercp.php' && $mybb->get_input('action') != 'email' && $mybb->get_input('action') != 'do_email')
	{
		redirect('usercp.php?action=email');
	}
	else if($mybb->request_method != 'post')
	{
		$banned_email_error = inline_error(array($lang->banned_email_warning));
	}
}

// work out which items the user has collapsed
$colcookie = '';
if(!empty($mybb->cookies['collapsed']))
{
	$colcookie = $mybb->cookies['collapsed'];
}

$collapse = $collapsed = $collapsedimg = array();

if($colcookie)
{
	// Preserve and don't unset $collapse, will be needed globally throughout many pages
	$collapse = explode("|", $colcookie);
	foreach($collapse as $val)
	{
		$ex = $val."_e";
		$co = $val."_c";
		$collapsed[$co] = "display: show;";
		$collapsed[$ex] = "display: none;";
		$collapsedimg[$val] = "_collapsed";
		$collapsedthead[$val] = " thead_collapsed";
	}
}

// Run hooks for end of global.php
$plugins->run_hooks('global_end');

$globaltime = $maintimer->getTime();
