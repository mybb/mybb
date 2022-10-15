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

if(!defined('THIS_SCRIPT'))
{
	define('THIS_SCRIPT', '');
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
elseif(!$mybb->user['uid'] && !empty($mybb->cookies['mybblang']) && $lang->language_exists($mybb->cookies['mybblang']))
{
	$mybb->settings['bblanguage'] = $mybb->cookies['mybblang'];
}
else
{
	$mybb->settings['bblanguage'] = 'english';
}

// Load language
$lang->set_language($mybb->settings['bblanguage']);
$lang->load('global');
$lang->load('messages');

// Wipe lockout cookie if enough time has passed
if(isset($mybb->cookies['lockoutexpiry']) && $mybb->cookies['lockoutexpiry'] < TIME_NOW)
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
$load_from_forum = $load_from_user = false;
$style = '';

// The user used our new quick theme changer
if(isset($mybb->input['theme']) && verify_post_check($mybb->get_input('my_post_key'), true))
{
	// Set up user handler.
	require_once MYBB_ROOT.'inc/datahandlers/user.php';
	$userhandler = new UserDataHandler('update');

	$user = array(
		'uid' => $mybb->user['uid'],
		'style' => $mybb->input['theme'],
		'usergroup' => $mybb->user['usergroup'],
		'additionalgroups' => $mybb->user['additionalgroups']
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
elseif(!$mybb->user['uid'] && !empty($mybb->cookies['mybbtheme']))
{
	$mybb->user['style'] = $mybb->cookies['mybbtheme'];
}

// This user has a custom theme set in their profile
if(!empty($mybb->user['style']))
{
	$mybb->user['style'] = $mybb->user['style'];

	$style = $mybb->user['style'];
	$load_from_user = true;
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

		if($db->num_rows($query) > 0 && $fid = $db->fetch_field($query, 'fid'))
		{
			if($forum_cache[$fid]['overridestyle'] || empty($style))
			{
				$style = $forum_cache[$fid]['style'];
				$load_from_forum = true;
			}
		}
	}
	// We have a thread id and a forum id, we can easily fetch the theme for this forum
	elseif(isset($mybb->input['tid']))
	{
		$query = $db->simple_select('threads', 'fid', "tid = '{$mybb->input['tid']}'", array('limit' => 1));

		if($db->num_rows($query) > 0 && $fid = $db->fetch_field($query, 'fid'))
		{
			if($forum_cache[$fid]['overridestyle'] || empty($style))
			{
				$style = $forum_cache[$fid]['style'];
				$load_from_forum = true;
			}
		}
	}
	// If we're accessing poll results, fetch the forum theme for it and if we're overriding it
	elseif(isset($mybb->input['pid']) && THIS_SCRIPT == "polls.php")
	{
		$query = $db->query("SELECT t.fid FROM ".TABLE_PREFIX."polls p INNER JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid) WHERE p.pid = '{$mybb->input['pid']}' LIMIT 1");

		if($db->num_rows($query) > 0 && $fid = $db->fetch_field($query, 'fid'))
		{
			if($forum_cache[$fid]['overridestyle'] || empty($style))
			{
				$style = $forum_cache[$fid]['style'];
				$load_from_forum = true;
			}
		}
	}
	// We have a forum id - simply load the theme from it
	elseif(isset($mybb->input['fid']) && isset($forum_cache[$mybb->input['fid']]))
	{
		if($forum_cache[$mybb->input['fid']]['overridestyle'] || empty($style))
		{
			$style = $forum_cache[$mybb->input['fid']]['style'];
			$load_from_forum = true;
		}
	}
}
unset($valid);

require_once MYBB_ROOT.'inc/functions_themes.php';
$themelet_hierarchy = get_themelet_hierarchy();
$mode = $mybb->settings['themelet_dev_mode'] ? 'devdist' : 'current';

// Fetch the theme to load from the cache
if($style && !empty($themelet_hierarchy[$mode]['themes'][$style]))
{
	$theme = $themelet_hierarchy[$mode]['themes'][$style]['properties'];

	if(!empty($theme) && !$load_from_forum && !is_member($theme['allowedgroups']) && $theme['allowedgroups'] != 'all')
	{
		if($load_from_user)
		{
			$db->update_query('users', array('style' => ''), "style='{$mybb->user['style']}' AND uid='{$mybb->user['uid']}'");
		}

		if(isset($mybb->cookies['mybbtheme']))
		{
			my_unsetcookie('mybbtheme');
		}
	}

	$load_from_forum = $load_from_user = false;
}

if(empty($theme)) {
	if(!$cache->read('default_theme'))
	{
		$cache->update_default_theme();
	}

	$theme = $cache->read('default_theme');
}

// No theme was found - we attempt to load the master or any other theme
if(empty($theme))
{
	// Missing theme was from a forum, run a query to set any forums using the theme to the default
	if($load_from_forum)
	{
		$db->update_query('forums', array('style' => 0), "style = '{$style}'");
	}
	// Missing theme was from a user, run a query to set any users using the theme to the default
	elseif($load_from_user)
	{
		$db->update_query('users', array('style' => 0), "style = '{$mybb->user['style']}'");
	}

	// Load the first available theme
	$theme = reset($themelet_hierarchy[$mode]['themes'])['properties'];
}

// Fetch all necessary stylesheets
$stylesheets = '';
$resources = read_json_file(MYBB_ROOT."inc/themes/$style/$mode/resources.json", $err_msg, /*$show_errs =*/false);
if(!empty($resources['stylesheets']))
{
	$theme['stylesheets'] = $resources['stylesheets'];
	$stylesheet_scripts = array("global", basename($_SERVER['PHP_SELF']));
	if(!empty($theme['color']))
	{
		$stylesheet_scripts[] = $theme['color'];
	}
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
					$stylesheet_url = $mybb->settings['bburl'].'/'.$page_stylesheet;
				}
				else
				{
					$stylesheet_url = $mybb->get_asset_url($page_stylesheet);
					if (file_exists(MYBB_ROOT.$page_stylesheet))
					{
						$stylesheet_url .= "?t=".filemtime(MYBB_ROOT.$page_stylesheet);
					}
				}

				if($mybb->settings['minifycss'])
				{
					$stylesheet_url = str_replace('.css', '.min.css', $stylesheet_url);
				}

				if(strpos($page_stylesheet, 'css.php') !== false)
				{
					// We need some modification to get it working with the displayorder
					$query_string = parse_url($stylesheet_url, PHP_URL_QUERY);
					$id = (int)my_substr($query_string, 11);
					$query = $db->simple_select("themestylesheets", "name", "sid={$id}");
					$real_name = $db->fetch_field($query, "name");
					$theme_stylesheets[$real_name] = $id;
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

$css_php_script_stylesheets = array();

if(!empty($theme_stylesheets) && is_array($theme['disporder']))
{
	foreach($theme['disporder'] as $style_name => $order)
	{
		if(!empty($theme_stylesheets[$style_name]))
		{
			if(is_int($theme_stylesheets[$style_name]))
			{
				$css_php_script_stylesheets[] = $theme_stylesheets[$style_name];
			}
			else
			{
				$stylesheets .= $theme_stylesheets[$style_name];
			}
		}
	}
}

if(!empty($css_php_script_stylesheets))
{
	$sheet = $mybb->settings['bburl'] . '/css.php?' . http_build_query(array(
		'stylesheet' => $css_php_script_stylesheets
		));

	$stylesheets .= "<link type=\"text/css\" rel=\"stylesheet\" href=\"{$sheet}\" />\n";
}

// Are we linking to a remote theme server?
if(!empty($theme['imgdir']) && my_validate_url($theme['imgdir']))
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
	$img_directory = !empty($theme['imgdir']) ? $theme['imgdir'] : '';

	if($mybb->settings['usecdn'] && !empty($mybb->settings['cdnpath']))
	{
		$img_directory = rtrim($mybb->settings['cdnpath'], '/').'/'.ltrim($theme['imgdir'], '/');
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
if(!empty($theme['logo']) && !preg_match("#^(\.\.?(/|$)|([a-z0-9]+)://)#i", $theme['logo']) && substr($theme['logo'], 0, 1) != '/')
{
	$theme['logo'] = $mybb->get_asset_url($theme['logo']);
}

// Set the current date and time now
$datenow = my_date($mybb->settings['dateformat'], TIME_NOW, '', false);
$timenow = my_date($mybb->settings['timeformat'], TIME_NOW);
$lang->welcome_current_time = $lang->sprintf($lang->welcome_current_time, $datenow.$lang->comma.$timenow);

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

$headerMessages = [];

$plugins->run_hooks('global_intermediate');

// If the board is closed and we have a usergroup allowed to view the board when closed, then show board closed warning
if($mybb->settings['boardclosed'] == 1 && $mybb->usergroup['canviewboardclosed'] == 1)
{
	$headerMessages[] = [
		'message' => $lang->bbclosed_warning
	];
}

// Load appropriate welcome block for the current logged in user
if($mybb->user['uid'] != 0)
{
	// Format the welcome back message
	$lang->welcome_back = $lang->sprintf($lang->welcome_back, build_profile_link(htmlspecialchars_uni($mybb->user['username']), $mybb->user['uid']), $lastvisit);
}

// See if there are any pending join requests for group leaders
$pending_joinrequests = '';
$groupleaders = $cache->read('groupleaders');

if($mybb->user['uid'] != 0 && is_array($groupleaders) && array_key_exists($mybb->user['uid'], $groupleaders))
{
	$groupleader = $groupleaders[$mybb->user['uid']];

	$gids = "'0'";
	foreach($groupleader as $user)
	{
		if($user['canmanagerequests'] != 1)
		{
			continue;
		}

		$user['gid'] = (int)$user['gid'];

		if(!empty($groupscache[$user['gid']]['type']) && $groupscache[$user['gid']]['type'] == 4)
		{
			$gids .= ",'{$user['gid']}'";
		}
	}

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

		$headerMessages[] = [
			'message' => $lang->pending_joinrequests
		];
	}
}

$modnotice = '';
$moderation_queue = array();
$can_access_moderationqueue = false;

// This user is a moderator, super moderator or administrator
if($mybb->usergroup['cancp'] == 1 || ($mybb->user['ismoderator'] && $mybb->usergroup['canmodcp'] == 1 && $mybb->usergroup['canmanagereportedcontent'] == 1))
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

				$headerMessages[] = [
					'message' => $lang->unread_reports
				];
			}
		}
	}
}

