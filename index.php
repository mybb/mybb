<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

define('IN_MYBB', 1);
define('THIS_SCRIPT', 'index.php');

$templatelist = "index,index_whosonline,index_whosonline_memberbit,forumbit_depth1_cat,forumbit_depth2_cat,forumbit_depth2_forum,forumbit_depth1_forum_lastpost,forumbit_depth2_forum_lastpost,forumbit_moderators";
$templatelist .= ",index_birthdays_birthday,index_birthdays,index_logoutlink,index_statspage,index_stats,forumbit_depth3,forumbit_depth3_statusicon,index_boardstats,forumbit_depth2_forum_lastpost_never,forumbit_depth2_forum_viewers";
$templatelist .= ",forumbit_moderators_group,forumbit_moderators_user,forumbit_depth2_forum_lastpost_hidden,forumbit_subforums,forumbit_depth2_forum_unapproved_posts,forumbit_depth2_forum_unapproved_threads";

require_once './global.php';
require_once MYBB_ROOT.'inc/functions_forumlist.php';
require_once MYBB_ROOT.'inc/class_parser.php';
$parser = new postParser;

$plugins->run_hooks('index_start');

// Load global language phrases
$lang->load('index');

$logoutlink = '';
if($mybb->user['uid'] != 0)
{
	eval('$logoutlink = "'.$templates->get('index_logoutlink').'";');
}

$statspage = '';
if($mybb->settings['statsenabled'] != 0)
{
	if(!empty($logoutlink))
	{
		$stats_page_separator = $lang->board_stats_link_separator;
	}
	eval('$statspage = "'.$templates->get('index_statspage').'";');
}

