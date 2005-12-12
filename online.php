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
 define("KILL_GLOBALS", 1);

 $templatelist = "online,online_row,online_row_ip,online_today,online_today_row,online_iplookup,mostonline,posticons";
require "./global.php";
require "./inc/functions_post.php";

// Load global language phrases
$lang->load("online");

if($mybb->usergroup['canviewonline'] == "no")
{
	nopermission();
}

// Make navigation
addnav($lang->nav_online, "online.php");

switch($mybb->input['action'])
{
	case "today":
		addnav($lang->nav_onlinetoday);
		break;
	case "iplookup":
		addnav($lang->nav_iplookup);
		break;
}

if($mybb->input['action'] == "today")
{

	$plugins->run_hooks("online_today_start");

	$todaycount = 0;
	$stime = time()-(60*60*24);
	
	$query = $db->query("SELECT u.* FROM ".TABLE_PREFIX."users u LEFT JOIN ".TABLE_PREFIX."usergroups g ON (g.gid=u.usergroup) WHERE u.lastactive > $stime ORDER BY u.lastactive DESC");
	while($online = $db->fetch_array($query))
	{
		if($online['invisible'] != "yes" || $mybb->usergroup['canviewwolinvis'] == "yes")
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
			$username = formatname($username, $online['usergroup'], $online['displaygroup']);
			$onlinetime = mydate($mybb->settings['timeformat'], $online['lastactive']);
			eval("\$todayrows .= \"".$templates->get("online_today_row")."\";");
		}
		$todaycount++;
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
	outputpage($today);
}
elseif($mybb->input['action'] == "iplookup")
{
	if($mybb->usergroup['canviewonlineips'] == "no")
	{
		nopermission();
	}
	$ip = $mybb->input['ip'];
	$host = @gethostbyaddr($ip);
	if(!$host || $host == $ip)
	{
		error($lang->error_nohostname);
	}
	eval("\$iplookup = \"".$templates->get("online_iplookup")."\";");
	outputpage($iplookup);
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
	if($mybb->input['sortby'] == "username")
	{
		$sql = "u.username ASC, s.time DESC";
	}
	elseif($mybb->input['sortby'] == "location")
	{
		$sql = "s.location, s.time DESC";
	}
	else
	{
		$sql = "s.time DESC";
	}
	$timesearch = time() - $mybb->settings['wolcutoffmins']*60;
	$query = $db->query("SELECT DISTINCT s.sid, s.ip, s.uid, s.time, s.location, u.username, s.nopermission, u.invisible, u.usergroup, u.displaygroup FROM ".TABLE_PREFIX."sessions s LEFT JOIN ".TABLE_PREFIX."users u ON (s.uid=u.uid) WHERE s.time>'$timesearch' ORDER BY $sql");
	$membercount = 0;
	$guestcount = 0;
	$anoncount = 0;
	while($user = $db->fetch_array($query))
	{
		$plugins->run_hooks("online_user");
		$botkey = strtolower(str_replace("bot=", "", $user['sid']));
		if($user['uid'] > 0)
		{
			if($doneusers[$user['uid']] < $user['time'] || !$doneusers[$user['uid']])
			{
				$doneusers[$user['uid']] = $user['time'];
				$users[$user['uid']] = what($user);
				if($user['invisible'] == "yes")
				{
					$anoncount++;
				}
				$membercount++;
			}
		}
		elseif(strstr($user['sid'], "bot=") !== false && $session->bots[$botkey])
		{
			$user['bot'] = $session->bots[$botkey];
			$guests[] = what($user);
			$botcount++;
		}
		else
		{
			$guests[] = what($user);
			$guestcount++;
		}
	}

	// Get forum permissions
	$unviewableforums = getunviewableforums();
	if($unviewableforums)
	{
		$fidnot = " AND fid NOT IN ($unviewableforums)";
	}
	if($uidsql)
	{
		$query = $db->query("SELECT uid,username FROM ".TABLE_PREFIX."users WHERE uid IN (0$uidsql)");
		while($user = $db->fetch_array($query))
		{
			$members[$user['uid']] = $user['username'];
		}
	}
	if($aidsql)
	{
		$query = $db->query("SELECT aid,pid FROM ".TABLE_PREFIX."attachments WHERE aid IN(0$aidsql)");
		while($attachment = $db->fetch_array($query))
		{
			$attachments[$attachment['aid']] = $attachment['pid'];
			$pidsql .= ",$attachment[pid]";
		}
	}
	if($pidsql)
	{
		$query = $db->query("SELECT pid,tid FROM ".TABLE_PREFIX."posts WHERE pid IN(0$pidsql) $fidnot");
		while($post = $db->fetch_array($query))
		{
			$posts[$post['pid']] = $post['tid'];
			$tidsql .= ",$post[tid]";
		}
	}
	if($tidsql)
	{
		$query = $db->query("SELECT fid,tid,subject FROM ".TABLE_PREFIX."threads WHERE tid IN(0$tidsql) $fidnot");
		while($thread = $db->fetch_array($query))
		{
			$threads[$thread['tid']] = htmlspecialchars_uni(stripslashes(dobadwords($thread['subject'])));
			$fidsql .= ",$thread[fid]";
		}
	}
	if($fidsql)
	{
		$query = $db->query("SELECT fid,name FROM ".TABLE_PREFIX."forums WHERE fid IN (0$fidsql) $fidnot");
		while($forum = $db->fetch_array($query))
		{
			$forums[$forum['fid']] = $forum['name'];
		}
	}
	if($eidsql)
	{
		$query = $db->query("SELECT eid,subject FROM ".TABLE_PREFIX."events WHERE eid IN (0$eidsql)");
		while($event = $db->fetch_array($query))
		{
			$events[$event['eid']] = htmlspecialchars_uni(dobadwords($event['subject']));
		}
	}

	if(is_array($users))
	{
		reset($users);
		while(list($key, $val) = each($users))
		{
			$users[$key] = show($val);
		}
		reset($users);
	}
	if(is_array($guests))
	{
		reset($guests);
		while(list($key, $val) = each($guests))
		{
			$guests[$key] = show($val);
		}
		reset($guests);
	}
	$usercount = $membercount + $guestcount;
	$mostonline = $cache->read("mostonline");
	$recordcount = $mostonline['numusers'];
	$recorddate = mydate($mybb->settings['dateformat'], $mostonline['time']);
	$recordtime = mydate($mybb->settings['timeformat'], $mostonline['time']);
	if($mybb->settings['refreshwol'] != "no")
	{
		$refresh = "<meta http-equiv=\"refresh\" content=\"60;URL=online.php\">";
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
	$lang->online_count = sprintf($lang->online_count, mynumberformat($usercount), $userbit, $mybb->settings['wolcutoffmins'], mynumberformat($membercount), $memberbit, mynumberformat($anoncount), $anonbit, mynumberformat($guestcount), $guestbit);
	$plugins->run_hooks("online_end");

	eval("\$online = \"".$templates->get("online")."\";");
	outputpage($online);
}

function show($user)
{
	global $threads, $forums, $posts, $events, $members, $settings, $theme, $mybb, $mybbuser, $onlinerows, $templates, $mybbgroup, $lang, $bots;

	switch($user['activity'])
	{
		// announcement.php functions
		case "announcements":
			if($forums[$user['fid']])
			{
				$locationname = sprintf($lang->viewing_announcements, $user['fid'], $forums[$user['fid']]);
			}
			else
			{
				$locationname = $lang->viewing_announcements2;
			}
			break;
		// attachment.php actions
		case "attachment":
			$aid = $posts[$user['aid']];
			$pid = $attachments[$aid];
			$tid = $posts[$pid];
			if($threads[$tid])
			{
				$locationname = sprintf($lang->viewing_attachment2, $tid, $threads[$tid]);
			}
			else
			{
				$locationname = $lang->viewing_attachment;
			}
			break;
		// calendar.php functions
		case "calendar":
			$locationname = $lang->viewing_calendar;
			break;
		case "calendar_event":
			if($events[$user['eid']])
			{
				$locationname = sprintf($lang->viewing_event2, $user['eid'], $events[$user['eid']]);
			}
			else
			{
				$locationname = $lang->viewing_event;
			}
			break;
		case "calendar_addevent":
			$locationname = $lang->adding_event;
			break;
		case "calendar_editevent":
			$locationname = $lang->editing_event;
			break;
		// editpost.php functions
		case "editpost":
			$locationname = $lang->editing_post;
			break;
		// forumdisplay.php functions
		case "forumdisplay":
			if($forums[$user['fid']])
			{
				$locationname = sprintf($lang->viewing_forum2, $user['fid'], $forums[$user['fid']]);
			}
			else
			{
				$locationname = $lang->viewing_forum;
			}
			break;
		// index.php functions
		case "index":
			$locationname = sprintf($lang->viewing_index, $mybb->settings['bbname']);
			break;
		// member.php functions
		case "member_activate":
			$locationname = $lang->activating_account;
			break;
		case "member_profile":
			if($members[$user['uuid']])
			{
				$locationname = sprintf($lang->viewing_profile2, $user['uuid'], $members[$user['uuid']]);
			}
			else
			{
				$locationname = $lang->viewing_profile;
			}
			break;
		case "member_register":
			$locationname = $lang->registering;
			break;
		case "member_login":
			$locationname = $lang->logging_in;
			break;
		case "member_logout":
			$locationname = $lang->logging_out;
			break;
		case "member_emailuser":
			$locationname = $lang->emailing_user;
			break;
		case "member_rate":
			$locationname = $lang->rating_user;
			break;
		case "member_resendactivation":
			$locationname = $lang->resending_account_activation;
			break;
		case "member_lostpw":
			$locationname = $lang->retrieving_lost_pw;
			break;
		// memberlist.php functions
		case "memberlist":
			$locationname = $lang->viewing_memberlist;
			break;
		// misc.php functions
		case "misc_whoposted":
			if($threads[$user['tid']])
			{
				$locationname = sprintf($lang->viewing_whoposted2, $user['tid'], $threads[$user['tid']]);
			}
			else
			{
				$locationname = $lang->viewing_whoposted;
			}
			break;
		case "misc_markread":
			$locationname = $lang->marking_read;
			break;
		case "misc_help":
			$locationname = $lang->viewing_helpdocs;
			break;
		case "misc_buddypopup":
			$locationname = $lang->viewing_buddylist;
			break;
		case "misc_smilies":
			$locationname = $lang->viewing_smilies;
			break;
		case "misc_syndication":
			$locationname = $lang->viewing_syndication;
			break;
		// moderation.php functions
		case "moderation":
			$locationname = $lang->using_modtools;
			break;
		// newreply.php functions
		case "newreply":
			if($user['pid'])
			{
				$user['tid'] = $posts[$user['pid']];
			}
			if($threads[$user['tid']])
			{
				$locationname = sprintf($lang->replying_thread2, $user['tid'], $threads[$user['tid']]);
			}
			else
			{
				$locationname = $lang->replying_thread;
			}
			break;
		// newthread.php functions
		case "newthread":
			if($forums[$user['fid']])
			{
				$locationname = sprintf($lang->posting_thread, $user['fid'], $forums[$user['fid']]);
			}
			else
			{
				$locationname = $lang->posting_thread;
			}
			break;
		// online.php functions
		case "wol":
			$locationname = $lang->viewing_wol;
			break;
		case "woltoday":
			$locationname = $lang->viewing_woltoday;
			break;
		// polls.php functions
		case "newpoll":
			$locationname = $lang->creating_poll;
			break;
		case "editpoll":
			$locationname = $lang->editing_poll;
			break;
		case "showresults":
			$locationname = $lang->viewing_pollresults;
			break;
		case "vote":
			$locationname = $lang->voting_poll;
			break;
		// postings.php functions
		case "postings":
			$locationname = $lang->using_modtools;
			break;
		// private.php functions
		case "private_send":
			$locationname = $lang->sending_pm;
			break;
		case "private_read":
			$locationname = $lang->reading_pm;
			break;
		case "private_folders":
			$locationname = $lang->editing_pmfolders;
			break;
		case "private":
			$locationname = $lang->using_pmsystem;
			break;
		// report.php functions
		case "report":
			$locationname = $lang->reporting_post;
			break;
		// reputation.php functions
		case "reputation":
			$locationname = $lang->giving_reputation;
			break;
		// search.php functions
		case "search":
			$locationname = sprintf($lang->searching_forum, $mybb->settings['bbname']);
			break;
		// showthread.php functions
		case "showthread":
			if($threads[$user['tid']])
			{
				$pagenote = "";
				$locationname = sprintf($lang->reading_thread2, $user['tid'], $threads[$user['tid']], $pagenote);
			}
			else
			{
				$locationname = $lang->reading_thread;
			}
			break;
		// showteam.php functions
		case "showteam":
			$locationname = $lang->viewing_team;
			break;
		// stats.php functions
		case "stats":
			$locationname = $lang->viewing_stats;
			break;
		// usercp.php functions
		case "usercp_profile":
			$locationname = $lang->updating_profile;
			break;
		case "usercp_options":
			$locationname = $lang->updating_options;
			break;
		case "usercp_editsig":
			$locationname = $lang->editing_signature;
			break;
		case "usercp_avatar":
			$locationname = $lang->changing_avatar;
			break;
		case "usercp_subscriptions":
			$locationname = $lang->viewing_subscriptions;
			break;
		case "usercp_favorites":
			$locationname = $lang->viewing_favorites;
			break;
		case "usercp_notepad":
			$locationname = $lang->editing_pad;
			break;
		case "usercp":
			$locationname = $lang->user_cp;
			break;
		case "portal":
			$locationname = $lang->viewing_portal;
			break;
	}
	if($user['nopermission'] == 1)
	{
		$locationname = $lang->viewing_noperms;
	}
	if(!$locationname)
	{
		$locationname = sprintf($lang->unknown_location, $user['location']);
	}

	if($user['uid'] > 0)
	{
		if($user['invisible'] != "yes" || $mybb->usergroup['canviewwolinvis'] == "yes")
		{
			if($user['invisible'] == "yes")
			{
				$invisiblemark = "*";
			}
			else 
			{
				$invisiblemark = "";
			}
			$user['username'] = formatname($user['username'], $user['usergroup'], $user['displaygroup']);
			$onlinename = "<a href=\"member.php?action=profile&uid=".$user['uid']."\">".$user['username']."</a>".$invisiblemark;
		}
	}
	elseif($user['bot'])
	{
		$onlinename = formatname($user['bot'], $botgroup);
	}
	else
	{
		$onlinename = formatname($lang->guest, 1);
	}
	$onlinetime = mydate($mybb->settings['timeformat'], $user['time']);
	if($mybb->usergroup['canviewonlineips'] == "yes")
	{
		eval("\$userip = \"".$templates->get("online_row_ip")."\";");
	}
	if($user['invisible'] != "yes" || $mybb->usergroup['canviewwolinvis'] == "yes")
	{
		eval("\$onlinerows .= \"".$templates->get("online_row")."\";");
	}
}


function what($user)
{
	global $mybb, $mybbuser, $settings, $theme, $fidsql, $tidsql, $pidsql, $eidsql, $uidsql, $mybbgroup;
	$splitloc = explode(".php", $user['location']);
	if($splitloc[0] == $user['location'])
	{
		$filename = "";
	}
	else
	{
		$filename = substr($splitloc[0], -strpos(strrev($splitloc[0]), "/"));
	}
	if($splitloc[1])
	{
		$temp = explode("&", substr($splitloc[1], 1));
		for ($i = 0; $i < count($temp); $i++)
		{
			$temp2 = explode("=", $temp[$i], 2);
			$parameters[$temp2[0]] = $temp2[1];
		}
	}
	
	switch($filename)
	{
		case "announcements":
			if(is_numeric($parameters['fid']))
			{
				$fidsql .= ",$parameters[fid]";
			}
			$user['activity'] = "announcements";
			$user['fid'] = $parameters['fid'];
			break;
		case "attachment":
			if(is_numeric($parameters['aid']))
			{
				$aidsql .= ",$parameters[aid]";
			}
			$user['activity'] = "attachment";
			$user['aid'] = $parameters['aid'];
			break;
		case "calendar":
			if($parameters['action'] == "event")
			{
				if(is_numeric($parameters['eid']))
				{
					$eidsql .= ",$parameters[eid]";
				}
				$user['activity'] = "calendar_event";
				$user['eid'] = $parameters['eid'];
			}
			elseif($parameters['action'] == "addevent")
			{
				$user['activity'] = "calendar_addevent";
			}
			elseif($parameters['action'] == "editevent")
			{
				$user['activity'] = "calendar_editevent";
			}
			else
			{
				$user['activity'] = "calendar";
			}
			break;
		case "editpost":
			$user['activity'] = "editpost";
			break;
		case "forumdisplay":
			if(is_numeric($parameters['fid']))
			{
				$fidsql .= ",$parameters[fid]";
			}
			$user['activity'] = "forumdisplay";
			$user['fid'] = $parameters['fid'];
			break;
		case "index":
		case "":
			$user['activity'] = "index";
			break;
		case "member":
			if($parameters['action'] == "activate")
			{
				$user['activity'] = "member_activate";
			}
			elseif($parameters['action'] == "register")
			{
				$user['activity'] = "member_register";
			}
			elseif($parameters['action'] == "login")
			{
				$user['activity'] = "member_login";
			}
			elseif($parameters['action'] == "logout") 
			{
				$user['activity'] = "member_logout";
			}
			elseif($parameters['action'] == "profile")
			{
				$user['activity'] = "member_profile";
				if(is_numeric($parameters['uid']))
				{
					$uidsql .= ",$parameters[uid]";
				}
				$user['uuid'] = $parameters['uid'];
			}
			elseif($parameters['action'] == "emailuser")
			{
				$user['activity'] = "member_emailuser";
			}
			elseif($parameters['action'] == "rate")
			{
				$user['activity'] = "member_rate";
			}
			elseif($parameters['action'] == "resendactivation" || $parameters['action'] == "do_resendactivation")
			{
				$user['activity'] = "member_resendactivation";
			}
			elseif($parameters['action'] == "lostpw" || $parameters['action'] == "do_lostpw" || $parameters['action'] == "resetpassword")
			{
				$user['activity'] = "member_lostpw";
			}
			else
			{
				$user['activity'] = "member";
			}
			break;
		case "memberlist":
			$user['activity'] = "memberlist";
			break;
		case "misc":
			if($parameters['action'] == "whoposted")
			{
				if(is_numeric($parameters['tid']))
				{
					$tidsql .= ",$parameters[tid]";
				}
				$user['activity'] = "misc_whoposted";
				$user['tid'] = $parameters['tid'];
			}
			elseif($parameters['action'] == "markread")
			{
				$user['activity'] = "misc_markread";
			}
			elseif($parameters['action'] == "help")
			{
				$user['activity'] = "misc_help";
			}
			elseif($parameters['action'] == "buddypopup")
			{
				$user['activity'] = "misc_buddypopup";
			}
			elseif($parameters['action'] == "smilies")
			{
				$user['activity'] = "misc_smilies";
			}
			elseif($parameters['action'] == "syndication")
			{
				$user['activity'] = "misc_syndication";
			}
			else
			{
				$user['activity'] = "misc";
			}
			break;
		case "moderation":
			$user['activity'] = "moderation";
			break;
		case "newreply":
			if(is_numeric($parameters['pid']))
			{
				$pidsql .= ",$parameters[pid]";
				$user['activity'] = "newreply";
				$user['pid'] = $parameters['pid'];
			}
			else
			{
				if(is_numeric($parameters['tid']))
				{
					$tidsql .= ",$parameters[tid]";
				}
				$user['activity'] = "newreply";
				$user['tid'] = $parameters['tid'];
			}
			break;
		case "newthread":
			if(is_numeric($parameters['fid']))
			{
				$fidsql .= ",$parameters[fid]";
			}
			$user['activity'] = "newthread";
			$user['fid'] = $parameters['fid'];
			break;
		case "online":
			if($parameters['action'] == "today")
			{
				$user['activity'] = "woltoday";
			}
			else
			{
				$user['activity'] = "wol";
			}
			break;
		case "polls":
			$user['activity'] = $parameters['action'];
			break;
		case "postings":
			$user['activity'] = "postings";
			break;
		case "printthread":
			if(is_numeric($parameters['tid']))
			{
				$tidsql .= ",$parameters[tid]";
			}
			$user['activity'] = "printthread";
			$user['tid'] = $parameters['tid'];
		case "private":
			if($parameters['action'] == "send")
			{
				$user['activity'] = "private_send";
			}
			elseif($parameters['action'] == "show")
			{
				$user['activity'] = "private_read";
			}
			elseif($parameters['action'] == "folders")
			{
				$user['activity'] = "private_folders";
			}
			else
			{
				$user['activity'] = "private";
			}
			break;
		case "report":
			$user['activity'] = "report";
			break;
		case "reputation":
			$user['activity'] = "reputation";
			break;
		case "search":
			$user['activity'] = "search";
			break;
		case "sendthread":
			if(is_numeric($parameters['tid']))
			{
				$tidsql .= ",$parameters[tid]";
			}
			$user['activity'] = "sendthread";
			$user['tid'] = $parameters['tid'];
		case "showteam":
			$user['activity'] = "showteam";
			break;
		case "showthread":
			if(is_numeric($parameters['pid']) && $parameters['action'] == "showpost")
			{
				$pidsql .= ",$parameters[pid]";
				$user['activity'] = "showpost";
				$user['pid'] = $parameters['pid'];
			}
			else
			{
				if($parameters['page'])
				{
					$user['page'] = $parameters['page'];
				}
				if(is_numeric($parameters['tid']))
				{
					$tidsql .= ",$parameters[tid]";
				}
				$user['activity'] = "showthread";
				$user['tid'] = $parameters['tid'];
			}
			break;
		case "stats":
			$user['activity'] = "stats";
			break;
		case "usercp":
			if($parameters['action'] == "profile")
			{
				$user['activity'] = "usercp_profile";
			}
			elseif($parameters['action'] == "options")
			{
				$user['activity'] = "usercp_options";
			}
			elseif($parameters['action'] == "password")
			{
				$user['activity'] = "usercp_password";
			}
			elseif($parameters['action'] == "editsig")
			{
				$user['activity'] = "usercp_editsig";
			}
			elseif($parameters['action'] == "avatar")
			{
				$user['activity'] = "usercp_avatar";
			}
			elseif($parameters['action'] == "editlists")
			{
				$user['activity'] = "usercp_editlists";
			}
			elseif($parameters['action'] == "favorites")
			{
				$user['activity'] = "usercp_favorites";
			}
			elseif($parameters['action'] == "subscriptions")
			{
				$user['activity'] = "usercp_subscriptions";
			}
			elseif($parameters['action'] == "notepad")
			{
				$user['activity'] = "usercp_notepad";
			}
			else
			{
				$user['activity'] = "usercp";
			}
			break;
		case "portal";
			$user['activity'] = "portal";
			break;
		case "nopermission":
			$user['activity'] = "nopermission";
			break;
		default:
			$user['activity'] = "unknown";
			break;
	}
	return $user;
}

?>