// Get awaiting moderation queue stats, except if the page is editpost.php,
// because that page can make changes - (un)approving attachments, or deleting
// unapproved attachments - that would invalidate anything generated here.
// Just leave this queue notification blank for editpost.php.
if(!(defined('THIS_SCRIPT') && THIS_SCRIPT == 'editpost.php') && ($can_access_moderationqueue || ($mybb->user['ismoderator'] && $mybb->usergroup['canmodcp'] == 1 && $mybb->usergroup['canmanagemodqueue'] == 1)))
{
	$unapproved_posts = $unapproved_threads = 0;
	$query = $db->simple_select("posts", "replyto", "visible = 0");
	while($unapproved = $db->fetch_array($query))
	{
		if($unapproved["replyto"] == 0){
			$unapproved_threads++;
		} else {
			$unapproved_posts++;
		}
	}

	$query = $db->simple_select("attachments", "COUNT(aid) AS unapprovedattachments", "visible=0");
	$unapproved_attachments = $db->fetch_field($query, "unapprovedattachments");

	$modqueue_types = array('threads', 'posts', 'attachments');

	foreach($modqueue_types as $modqueue_type)
	{
		if(!empty(${'unapproved_'.$modqueue_type}))
		{
			if(${'unapproved_'.$modqueue_type} == 1)
			{
				$modqueue_message = $lang->{'unapproved_'.substr($modqueue_type, 0, -1)};
			}
			else
			{
				$modqueue_message = $lang->sprintf($lang->{'unapproved_'.$modqueue_type}, my_number_format(${'unapproved_'.$modqueue_type}));
			}

			$headerMessage[] = [
				'message' => \MyBB\template('misc/modqueue_link.twig', [
					'modqueue_type' => $modqueue_type,
					'modqueue_message' => $modqueue_message,
				]),
			];
		}
	}
}