$whosonline = '';
if($mybb->settings['showwol'] != 0 && $mybb->usergroup['canviewonline'] != 0)
{
	// Get the online users.
	if($mybb->settings['wolorder'] == 'username')
	{
		$order_by = 'u.username ASC';
		$order_by2 = 's.time DESC';
	}
	else
	{
		$order_by = 's.time DESC';
		$order_by2 = 'u.username ASC';
	}

	$timesearch = TIME_NOW - (int)$mybb->settings['wolcutoff'];
	$query = $db->query("
		SELECT s.sid, s.ip, s.uid, s.time, s.location, s.location1, u.username, u.invisible, u.usergroup, u.displaygroup
		FROM ".TABLE_PREFIX."sessions s
		LEFT JOIN ".TABLE_PREFIX."users u ON (s.uid=u.uid)
		WHERE s.time > '".$timesearch."'
		ORDER BY {$order_by}, {$order_by2}
	");

	$forum_viewers = $doneusers = $onlinemembers = $onlinebots = array();
	$membercount = $guestcount = $anoncount = $botcount = 0;

	// Fetch spiders
	$spiders = $cache->read('spiders');

	// Loop through all users.
	while($user = $db->fetch_array($query))
	{
		// Create a key to test if this user is a search bot.
		$botkey = my_strtolower(str_replace('bot=', '', $user['sid']));

		// Decide what type of user we are dealing with.
		if($user['uid'] > 0)
		{
			// The user is registered.
			if(empty($doneusers[$user['uid']]) || $doneusers[$user['uid']] < $user['time'])
			{
				// If the user is logged in anonymously, update the count for that.
				if($user['invisible'] == 1)
				{
					++$anoncount;
				}
				++$membercount;
				if($user['invisible'] != 1 || $mybb->usergroup['canviewwolinvis'] == 1 || $user['uid'] == $mybb->user['uid'])
				{
					// If this usergroup can see anonymously logged-in users, mark them.
					if($user['invisible'] == 1)
					{
						$invisiblemark = '*';
					}
					else
					{
						$invisiblemark = '';
					}

					// Properly format the username and assign the template.
					$user['username'] = format_name(htmlspecialchars_uni($user['username']), $user['usergroup'], $user['displaygroup']);
					$user['profilelink'] = build_profile_link($user['username'], $user['uid']);
					eval('$onlinemembers[] = "'.$templates->get('index_whosonline_memberbit', 1, 0).'";');
				}
				// This user has been handled.
				$doneusers[$user['uid']] = $user['time'];
			}
		}
		elseif(my_strpos($user['sid'], 'bot=') !== false && $spiders[$botkey])
		{
			if($mybb->settings['wolorder'] == 'username')
			{
				$key = $spiders[$botkey]['name'];
			}
			else
			{
				$key = $user['time'];
			}

			// The user is a search bot.
			$onlinebots[$key] = format_name($spiders[$botkey]['name'], $spiders[$botkey]['usergroup']);
			++$botcount;
		}
		else
		{
			// The user is a guest.
			++$guestcount;
		}

		if($user['location1'])
		{
			++$forum_viewers[$user['location1']];
		}
	}

	if($mybb->settings['wolorder'] == 'activity')
	{
		// activity ordering is DESC, username is ASC
		krsort($onlinebots);
	}
	else
	{
		ksort($onlinebots);
	}

	$onlinemembers = array_merge($onlinebots, $onlinemembers);
	if(!empty($onlinemembers))
	{
		$comma = $lang->comma." ";
		$onlinemembers = implode($comma, $onlinemembers);
	}
	else
	{
		$onlinemembers = "";
	}

	// Build the who's online bit on the index page.
	$onlinecount = $membercount + $guestcount + $botcount;

	if($onlinecount != 1)
	{
		$onlinebit = $lang->online_online_plural;
	}
	else
	{
		$onlinebit = $lang->online_online_singular;
	}
	if($membercount != 1)
	{
		$memberbit = $lang->online_member_plural;
	}
	else
	{
		$memberbit = $lang->online_member_singular;
	}
	if($anoncount != 1)
	{
		$anonbit = $lang->online_anon_plural;
	}
	else
	{
		$anonbit = $lang->online_anon_singular;
	}
	if($guestcount != 1)
	{
		$guestbit = $lang->online_guest_plural;
	}
	else
	{
		$guestbit = $lang->online_guest_singular;
	}
	$lang->online_note = $lang->sprintf($lang->online_note, my_number_format($onlinecount), $onlinebit, $mybb->settings['wolcutoffmins'], my_number_format($membercount), $memberbit, my_number_format($anoncount), $anonbit, my_number_format($guestcount), $guestbit);
	eval('$whosonline = "'.$templates->get('index_whosonline').'";');
}

// Build the birthdays for to show on the index page.
$bdays = $birthdays = '';
if($mybb->settings['showbirthdays'] != 0)
{
	// First, see what day this is.
	$bdaycount = $bdayhidden = 0;
	$bdaydate = my_date('j-n', TIME_NOW, '', 0);
	$year = my_date('Y', TIME_NOW, '', 0);

	$bdaycache = $cache->read('birthdays');

	if(!is_array($bdaycache))
	{
		$cache->update_birthdays();
		$bdaycache = $cache->read('birthdays');
	}

	$hiddencount = $today_bdays = 0;
	if(isset($bdaycache[$bdaydate]))
	{
		$hiddencount = $bdaycache[$bdaydate]['hiddencount'];
		$today_bdays = $bdaycache[$bdaydate]['users'];
	}

	$comma = '';
	if(!empty($today_bdays))
	{
		if((int)$mybb->settings['showbirthdayspostlimit'] > 0)
		{
			$bdayusers = array();
			foreach($today_bdays as $key => $bdayuser_pc)
			{
				$bdayusers[$bdayuser_pc['uid']] = $key;
			}

			if(!empty($bdayusers))
			{
				// Find out if our users have enough posts to be seen on our birthday list
				$bday_sql = implode(',', array_keys($bdayusers));
				$query = $db->simple_select('users', 'uid, postnum', "uid IN ({$bday_sql})");

				while($bdayuser = $db->fetch_array($query))
				{
					if($bdayuser['postnum'] < $mybb->settings['showbirthdayspostlimit'])
					{
						unset($today_bdays[$bdayusers[$bdayuser['uid']]]);
					}
				}
			}
		}

		// We still have birthdays - display them in our list!
		if(!empty($today_bdays))
		{
			foreach($today_bdays as $bdayuser)
			{
				if($bdayuser['displaygroup'] == 0)
				{
					$bdayuser['displaygroup'] = $bdayuser['usergroup'];
				}

				// If this user's display group can't be seen in the birthday list, skip it
				if($groupscache[$bdayuser['displaygroup']] && $groupscache[$bdayuser['displaygroup']]['showinbirthdaylist'] != 1)
				{
					continue;
				}

				$age = '';
				$bday = explode('-', $bdayuser['birthday']);
				if($year > $bday['2'] && $bday['2'] != '')
				{
					$age = ' ('.($year - $bday['2']).')';
				}

				$bdayuser['username'] = format_name(htmlspecialchars_uni($bdayuser['username']), $bdayuser['usergroup'], $bdayuser['displaygroup']);
				$bdayuser['profilelink'] = build_profile_link($bdayuser['username'], $bdayuser['uid']);
				eval('$bdays .= "'.$templates->get('index_birthdays_birthday', 1, 0).'";');
				++$bdaycount;
				$comma = $lang->comma;
			}
		}
	}

	if($hiddencount > 0)
	{
		if($bdaycount > 0)
		{
			$bdays .= ' - ';
		}

		$bdays .= "{$hiddencount} {$lang->birthdayhidden}";
	}

	// If there are one or more birthdays, show them.
	if($bdaycount > 0 || $hiddencount > 0)
	{
		eval('$birthdays = "'.$templates->get('index_birthdays').'";');
	}
}

// Build the forum statistics to show on the index page.
$forumstats = '';
if($mybb->settings['showindexstats'] != 0)
{
	// First, load the stats cache.
	$stats = $cache->read('stats');

	// Check who's the newest member.
	if(!$stats['lastusername'])
	{
		$newestmember = $lang->nobody;;
	}
	else
	{
		$newestmember = build_profile_link($stats['lastusername'], $stats['lastuid']);
	}

	// Format the stats language.
	$lang->stats_posts_threads = $lang->sprintf($lang->stats_posts_threads, my_number_format($stats['numposts']), my_number_format($stats['numthreads']));
	$lang->stats_numusers = $lang->sprintf($lang->stats_numusers, my_number_format($stats['numusers']));
	$lang->stats_newestuser = $lang->sprintf($lang->stats_newestuser, $newestmember);

	// Find out what the highest users online count is.
	$mostonline = $cache->read('mostonline');
	if($onlinecount > $mostonline['numusers'])
	{
		$time = TIME_NOW;
		$mostonline['numusers'] = $onlinecount;
		$mostonline['time'] = $time;
		$cache->update('mostonline', $mostonline);
	}
	$recordcount = $mostonline['numusers'];
	$recorddate = my_date($mybb->settings['dateformat'], $mostonline['time']);
	$recordtime = my_date($mybb->settings['timeformat'], $mostonline['time']);

	// Then format that language string.
	$lang->stats_mostonline = $lang->sprintf($lang->stats_mostonline, my_number_format($recordcount), $recorddate, $recordtime);

	eval('$forumstats = "'.$templates->get('index_stats').'";');
}

// Show the board statistics table only if one or more index statistics are enabled.
$boardstats = '';
if(($mybb->settings['showwol'] != 0 && $mybb->usergroup['canviewonline'] != 0) || $mybb->settings['showindexstats'] != 0 || ($mybb->settings['showbirthdays'] != 0 && $bdaycount > 0))
{
	if(!isset($stats) || isset($stats) && !is_array($stats))
	{
		// Load the stats cache.
		$stats = $cache->read('stats');
	}
	
	$expaltext = (in_array("boardstats", $collapse)) ? "[+]" : "[-]";
	eval('$boardstats = "'.$templates->get('index_boardstats').'";');
}

if($mybb->user['uid'] == 0)
{
	// Build a forum cache.
	$query = $db->simple_select('forums', '*', 'active!=0', array('order_by' => 'pid, disporder'));

	$forumsread = array();
	if(isset($mybb->cookies['mybb']['forumread']))
	{
		$forumsread = my_unserialize($mybb->cookies['mybb']['forumread']);
	}
}
else
{
	// Build a forum cache.
	$query = $db->query("
		SELECT f.*, fr.dateline AS lastread
		FROM ".TABLE_PREFIX."forums f
		LEFT JOIN ".TABLE_PREFIX."forumsread fr ON (fr.fid = f.fid AND fr.uid = '{$mybb->user['uid']}')
		WHERE f.active != 0
		ORDER BY pid, disporder
	");
}

while($forum = $db->fetch_array($query))
{
	if($mybb->user['uid'] == 0)
	{
		if(!empty($forumsread[$forum['fid']]))
		{
			$forum['lastread'] = $forumsread[$forum['fid']];
		}
	}
	$fcache[$forum['pid']][$forum['disporder']][$forum['fid']] = $forum;
}
$forumpermissions = forum_permissions();

// Get the forum moderators if the setting is enabled.
$moderatorcache = array();
if($mybb->settings['modlist'] != 0 && $mybb->settings['modlist'] != 'off')
{
	$moderatorcache = $cache->read('moderators');
}

$excols = 'index';
$permissioncache['-1'] = '1';
$bgcolor = 'trow1';

// Decide if we're showing first-level subforums on the index page.
$showdepth = 2;
if($mybb->settings['subforumsindex'] != 0)
{
	$showdepth = 3;
}

$forum_list = build_forumbits();
$forums = $forum_list['forum_list'];

$plugins->run_hooks('index_end');

eval('$index = "'.$templates->get('index').'";');
output_page($index);