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

require_once './global.php';
require_once MYBB_ROOT.'inc/functions_forumlist.php';
require_once MYBB_ROOT.'inc/class_parser.php';
$parser = new postParser;
require_once MYBB_ROOT.'inc/functions_online.php';

// Load global language phrases
$lang->load('index');

$plugins->run_hooks('index_start');

$groups = [];
$doneusers = [];
$donebots = [];

// Build Who's Online data
$wol_data = build_whosonline_data(array(
	'include_forum_viewers' => true,
	'include_groups' => ($mybb->settings['showgroupslegend'] != 0),
));

// Extract data from returned array
$doneusers = $wol_data['members'];
$donebots = $wol_data['bots'];
$groups = $wol_data['groups'];
$forum_viewers = $wol_data['forum_viewers'];
$membercount = $wol_data['membercount'];
$guestcount = $wol_data['guestcount'];
$botcount = $wol_data['botcount'];
$anoncount = $wol_data['anoncount'];
$onlinecount = $wol_data['onlinecount'];
$mostonline = array();
if(isset($wol_data['mostonline']) && is_array($wol_data['mostonline']) && !empty($wol_data['mostonline']))
{
	$mostonline = $wol_data['mostonline'];
}
else
{
	$mostonline = $cache->read("mostonline");
	if(!is_array($mostonline))
	{
		$mostonline = array();
	}
}

// Build language strings if WOL is enabled
if($mybb->settings['showwol'] != 0 && $mybb->usergroup['canviewonline'] != 0)
{
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
}

// Build the birthdays for to show on the index page.
$bdays = '';
$birthdays = [];
$hiddencount = 0;
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

	if(isset($bdaycache[$bdaydate]))
	{
		$hiddencount = $bdaycache[$bdaydate]['hiddencount'] ?? 0;
		$birthdays = $bdaycache[$bdaydate]['users'];
	}

	if(!empty($birthdays))
	{

		if((int)$mybb->settings['showbirthdayspostlimit'] > 0)
		{

			$bdayusers = [];
			foreach($birthdays as $key => $bdayuser_pc)
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
						unset($birthdays[$bdayusers[$bdayuser['uid']]]);
					}
				}

			}

		}

		// We still have birthdays - display them in our list!
		if(!empty($birthdays))
		{

			foreach($birthdays as $key => $bdayuser)
			{

				if($bdayuser['displaygroup'] == 0)
				{
					$birthdays[$key]['displaygroup'] = $bdayuser['usergroup'];
				}

				// If this user's display group can't be seen in the birthday list, skip it
				if(isset($groupscache[$bdayuser['displaygroup']]) && $groupscache[$bdayuser['displaygroup']]['showinbirthdaylist'] != 1)
				{
					continue;
				}

				$bday = explode('-', $bdayuser['birthday']);
				if($year > $bday['2'] && $bday['2'] != '')
				{
					$birthdays[$key]['age'] = $year - $bday['2'];
				}

				++$bdaycount;
			}
		}
	}
}

// Build the forum statistics to show on the index page.
$forumstats = '';
if($mybb->settings['showindexstats'] != 0)
{
	// First, load the stats cache.
	$stats = $cache->read('stats');

	// mostonline is now updated by build_whosonline_data() function
}

// Load the stats cache.
if(!isset($stats) || isset($stats) && !is_array($stats))
{
	$stats = $cache->read('stats');
}

if($mybb->user['uid'] == 0)
{
	// Build a forum cache.
	$query = $db->query("
        SELECT f.*, u.avatar
        FROM ".TABLE_PREFIX."forums f
        LEFT JOIN ".TABLE_PREFIX."users u ON (f.lastposteruid = u.uid)
        WHERE f.active != 0
        ORDER BY pid, disporder
    ");

	$forumsread = array();
	if(isset($mybb->cookies['mybb']['forumread']))
	{
		$forumsread = my_unserialize($mybb->cookies['mybb']['forumread'], false);
	}
}
else
{
	// Build a forum cache.
	$query = $db->query("
        SELECT f.*, fr.dateline AS lastread, u.avatar
        FROM ".TABLE_PREFIX."forums f
        LEFT JOIN ".TABLE_PREFIX."forumsread fr ON (fr.fid = f.fid AND fr.uid = '{$mybb->user['uid']}')
        LEFT JOIN ".TABLE_PREFIX."users u ON (f.lastposteruid = u.uid)
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
$permissioncache = null;
$bgcolor = 'trow1';

// Decide if we're showing first-level subforums on the index page.
$showdepth = 2;
if($mybb->settings['subforumsindex'] != 0)
{
	$showdepth = 3;
}

$forum_list = build_forumbits();
$forums = $forum_list['forum_list'] ?? '';

$plugins->run_hooks('index_end');

output_page(\MyBB\View\template('index/index.twig', [
	'forums' => $forums,
	'groups' => $groups,
	'users' => $doneusers,
	'bots' => $donebots,
	'birthdays' => $birthdays,
	'hiddencount' => $hiddencount,
	'stats' => $stats,
	'mostonline' => $mostonline
]));