if(!empty($moderation_queue))
{
	$moderation_queue_last = array_pop($moderation_queue);
	if(empty($moderation_queue))
	{
		$moderation_queue = $moderation_queue_last;
	}
	else
	{
		$moderation_queue = implode($lang->comma, $moderation_queue).' '.$lang->and.' '.$moderation_queue_last;
	}
	$moderation_queue = $lang->sprintf($lang->mod_notice, $moderation_queue);
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
	// Format their ban lift date and reason appropriately
	if(!empty($mybb->user['banned']))
	{
		if(!empty($mybb->user['banlifted']))
		{
			$ban['lift'] = my_date('normal', $mybb->user['banlifted']);
		}
		else
		{
			$ban['lift'] = $lang->banned_lifted_never;
		}
	}
	else
	{
		$ban['lift'] = $lang->unknown;
	}

	if(!empty($mybb->user['banreason']))
	{
		$ban['reason'] = htmlspecialchars_uni($mybb->user['banreason']);
	}
	else
	{
		$ban['reason'] = $lang->unknown;
	}

	$headerMessages['banneduser'] = [
		'extra' => $ban
	];
}

$lang->ajax_loading = str_replace("'", "\\'", $lang->ajax_loading);

// Check if this user has a new private message.
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
		$headerMessages['pmnotice']['message'] = $lang->sprintf($lang->newpm_notice_one, $user_text, $mybb->settings['bburl'], $pm['pmid'], htmlspecialchars_uni($pm['subject']));
	}
	else
	{
		$headerMessages['pmnotice']['message'] = $lang->sprintf($lang->newpm_notice_multiple, $mybb->user['pms_unread'], $user_text, $mybb->settings['bburl'], $pm['pmid'], htmlspecialchars_uni($pm['subject']));
	}
	$headerMessages['pmnotice']['id'] = 'pm_notice';
	$headerMessages['pmnotice']['class'] = 'pm_alert';
}

