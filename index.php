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

$templatelist = "index,index_whosonline,index_welcomemembertext,index_welcomeguest,index_whosonline_memberbit,forumbit_depth1_cat,forumbit_depth1_forum,forumbit_depth2_cat,forumbit_depth2_forum,forumbit_depth1_forum_lastpost,forumbit_depth2_forum_lastpost,index_modcolumn,forumbit_moderators,index_welcomeguesttext"; 
$templatelist .= ",index_birthdays_birthday,index_birthdays,index_pms,index_loginform,index_logoutlink,index_stats,forumbit_depth3";

require "./global.php";
require "./inc/functions_post.php";

$plugins->run_hooks("index_start");

// Load global language phrases
$lang->load("index");

if($mybb->user['uid'] != 0)
{
	eval("\$logoutlink = \"".$templates->get("index_logoutlink")."\";");
}
else
{
	eval("\$loginform = \"".$templates->get("index_loginform")."\";");
}
if($mybb->settings['showwol'] != "no")
{
	// Get the online users
	$timesearch = time() - $mybb->settings['wolcutoffmins']*60;
	$comma = "";
	$query = $db->query("SELECT s.sid, s.ip, s.uid, s.time, s.location, u.username, u.invisible, u.usergroup, u.displaygroup FROM ".TABLE_PREFIX."sessions s LEFT JOIN ".TABLE_PREFIX."users u ON (s.uid=u.uid) WHERE s.time>'$timesearch' ORDER BY u.username ASC, s.time DESC");
	$membercount = 0;
	$guestcount = 0;
	$anoncount = 0;
	$doneusers = array();

	while($user = $db->fetch_array($query))
	{
		if($user['uid'] > 0)
		{
			if($doneusers[$user['uid']] < $user['time'] || !$doneusers[$user['uid']])
			{
				if($user['invisible'] == "yes")
				{
					$anoncount++;
				}
				else
				{
					$membercount++;
				}
				if($user['invisible'] != "yes" || $mybb->usergroup['canviewwolinvis'] == "yes" || $user['uid'] == $mybb->user['uid'])
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
					eval("\$onlinemembers .= \"".$templates->get("index_whosonline_memberbit", 1, 0)."\";");
					$comma = ", ";
				}
				$doneusers[$user['uid']] = $user['time'];
			}
		}
		elseif(strstr($user['sid'], "bot="))
		{
			$botkey = strtolower(str_replace("bot=", "", $user['sid']));
			$onlinemembers .= $comma.formatname($session->bots[$botkey], $botgroup);
			$comma = ", ";
			$botcount++;
		}
		else
		{
			$guestcount++;
		}
	}
	$onlinecount = $membercount + $guestcount + $anoncount;

	// Every 1-10 times clear the WOL table
	$hourdel = "48";
	if($rand == 5)
	{
		$hourdel = time()-($hourdel*60*60);
		$db->shutdown_query("DELETE FROM ".TABLE_PREFIX."sessions WHERE time<'$hourdel'");
	}
	$lang->online_note = sprintf($lang->online_note, mynumberformat($onlinecount), mynumberformat($membercount), mynumberformat($anoncount), mynumberformat($guestcount));
	eval("\$whosonline = \"".$templates->get("index_whosonline")."\";");
}
// Get birthdays
if($mybb->settings['showbirthdays'] != "no")
{
	$bdaycount = 0;
	$bdaytime = time();
	$bdaydate = mydate("d-n", $bdaytime, "", 0);
	$year = mydate("Y", $bdaytime, "", 0);
	$query = $db->query("SELECT uid, username, birthday FROM ".TABLE_PREFIX."users WHERE birthday LIKE '$bdaydate-%'");
	while($bdayuser = $db->fetch_array($query))
	{
		$bday = explode("-", $bdayuser['birthday']);
		if($year > $bday['2'] && $bday['2'] != "")
		{
			$age = " (".($year - $bday['2']).")";
		}
		else
		{
			$age = "";
		}
		eval("\$bdays .= \"".$templates->get("index_birthdays_birthday")."\";");
		$bdaycount++;
	}
	if($bdaycount > 0)
	{
		eval("\$birthdays = \"".$templates->get("index_birthdays")."\";");			
	}
}
// Get Forum Statistics
if($mybb->settings['showindexstats'] != "no")
{
	$stats = $cache->read("stats");
	if(!$stats['lastusername'])
	{
		$newestmember = "no-one";
	}
	else
	{
		$newestmember = "<a href=\"member.php?action=profile&amp;uid=".$stats['lastuid']."\">".$stats['lastusername']."</a>";
	}
	$lang->stats_posts_threads = sprintf($lang->stats_posts_threads, mynumberformat($stats['numposts']), mynumberformat($stats['numthreads']));
	$lang->stats_numusers = sprintf($lang->stats_numusers, mynumberformat($stats['numusers']));
	$lang->stats_newestuser = sprintf($lang->stats_newestuser, $newestmember);
	
	// Most users online
	$mostonline = $cache->read("mostonline");
	if($onlinecount > $mostonline['numusers'])
	{
		$time = time();
		$mostonline['numusers'] = $onlinecount;
		$mostonline['time'] = $time;
		$cache->update("mostonline", $mostonline);
	}
	$recordcount = $mostonline['numusers'];
	$recorddate = mydate($mybb->settings['dateformat'], $mostonline['time']);
	$recordtime = mydate($mybb->settings['timeformat'], $mostonline['time']);
	
	$lang->stats_mostonline = sprintf($lang->stats_mostonline, mynumberformat($recordcount), $recorddate, $recordtime);
	
	eval("\$forumstats = \"".$templates->get("index_stats")."\";");
}
// Get Forums
$query = $db->query("SELECT f.*, t.subject AS lastpostsubject FROM ".TABLE_PREFIX."forums f LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid = f.lastposttid) WHERE active!='no' ORDER BY f.pid, f.disporder");
while($forum = $db->fetch_array($query))
{
	$fcache[$forum['pid']][$forum['disporder']][$forum['fid']] = $forum;

}
$forumpermissions = forum_permissions();
// Get forum moderators
if($mybb->settings['modlist'] != "off")
{
	$query = $db->query("SELECT m.uid, m.fid, u.username FROM ".TABLE_PREFIX."moderators m LEFT JOIN ".TABLE_PREFIX."users u ON (m.uid=u.uid) ORDER BY u.username");
	while($moderator = $db->fetch_array($query))
	{
		$moderatorcache[$moderator['fid']][] = $moderator;
	}
}

