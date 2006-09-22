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

define("IN_MYBB", 1);

$templatelist = "online,online_row,online_row_ip,online_today,online_today_row,online_iplookup,mostonline";
require_once "./global.php";
require_once MYBB_ROOT."inc/functions_post.php";
require_once MYBB_ROOT."inc/functions_online.php";
require_once MYBB_ROOT."inc/class_parser.php";
$parser = new postParser;
// Load global language phrases
$lang->load("online");

if($mybb->usergroup['canviewonline'] == "no")
{
	error_no_permission();
}

// Make navigation
add_breadcrumb($lang->nav_online, "online.php");

switch($mybb->input['action'])
{
	case "today":
		add_breadcrumb($lang->nav_onlinetoday);
		break;
	case "iplookup":
		add_breadcrumb($lang->nav_iplookup);
		break;
}

if($mybb->input['action'] == "today")
{

	$plugins->run_hooks("online_today_start");

	$todaycount = 0;
	$stime = time()-(60*60*24);
	$todayrows = '';
	$query = $db->query("
		SELECT u.*
		FROM ".TABLE_PREFIX."users u
		LEFT JOIN ".TABLE_PREFIX."usergroups g ON (g.gid=u.usergroup)
		WHERE u.lastactive > $stime
		ORDER BY u.lastactive DESC
	");
	while($online = $db->fetch_array($query))
	{
		if($online['invisible'] != "yes" || $mybb->usergroup['canviewwolinvis'] == "yes" || $online['uid'] == $mybb->user['uid'])
		{
			if($online['invisible'] == "yes")
			{
				$invisiblemark = "*";
			}
			else
			{
				$invisiblemark = "";
			}
			$username = $online['username'];
			$username = format_name($username, $online['usergroup'], $online['displaygroup']);
			$onlinetime = my_date($mybb->settings['timeformat'], $online['lastactive']);
			eval("\$todayrows .= \"".$templates->get("online_today_row")."\";");
		}
		++$todaycount;
	}
	if($todaycount == 1)
	{
		$onlinetoday = $lang->member_online_today;
	}
	else
	{
		$onlinetoday = sprintf($lang->members_were_online_today, $todaycount);
	}

	$plugins->run_hooks("online_today_end");

	eval("\$today = \"".$templates->get("online_today")."\";");
	output_page($today);
}
elseif($mybb->input['action'] == "iplookup")
{
	if($mybb->usergroup['canviewonlineips'] == "no")
	{
		error_no_permission();
	}
	$ip = $mybb->input['ip'];
	$host = @gethostbyaddr($ip);
	$ip = htmlspecialchars($ip);
	if(!$host || $host == $ip)
	{
		error($lang->error_nohostname);
	}

	// Admin options
	$adminoptions = "";
	if($mybb->usergroup['cancp'] == "yes")
	{
		eval("\$adminoptions = \"".$templates->get("online_iplookup_adminoptions")."\";");
	}
	eval("\$iplookup = \"".$templates->get("online_iplookup")."\";");
	output_page($iplookup);
}
else
{
	$plugins->run_hooks("online_start");

	$aidsql = '';
	$pidsql = '';
	$uidsql = '';
	$tidsql = '';
	$fidsql = '';
	$eidsql = '';
	$onlinerows = '';
	if($mybb->input['sortby'] == "username")
	{
		$sql = "u.username ASC, s.time DESC";
		$refresh_string = "?sortby=username";
	}
	elseif($mybb->input['sortby'] == "location")
	{
		$sql = "s.location, s.time DESC";
		$refresh_string = "?sortby=location";
	}
	else
	{
		$sql = "s.time DESC";
		$refresh_string = '';
	}
	$timesearch = time() - $mybb->settings['wolcutoffmins']*60;
	$query = $db->query("
		SELECT DISTINCT s.sid, s.ip, s.uid, s.time, s.location, u.username, s.nopermission, u.invisible, u.usergroup, u.displaygroup
		FROM ".TABLE_PREFIX."sessions s
		LEFT JOIN ".TABLE_PREFIX."users u ON (s.uid=u.uid)
		WHERE s.time>'$timesearch'
		ORDER BY $sql
	");
	$membercount = 0;
	$guestcount = 0;
	$anoncount = 0;
	while($user = $db->fetch_array($query))
	{
		$plugins->run_hooks("online_user");
		$botkey = my_strtolower(str_replace("bot=", '', $user['sid']));
		if($user['uid'] > 0)
		{
			if($doneusers[$user['uid']] < $user['time'] || !$doneusers[$user['uid']])
			{
				$doneusers[$user['uid']] = $user['time'];
				$users[$user['uid']] = what($user);
				if($user['invisible'] == "yes")
				{
					++$anoncount;
				}
				$membercount++;
			}
		}
		elseif(strstr($user['sid'], "bot=") !== false && $session->bots[$botkey])
		{
			$user['bot'] = $session->bots[$botkey];
			$guests[] = what($user);
			++$botcount;
		}
		else
		{
			$guests[] = what($user);
			++$guestcount;
		}
	}

	// Get forum permissions
	$unviewableforums = get_unviewable_forums();
	if($unviewableforums)
	{
		$fidnot = " AND fid NOT IN ($unviewableforums)";
	}
	if($uidsql)
	{
		$query = $db->simple_select("users", "uid,username", "uid IN (0$uidsql)");
		while($user = $db->fetch_array($query))
		{
			$members[$user['uid']] = $user['username'];
		}
	}
	if($aidsql)
	{
		$query = $db->simple_select("attachments", "aid,pid", "aid IN (0$aidsql)");
		while($attachment = $db->fetch_array($query))
		{
			$attachments[$attachment['aid']] = $attachment['pid'];
			$pidsql .= ",{$attachment['pid']}";
		}
	}
	if($pidsql)
	{
		$query = $db->simple_select("posts", "pid,tid", "pid IN (0$pidsql) $fidnot");
		while($post = $db->fetch_array($query))
		{
			$posts[$post['pid']] = $post['tid'];
			$tidsql .= ",{$post['tid']}";
		}
	}
	if($tidsql)
	{
		$query = $db->simple_select("threads", "fid,tid,subject", "tid IN(0$tidsql) $fidnot");
		while($thread = $db->fetch_array($query))
		{
			$threads[$thread['tid']] = htmlspecialchars_uni($parser->parse_badwords($thread['subject']));
			$fidsql .= ",{$thread['fid']}";
		}
	}
	if($fidsql)
	{
		$query = $db->simple_select("forums", "fid,name,linkto", "fid IN (0$fidsql) $fidnot");
		while($forum = $db->fetch_array($query))
		{
			$forums[$forum['fid']] = $forum['name'];
			$forums_linkto[$forum['fid']] = $forum['linkto'];
		}
	}
	if($eidsql)
	{
		$query = $db->simple_select("events", "eid,subject", "eid IN (0$eidsql)");
		while($event = $db->fetch_array($query))
		{
			$events[$event['eid']] = htmlspecialchars_uni($parser->parse_badwords($event['subject']));
		}
	}

	if(is_array($users))
	{
		reset($users);
		foreach($users as $key => $val)
		{
			$users[$key] = show($val);
		}
		reset($users);
	}
	if(is_array($guests))
	{
		reset($guests);
		foreach($guests as $key => $val)
		{
			$guests[$key] = show($val);
		}
		reset($guests);
	}
	$usercount = $membercount + $guestcount;
	$mostonline = $cache->read("mostonline");
	$recordcount = $mostonline['numusers'];
	$recorddate = my_date($mybb->settings['dateformat'], $mostonline['time']);
	$recordtime = my_date($mybb->settings['timeformat'], $mostonline['time']);
	if($mybb->settings['refreshwol'] != "no")
	{
		$refresh = "<meta http-equiv=\"refresh\" content=\"60;URL=online.php$refresh_string\" />";
	}
	if($usercount != 1)
	{
		$userbit = $lang->online_online_plural;
	}
	else
	{
		$userbit = $lang->online_online_singular;
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
	$lang->online_count = sprintf($lang->online_count, my_number_format($usercount), $userbit, $mybb->settings['wolcutoffmins'], my_number_format($membercount), $memberbit, my_number_format($anoncount), $anonbit, my_number_format($guestcount), $guestbit);
	$plugins->run_hooks("online_end");

	eval("\$online = \"".$templates->get("online")."\";");
	output_page($online);
}

?>