if(isset($mybb->user['avatartype']) && ($mybb->user['avatartype'] === 'remote' || $mybb->user['avatartype'] === 'gravatar') && !$mybb->settings['allowremoteavatars'])
{
	$headerMessages[] = [
		'message' => $lang->remote_avatar_disabled_default_avatar
	];
}

$awaitingusers = '';
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
		$aamessage = [];
		if($awaitingusers == 1)
		{
			$aamessage['message'] = $lang->awaiting_message_single;
		}
		else
		{
			$aamessage['message'] = $lang->sprintf($lang->awaiting_message_plural, $awaitingusers);
		}

		if($admincplink)
		{
			$aamessage['message'] .= $lang->sprintf($lang->awaiting_message_link, $mybb->settings['bburl'], $admin_dir);
		}
		$headerMessages[] = $aamessage;
	}
}

$jsTemplates = array();
foreach (array('modal', 'modal_button') as $template) {
	$jsTemplates[$template] = \MyBB\template("modals/{$template}.twig");
	$jsTemplates[$template] = str_replace(array("\n","\r"), array("\\\n", ""), addslashes($jsTemplates[$template]));
}

// Check to see if we have any tasks to run
$task_image = '';
$task_cache = $cache->read('tasks');
if(!$task_cache['nextrun'])
{
	$task_cache['nextrun'] = TIME_NOW;
}

