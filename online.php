<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'online.php');

require_once "./global.php";
require_once MYBB_ROOT."inc/functions_post.php";
require_once MYBB_ROOT."inc/functions_online.php";
require_once MYBB_ROOT."inc/class_parser.php";
$parser = new postParser;

// Load global language phrases
$lang->load("online");

if($mybb->usergroup['canviewonline'] == 0)
{
	error_no_permission();
}

// Make navigation
add_breadcrumb($lang->nav_online, "online.php");

if($mybb->get_input('action') == "today")
{
	add_breadcrumb($lang->nav_onlinetoday);

	$plugins->run_hooks("online_today_start");

	$threshold = TIME_NOW-(60*60*24);
	$query = $db->simple_select("users", "COUNT(uid) AS users", "lastactive > '{$threshold}'");
	$todaycount = $db->fetch_field($query, "users");

	$query = $db->simple_select("users", "COUNT(uid) AS users", "lastactive > '{$threshold}' AND invisible = '1'");
	$invis_count = $db->fetch_field($query, "users");

	if(!$mybb->settings['threadsperpage'] || (int)$mybb->settings['threadsperpage'] < 1)
	{
		$mybb->settings['threadsperpage'] = 20;
	}
	
	// Add pagination
	$perpage = $mybb->settings['threadsperpage'];

	if($mybb->get_input('page', MyBB::INPUT_INT) > 0)
	{
		$page = $mybb->get_input('page', MyBB::INPUT_INT);
		$start = ($page-1) * $perpage;
		$pages = ceil($todaycount / $perpage);
		if($page > $pages)
		{
			$start = 0;
			$page = 1;
		}
	}
	else
	{
		$start = 0;
		$page = 1;
	}

	$query = $db->simple_select("users", "*", "lastactive > '{$threshold}'", array("order_by" => "lastactive", "order_dir" => "desc", "limit" => $perpage, "limit_start" => $start));

	$online_today_users = [];
	while($online = $db->fetch_array($query))
	{
		if($online['invisible'] != 1 || $mybb->usergroup['canviewwolinvis'] == 1 || $online['uid'] == $mybb->user['uid'])
		{
            $username = format_name(htmlspecialchars_uni($online['username']), $online['usergroup'], $online['displaygroup']);
            $online_today_users[] = [
                'profilelink' => build_profile_link($username, $online['uid']),
                'onlinetime' => my_date($mybb->settings['timeformat'], $online['lastactive']),
                'invisible' => $online['invisible'],
            ];
		}
	}

	$todaycount = my_number_format($todaycount);
	$invis_count = my_number_format($invis_count);

	if($todaycount == 1)
	{
		$onlinetoday = $lang->member_online_today;
	}
	else
	{
		$onlinetoday = $lang->sprintf($lang->members_were_online_today, $todaycount);
	}

	if($invis_count)
	{
		$string = $lang->members_online_hidden;

		if($invis_count == 1)
		{
			$string = $lang->member_online_hidden;
		}

		$onlinetoday .= $lang->sprintf($string, $invis_count);
	}

	$multipage = multipage($todaycount, $perpage, $page, "online.php?action=today");

	$plugins->run_hooks("online_today_end");

    output_page(\MyBB\template('online/today.twig', [
        'online_today' => $onlinetoday,
        'online_today_users' => $online_today_users,
        'multipage' => $multipage,
    ]));
}
else
{
	$plugins->run_hooks("online_start");

	// Custom sorting options
	if($mybb->get_input('sortby') == "username")
	{
		$sql = "u.username ASC, s.time DESC";
		$refresh_string = "?sortby=username";
	}
	elseif($mybb->get_input('sortby') == "location")
	{
		$sql = "s.location, s.time DESC";
		$refresh_string = "?sortby=location";
	}
	// Otherwise sort by last refresh
	else
	{
		switch($db->type)
		{
			case "sqlite":
			case "pgsql":
				$sql = "s.time DESC";
				break;
			default:
				$sql = "IF( s.uid >0, 1, 0 ) DESC, s.time DESC";
				break;
		}
		$refresh_string = '';
	}

	$timesearch = TIME_NOW - $mybb->settings['wolcutoffmins']*60;

	// Exactly how many users are currently online?
	switch($db->type)
	{
		case "sqlite":
			$sessions = array();
			$query = $db->simple_select("sessions", "sid", "time > {$timesearch}");
			while($sid = $db->fetch_field($query, "sid"))
			{
				$sessions[$sid] = 1;
			}
			$online_count = count($sessions);
			unset($sessions);
			break;
		case "pgsql":
		default:
			$query = $db->simple_select("sessions", "COUNT(sid) as online", "time > {$timesearch}");
			$online_count = $db->fetch_field($query, "online");
			break;
	}
	
	if(!$mybb->settings['threadsperpage'] || (int)$mybb->settings['threadsperpage'] < 1)
	{
		$mybb->settings['threadsperpage'] = 20;
	}

	// How many pages are there?
	$perpage = $mybb->settings['threadsperpage'];

	if($mybb->get_input('page', MyBB::INPUT_INT) > 0)
	{
		$page = $mybb->get_input('page', MyBB::INPUT_INT);
		$start = ($page-1) * $perpage;
		$pages = ceil($online_count / $perpage);
		if($page > $pages)
		{
			$start = 0;
			$page = 1;
		}
	}
	else
	{
		$start = 0;
		$page = 1;
	}

	// Assemble page URL
	$multipage = multipage($online_count, $perpage, $page, "online.php".$refresh_string);

	// Query for active sessions
	$query = $db->query("
		SELECT DISTINCT s.sid, s.ip, s.uid, s.time, s.location, u.username, s.nopermission, u.invisible, u.usergroup, u.displaygroup
		FROM ".TABLE_PREFIX."sessions s
		LEFT JOIN ".TABLE_PREFIX."users u ON (s.uid=u.uid)
		WHERE s.time>'$timesearch'
		ORDER BY $sql
		LIMIT {$start}, {$perpage}
	");

	// Fetch spiders
	$spiders = $cache->read("spiders");

	while($user = $db->fetch_array($query))
	{
		$plugins->run_hooks("online_user");

		// Fetch the WOL activity
		$user['activity'] = fetch_wol_activity($user['location'], $user['nopermission']);

		$botkey = my_strtolower(str_replace("bot=", '', $user['sid']));

		// Have a registered user
		if($user['uid'] > 0)
		{
			if(empty($users[$user['uid']]) || $users[$user['uid']]['time'] < $user['time'])
			{
				$users[$user['uid']] = $user;
			}
		}
		// Otherwise this session is a bot
		else if(my_strpos($user['sid'], "bot=") !== false && $spiders[$botkey])
		{
			$user['bot'] = $spiders[$botkey]['name'];
			$user['usergroup'] = $spiders[$botkey]['usergroup'];
			$guests[] = $user;
		}
		// Or a guest
		else
		{
			$guests[] = $user;
		}
	}

	// Now we build the actual online rows - we do this separately because we need to query all of the specific activity and location information
	$online_rows = [];
	if(isset($users) && is_array($users))
	{
		reset($users);
		foreach($users as $user)
		{
			$row = build_wol_row($user);
			if ($row) {
			    $online_rows[] = $row;
            }
		}
	}
	if(isset($guests) && is_array($guests))
	{
		reset($guests);
		foreach($guests as $user)
		{
            $row = build_wol_row($user);
            if ($row) {
                $online_rows[] = $row;
            }
		}
	}

	// Fetch the most online information
	$most_online = $cache->read("mostonline");
	$record_count = $most_online['numusers'];
	$record_date = my_date('relative', $most_online['time']);

	$plugins->run_hooks("online_end");

    output_page(\MyBB\template('online/online.twig', [
        'refresh_string' => $refresh_string,
        'online_users' => $online_rows,
        'multipage' => $multipage,
    ]));
}
