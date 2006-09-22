<?php
/**
 * MyBB 1.2
 * Copyright © 2006 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.com
 * License: http://www.mybboard.com/eula.html
 *
 * $Id:
 */

function show($user, $return=false)
{
	global $threads, $forums, $forums_linkto, $posts, $events, $members, $theme, $mybb, $onlinerows, $templates, $lang, $session;

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
				if($forums_linkto[$user['fid']])
				{
					$locationname = sprintf($lang->forum_redirect_to, $user['fid'], $forums[$user['fid']]);
				}
				else
				{
					$locationname = sprintf($lang->viewing_forum2, $user['fid'], $forums[$user['fid']]);
				}
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
		case "member":
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
			$locationname = $lang->member_lostpw;
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
		case "misc_imcenter":
			$locationname = $lang->viewing_imcenter;
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
				$locationname = sprintf($lang->posting_thread2, $user['fid'], $forums[$user['fid']]);
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
		/* Ratethread functions */
		case "ratethread":
			$locationname = $lang->rating_thread;
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
				$pagenote = '';
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
		case "usercp2_favorites":
			$locationname = $lang->managing_favorites;
			break;
		case "usercp2_subscriptions":
			$locationname = $lang->managing_subscriptions;
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
		if($user['invisible'] != "yes" || $mybb->usergroup['canviewwolinvis'] == "yes" || $user['uid'] == $mybb->user['uid'])
		{
			if($user['invisible'] == "yes")
			{
				$invisiblemark = "*";
			}
			else
			{
				$invisiblemark = '';
			}
			$user['username'] = format_name($user['username'], $user['usergroup'], $user['displaygroup']);
			$onlinename = build_profile_link($user['username'], $user['uid']).$invisiblemark;
		}
	}
	elseif($user['bot'])
	{
		$onlinename = format_name($user['bot'], $session->botgroup);
	}
	else
	{
		$onlinename = format_name($lang->guest, 1);
	}
	$onlinetime = my_date($mybb->settings['timeformat'], $user['time']);
	if($mybb->usergroup['canviewonlineips'] == "yes")
	{
		eval("\$userip = \"".$templates->get("online_row_ip")."\";");
	}
	else
	{
		$user['ip'] = '';
	}
	if($user['invisible'] != "yes" || $mybb->usergroup['canviewwolinvis'] == "yes" || $user['uid'] == $mybb->user['uid'])
	{
		eval("\$onlinerows .= \"".$templates->get("online_row")."\";");
	}
	if($return != false)
	{
		return array(
			"onlinename" => $onlinename,
			"userip" => $user['ip'],
			"onlinetime" => $onlinetime,
			"locationname" => $locationname
		);
	}
}