// Use a fictional setting to inject the footer code into Twig without creating an ad-hoc extension
$mybb->settings['footer'] = [];

// Are we showing the quick language selection box?
if($mybb->settings['showlanguageselect'] != 0)
{
	$mybb->settings['footer']['langselect']['options'] = $lang->get_languages();

	if(count($mybb->settings['footer']['langselect']) > 1)
	{
		$mybb->settings['footer']['langselect']['current_url'] = get_current_location(false, 'language');
	}
}

// Are we showing the quick theme selection box?
if($mybb->settings['showthemeselect'] != 0)
{
	$mybb->settings['footer']['themeselect']['options'] = build_fs_theme_select("theme", $mybb->user['style'], /*$effective_uid = */$mybb->user['uid'], /*$usergroup_override = */false, /*$footer = */true);
	if(!empty($mybb->settings['footer']['themeselect']['options']))
	{
		$mybb->settings['footer']['themeselect']['current_url'] = get_current_location(false, 'theme');
	}
}

if(($mybb->settings['contactlink'] == "contact.php" && $mybb->settings['contact'] == 1 && ($mybb->settings['contact_guests'] != 1 && $mybb->user['uid'] == 0 || $mybb->user['uid'] > 0)) || $mybb->settings['contactlink'] != "contact.php")
{
	$mybb->settings['contactlink'] = $mybb->settings['bburl'].'/'.$mybb->settings['contactlink'];
}

// DST Auto detection enabled?
if($mybb->user['uid'] > 0 && $mybb->user['dstcorrection'] == 2)
{
	$timezone = (float)$mybb->user['timezone'] + $mybb->user['dst'];
	$mybb->settings['dst_detection'] = \MyBB\template('messages/dst_detection.twig', [
		'timezone' => $timezone
	]);
}

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

// If the board is closed, the user is not an administrator and they're not trying to login,
// show the board closed message
if($mybb->settings['boardclosed'] == 1 && $mybb->usergroup['canviewboardclosed'] != 1 &&
	!in_array($current_page, $closed_bypass) &&
	(!is_array($closed_bypass[$current_page]) ||
		!in_array($mybb->get_input('action'), $closed_bypass[$current_page])))
{
	// Show error
	if(!$mybb->settings['boardclosed_reason'])
	{
		$mybb->settings['boardclosed_reason'] = $lang->boardclosed_reason;
	}

	$lang->error_boardclosed .= \MyBB\template('messages/boardclosed_reason.twig');

	if(!$mybb->get_input('modal'))
	{
		error($lang->error_boardclosed);
	}
	else
	{
		echo(\MyBB\template('modals/boardclosed.twig'));
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

	if(!empty($referrer) && $referrer['uid'])
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
		else
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
			echo(\MyBB\template('modals/no_permission.twig'));
			exit;
		}
	}
}

// Find out if this user of ours is using a banned email address.
// If they are, redirect them to change it
if($mybb->user['uid'] && is_banned_email($mybb->user['email']) && $mybb->settings['emailkeep'] != 1)
{
	if(
		!(THIS_SCRIPT == 'usercp.php' && in_array($mybb->get_input('action'), array('email', 'do_email'))) &&
		!(THIS_SCRIPT == 'member.php' && $mybb->get_input('action') == 'activate')
	)
	{
		redirect('usercp.php?action=email');
	}
	else
	{
		$banned_email_error = inline_error(array($lang->banned_email_warning));
	}
}

// work out which items the user has collapsed
$colcookie = '';
if (!empty($mybb->cookies['collapsed'])) {
	$colcookie = $mybb->cookies['collapsed'];
}

$collapsed = [];
if ($colcookie) {
	$col = explode("|", $colcookie);
	if (is_array($col)) {
		foreach ($col as $key => $val) {
			$collapsed[$val . "_e"] = true;
		}
	}
}

// Run hooks for end of global.php
$plugins->run_hooks('global_end');

$globaltime = $maintimer->getTime();