// Expand (or Collapse) forums
if($mybb->input['action'] == "expand")
{
	mysetcookie("fcollapse[$fid]", "");
	$fcollapse[$fid] = "";
}
elseif($mybb->input['action'] == "collapse")
{
	mysetcookie("fcollapse[$fid]", "y");
	$fcollapse[$fid] = "y";
}
$excols = "index";
$permissioncache['-1'] = "1";
$bgcolor = "trow1";
if($mybb->settings['subforumsindex'] != 0)
{
	$showdepth = 3;
}
else
{
	$showdepth =2;
}
$forums = getforums();

function getforums($pid="0", $depth=1, $permissions="")
{
	global $fcache, $moderatorcache, $forumpermissions, $theme, $mybb, $mybbforumread, $settings, $mybbuser, $excols, $fcollapse, $templates, $bgcolor, $collapsed, $lang, $showdepth, $forumpass, $plugins;
	if(is_array($fcache[$pid]))
	{
		while(list($key, $main) = each($fcache[$pid]))
		{
			while(list($key, $forum) = each($main))
			{
				$perms = $forumpermissions[$forum['fid']];
				if($perms['canview'] == "yes" || $mybb->settings['hideprivateforums'] == "no")
				{
					$plugins->run_hooks("index_forum");

					if($depth == 3)
					{
						eval("\$forumlisting .= \"".$templates->get("forumbit_depth3", 1, 0)."\";");
						$comma = ", ";
						$donecount++;
						if($donecount == $mybb->settings['subforumsindex'])
						{
							return $forumlisting;
						}
						continue;
					}
					$forumread = mygetarraycookie("forumread", $forum['fid']);
					if($forum['lastpost'] > $mybb->user['lastvisit'] && $forum['lastpost'] > $forumread && $forum['lastpost'] != 0)
					{
						$folder = "on";
						$altonoff = $lang->new_posts;
					}
					else
					{
						$folder = "off";
						$altonoff = $lang->no_new_posts;
					}
					if($forum['open'] == "no")
					{
						$folder = "offlock";
						$altonoff = $lang->forum_locked;
					}
					$forumread = 0;
					if($forum['type'] == "c")
					{
						$forumcat = "_cat";
					}
					else
					{
						$forumcat = "_forum";
					}
					$hideinfo = 0;
					if($forum['type'] == "f" && $forum['linkto'] == "")
					{
						if($forum['lastpost'] == 0 || $forum['lastposter'] == "")
						{
							$lastpost = "<span style=\"text-align: center;\">".$lang->lastpost_never."</span>";
						}
						elseif($forum['password'] != "" && $forumpass[$forum['fid']] != md5($mybb->user['uid'].$forum['password']))
						{
							$hideinfo = 1;
						}
						else
						{
							$lastpostdate = mydate($mybb->settings['dateformat'], $forum['lastpost']);
							$lastposttime = mydate($mybb->settings['timeformat'], $forum['lastpost']);
							$lastposter = $forum['lastposter'];
							$lastposttid = $forum['lastposttid'];
							$lastpostsubject = $fulllastpostsubject = $forum['lastpostsubject'];
							$lastpostsubject = dobadwords($lastpostsubject);
							if(strlen($lastpostsubject) > 25)
							{
								$lastpostsubject = substr($lastpostsubject, 0, 25) . "...";
							}
							$lastpostsubject = htmlspecialchars_uni(dobadwords($lastpostsubject));
							eval("\$lastpost = \"".$templates->get("forumbit_depth$depth$forumcat"."_lastpost")."\";");
						}
					}
					if($forum['linkto'] != "" || $hideinfo == 1)
					{
						$lastpost = "<center>-</center>";
						$posts = "-";
						$threads = "-";
					}
					else
					{
						$posts = mynumberformat($forum['posts']);
						$threads = mynumberformat($forum['threads']);
					}
					if($mybb->settings['modlist'] != "off")
					{
						$moderators = "";
						$parentlistexploded = explode(",", $forum['parentlist']);
						while(list($key, $mfid) = each($parentlistexploded))
						{
							if($moderatorcache[$mfid])
							{
								reset($moderatorcache[$mfid]);
								while(list($key2, $moderator) = each($moderatorcache[$mfid]))
								{
									$moderators .= "$comma<a href=\"member.php?action=profile&uid=$moderator[uid]\">".$moderator['username']."</a>";
									$comma = ", ";
								}
							}
						}
						$comma = "";
						if($moderators)
						{
							eval("\$modlist = \"".$templates->get("forumbit_moderators")."\";");
						}
						else
						{
							$modlist = "";
						}
					}
					if($mybb->settings['showdescriptions'] == "no")
					{
						$forum['description'] = "";
					}
					$expdisplay = "";
					$cname = "cat_".$forum['fid']."_c";
					if($collapsed[$cname] == "display: show;")
					{
						$expcolimage = "collapse_collapsed.gif";
						$expdisplay = "display: none;";
					}
					else
					{
						$expcolimage = "collapse.gif";
					}
					if($bgcolor == "trow2")
					{
						$bgcolor = "trow1";
					}
					else
					{
						$bgcolor = "trow2";
					}

					if($fcache[$forum['fid']] && $depth < $showdepth)
					{
						$newdepth = $depth + 1;
						$forums = getforums($forum['fid'], $newdepth, $perms);
						if($depth == 2 && $forums)
						{
							$subforums = "<br />".$lang->subforums." ".$forums;
						}
					}
					eval("\$forumlisting .= \"".$templates->get("forumbit_depth$depth$forumcat")."\";");
				}
				$forums = $subforums = "";
			}
		}
	}
	return $forumlisting;
}

$plugins->run_hooks("index_end");

eval("\$index = \"".$templates->get("index")."\";");
outputpage($index);
?>