function what($user)
{
	global $mybb, $theme, $fidsql, $tidsql, $pidsql, $eidsql, $uidsql;
	$splitloc = explode(".php", $user['location']);
	if($splitloc[0] == $user['location'])
	{
		$filename = '';
	}
	else
	{
		$filename = my_substr($splitloc[0], -strpos(strrev($splitloc[0]), "/"));
	}
	if($splitloc[1])
	{
		$temp = explode("&", my_substr($splitloc[1], 1));
		for ($i = 0; $i < count($temp); ++$i)
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
				$fidsql .= ",{$parameters['fid']}";
			}
			$user['activity'] = "announcements";
			$user['fid'] = $parameters['fid'];
			break;
		case "attachment":
			if(is_numeric($parameters['aid']))
			{
				$aidsql .= ",{$parameters['aid']}";
			}
			$user['activity'] = "attachment";
			$user['aid'] = $parameters['aid'];
			break;
		case "calendar":
			if($parameters['action'] == "event")
			{
				if(is_numeric($parameters['eid']))
				{
					$eidsql .= ",{$parameters['eid']}";
				}
				$user['activity'] = "calendar_event";
				$user['eid'] = $parameters['eid'];
			}
			elseif($parameters['action'] == "addevent" || $parameters['action'] == "do_addevent")
			{
				$user['activity'] = "calendar_addevent";
			}
			elseif($parameters['action'] == "editevent" || $parameters['action'] == "do_editevent")
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
				$fidsql .= ",{$parameters['fid']}";
			}
			$user['activity'] = "forumdisplay";
			$user['fid'] = $parameters['fid'];
			break;
		case "index":
		case '':
			$user['activity'] = "index";
			break;
		case "member":
			if($parameters['action'] == "activate")
			{
				$user['activity'] = "member_activate";
			}
			elseif($parameters['action'] == "register" || $parameters['action'] == "do_register")
			{
				$user['activity'] = "member_register";
			}
			elseif($parameters['action'] == "login" || $parameters['action'] == "do_login")
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
					$uidsql .= ",{$parameters['uid']}";
				}
				$user['uuid'] = $parameters['uid'];
			}
			elseif($parameters['action'] == "emailuser" || $parameters['action'] == "do_emailuser")
			{
				$user['activity'] = "member_emailuser";
			}
			elseif($parameters['action'] == "rate" || $parameters['action'] == "do_rate")
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
			$accepted_parameters = array("markread", "help", "buddypopup", "smilies", "syndication", "imcenter");
			if($parameters['action'] == "whoposted")
			{
				if(is_numeric($parameters['tid']))
				{
					$tidsql .= ",{$parameters['tid']}";
				}
				$user['activity'] = "misc_whoposted";
				$user['tid'] = $parameters['tid'];
			}
			
			elseif(in_array($parameters['action'], $accepted_parameters))
			{
				$user['activity'] = "misc_".$parameters['action'];
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
				$pidsql .= ",{$parameters['pid']}";
				$user['activity'] = "newreply";
				$user['pid'] = $parameters['pid'];
			}
			else
			{
				if(is_numeric($parameters['tid']))
				{
					$tidsql .= ",{$parameters['tid']}";
				}
				$user['activity'] = "newreply";
				$user['tid'] = $parameters['tid'];
			}
			break;
		case "newthread":
			if(is_numeric($parameters['fid']))
			{
				$fidsql .= ",{$parameters['fid']}";
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
			// Make the "do" parts the same as the other one.
			if($parameters['action'] == "do_newpoll")
			{
				$user['activity'] = "newpoll";
			}
			elseif($parameters['action'] == "do_editpoll")
			{
				$user['activity'] = "editpoll";
			}
			else
			{
				$user['activity'] = $parameters['action'];
			}
			break;
		case "printthread":
			if(is_numeric($parameters['tid']))
			{
				$tidsql .= ",{$parameters['tid']}";
			}
			$user['activity'] = "printthread";
			$user['tid'] = $parameters['tid'];
		case "private":
			if($parameters['action'] == "send" || $parameters['action'] == "do_send")
			{
				$user['activity'] = "private_send";
			}
			elseif($parameters['action'] == "show")
			{
				$user['activity'] = "private_read";
			}
			elseif($parameters['action'] == "folders" || $parameters['action'] == "do_folders")
			{
				$user['activity'] = "private_folders";
			}
			else
			{
				$user['activity'] = "private";
			}
			break;
		case "ratethread":
			$user['activity'] = "ratethread";
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
				$tidsql .= ",{$parameters['tid']}";
			}
			$user['activity'] = "sendthread";
			$user['tid'] = $parameters['tid'];
		case "showteam":
			$user['activity'] = "showteam";
			break;
		case "showthread":
			if(is_numeric($parameters['pid']) && $parameters['action'] == "showpost")
			{
				$pidsql .= ",{$parameters['pid']}";
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
					$tidsql .= ",{$parameters['tid']}";
				}
				$user['activity'] = "showthread";
				$user['tid'] = $parameters['tid'];
			}
			break;
		case "stats":
			$user['activity'] = "stats";
			break;
		case "usercp":
			if($parameters['action'] == "profile" || $parameters['action'] == "do_profile")
			{
				$user['activity'] = "usercp_profile";
			}
			elseif($parameters['action'] == "options" || $parameters['action'] == "do_options")
			{
				$user['activity'] = "usercp_options";
			}
			elseif($parameters['action'] == "password" || $parameters['action'] == "do_password")
			{
				$user['activity'] = "usercp_password";
			}
			elseif($parameters['action'] == "editsig" || $parameters['action'] == "do_editsig")
			{
				$user['activity'] = "usercp_editsig";
			}
			elseif($parameters['action'] == "avatar" || $parameters['action'] == "do_avatar")
			{
				$user['activity'] = "usercp_avatar";
			}
			elseif($parameters['action'] == "editlists" || $parameters['action'] == "do_editlists")
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
			elseif($parameters['action'] == "notepad" || $parameters['action'] == "do_notepad")
			{
				$user['activity'] = "usercp_notepad";
			}
			else
			{
				$user['activity'] = "usercp";
			}
			break;
		case "usercp2":
			if($parameters['action'] == "addfavorite" || $parameters['action'] == "removefavorite" || $parameters['action'] == "removefavorites")
			{
				$user['activity'] = "usercp2_favorites";
			}
			elseif($parameters['action'] == "addsubscription" || $parameters['action'] == "removesubscription" || $parameters['action'] == "removesubscription")
			{
				$user['activity'] = "usercp2_subscriptions";
			}
			break;
		case "portal":
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