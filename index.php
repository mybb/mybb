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

$templatelist = "index,index_whosonline,index_welcomemembertext,index_welcomeguest,index_whosonline_memberbit,forumbit_depth1_cat,forumbit_depth1_forum,forumbit_depth2_cat,forumbit_depth2_forum,forumbit_depth1_forum_lastpost,forumbit_depth2_forum_lastpost,index_modcolumn,forumbit_moderators,forumbit_subforums,index_welcomeguesttext";
$templatelist .= ",index_birthdays_birthday,index_birthdays,index_pms,index_loginform,index_logoutlink,index_stats,forumbit_depth3";

require "./global.php";

require MYBB_ROOT."inc/functions_post.php";
require MYBB_ROOT."inc/class_parser.php";
$parser = new postParser;

$plugins->run_hooks("index_start");

// Load global language phrases
$lang->load("index");

$logoutlink = $loginform = '';
if($mybb->user['uid'] != 0)
{
	eval("\$logoutlink = \"".$templates->get("index_logoutlink")."\";");
}
else
{
	//Checks to make sure the user can login; they haven't had too many tries at logging in.
	//Function call is not fatal
	if(login_attempt_check(false) !== false)
	{
		eval("\$loginform = \"".$templates->get("index_loginform")."\";");
	}
}
$whosonline = '';
if($mybb->settings['showwol'] != "no" && $mybb->usergroup['canviewonline'] != "no")
{
	// Get the online users.
	$timesearch = time() - $mybb->settings['wolcutoffmins']*60;
	$comma = '';
	$query = $db->query("
		SELECT s.sid, s.ip, s.uid, s.time, s.location, u.username, u.invisible, u.usergroup, u.displaygroup
		FROM ".TABLE_PREFIX."sessions s
		LEFT JOIN ".TABLE_PREFIX."users u ON (s.uid=u.uid)
		WHERE s.time>'$timesearch'
		ORDER BY u.username ASC, s.time DESC
	");
	$membercount = 0;
	$onlinemembers = '';
	$guestcount = 0;
	$anoncount = 0;
	$doneusers = array();

	// Loop through all users.
	while($user = $db->fetch_array($query))
	{
		// Create a key to test if this user is a search bot.
		$botkey = strtolower(str_replace("bot=", '', $user['sid']));

		// Decide what type of user we are dealing with.
		if($user['uid'] > 0)
		{
			// The user is registered.
			if($doneusers[$user['uid']] < $user['time'] || !$doneusers[$user['uid']])
			{
				// If the user is logged in anonymously, update the count for that.
				if($user['invisible'] == "yes")
				{
					++$anoncount;
				}
				++$membercount;
				if($user['invisible'] != "yes" || $mybb->usergroup['canviewwolinvis'] == "yes" || $user['uid'] == $mybb->user['uid'])
				{
					// If this usergroup can see anonymously logged-in users, mark them.
					if($user['invisible'] == "yes")
					{
						$invisiblemark = "*";
					}
					else
					{
						$invisiblemark = '';
					}

					// Properly format the username and assign the template.
					$user['username'] = formatname($user['username'], $user['usergroup'], $user['displaygroup']);
					eval("\$onlinemembers .= \"".$templates->get("index_whosonline_memberbit", 1, 0)."\";");
					$comma = ", ";
				}
				// This user has been handled.
				$doneusers[$user['uid']] = $user['time'];
			}
		}
		elseif(strstr($user['sid'], "bot=") !== false && $session->bots[$botkey])
		{
			// The user is a search bot.
			$onlinemembers .= $comma.formatname($session->bots[$botkey], $session->botgroup);
			$comma = ", ";
			++$botcount;
		}
		else
		{
			// The user is a guest.
			++$guestcount;
		}
	}

	// Build the who's online bit on the index page.
	$onlinecount = $membercount + $guestcount;
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
	$lang->online_note = sprintf($lang->online_note, mynumberformat($onlinecount), $onlinebit, $mybb->settings['wolcutoffmins'], mynumberformat($membercount), $memberbit, mynumberformat($anoncount), $anonbit, mynumberformat($guestcount), $guestbit);
	eval("\$whosonline = \"".$templates->get("index_whosonline")."\";");
}

// Build the birthdays for to show on the index page.
$bdays = $birthdays = '';
if($mybb->settings['showbirthdays'] != "no")
{
	// First, see what day this is.
	$bdaycount = 0;
	$bdaytime = time();
	$bdaydate = mydate("j-n", $bdaytime, '', 0);
	$year = mydate("Y", $bdaytime, '', 0);

	// Select all users who have their birthday today.
	$query = $db->simple_select(TABLE_PREFIX."users", "uid, username, birthday", "birthday LIKE '$bdaydate-%'");
	$comma = '';
	while($bdayuser = $db->fetch_array($query))
	{
		$bday = explode("-", $bdayuser['birthday']);
		if($year > $bday['2'] && $bday['2'] != '')
		{
			$age = " (".($year - $bday['2']).")";
		}
		else
		{
			$age = '';
		}
		eval("\$bdays .= \"".$templates->get("index_birthdays_birthday", 1, 0)."\";");
		++$bdaycount;
		$comma = ", ";
	}

	// If there are one or more birthdays, show them.
	if($bdaycount > 0)
	{
		eval("\$birthdays = \"".$templates->get("index_birthdays")."\";");
	}
}

// Build the forum statistics to show on the index page.
if($mybb->settings['showindexstats'] != "no")
{
	// First, load the stats cache.
	$stats = $cache->read("stats");

	// Check who's the newest member.
	if(!$stats['lastusername'])
	{
		$newestmember = "no-one";
	}
	else
	{
		$newestmember = "<a href=\"member.php?action=profile&amp;uid=".$stats['lastuid']."\">".$stats['lastusername']."</a>";
	}

	// Format the stats language.
	$lang->stats_posts_threads = sprintf($lang->stats_posts_threads, mynumberformat($stats['numposts']), mynumberformat($stats['numthreads']));
	$lang->stats_numusers = sprintf($lang->stats_numusers, mynumberformat($stats['numusers']));
	$lang->stats_newestuser = sprintf($lang->stats_newestuser, $newestmember);

	// Find out what the highest users online count is.
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

	// Then format that language string.
	$lang->stats_mostonline = sprintf($lang->stats_mostonline, mynumberformat($recordcount), $recorddate, $recordtime);

	eval("\$forumstats = \"".$templates->get("index_stats")."\";");
}

// Get the forums we will need to show.
$query = $db->query(
	"SELECT f.*, t.subject AS lastpostsubject
	FROM ".TABLE_PREFIX."forums f
	LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid = f.lastposttid)
	WHERE active!='no'
	ORDER BY f.pid, f.disporder
");
// Build a forum cache.
while($forum = $db->fetch_array($query))
{
	$fcache[$forum['pid']][$forum['disporder']][$forum['fid']] = $forum;

}
$forumpermissions = forum_permissions();

// Get the forum moderators if the setting is enabled.
if($mybb->settings['modlist'] != "off")
{
	$query = $db->query("
		SELECT m.uid, m.fid, u.username
		FROM ".TABLE_PREFIX."moderators m
		LEFT JOIN ".TABLE_PREFIX."users u ON (m.uid=u.uid)
		ORDER BY u.username
	");
	// Build a moderator cache.
	while($moderator = $db->fetch_array($query))
	{
		$moderatorcache[$moderator['fid']][] = $moderator;
	}
}

$excols = "index";
$permissioncache['-1'] = "1";
$bgcolor = "trow1";

// Decide if we're showing first-level subforums on the index page.
if($mybb->settings['subforumsindex'] != 0)
{
	$showdepth = 3;
}
else
{
	$showdepth = 2;
}
$forums = getforums();

/**
* Get a list of forums.
*
* @param unknown
* @param int The depth to return forums with.
* @param unknown
* @return string The filled-in forum listing template.
*/
function getforums($pid="0", $depth=1, $permissions="")
{
	global $fcache, $moderatorcache, $forumpermissions, $theme, $mybb, $mybbforumread, $settings, $mybbuser, $excols, $templates, $bgcolor, $collapsed, $lang, $showdepth, $forumpass, $plugins, $parser;
	$forumlisting = '';
	if(is_array($fcache[$pid]))
	{
		foreach($fcache[$pid] as $main)
		{
			foreach($main as $forum)
			{
				$forums = $subforums = '';
				$perms = $forumpermissions[$forum['fid']];
				if($perms['canview'] == "yes" || $mybb->settings['hideprivateforums'] == "no")
				{
					$plugins->run_hooks("index_forum");
					
					$forum_url = get_forum_link($forum['fid']);

					if($mybb->user['uid'] != 0)
					{
						$lastvisit = $mybb->user['lastvisit'];
					}
					else
					{
						$lastvisit = $_COOKIE['mybb']['lastvisit'];
					}
					$forumread = mygetarraycookie("forumread", $forum['fid']);
					if($forum['lastpost'] > $lastvisit && $forum['lastpost'] > $forumread && $forum['lastpost'] != 0)
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
					if($depth == 3)
					{
						$statusicon = '';
						if($mybb->settings['subforumsstatusicons'] == "yes")
						{
							$folder .= "mini";
							eval("\$statusicon = \"".$templates->get("forumbit_depth3_statusicon", 1, 0)."\";");
						}
						eval("\$forumlisting .= \"".$templates->get("forumbit_depth3", 1, 0)."\";");
						$comma = ", ";
						++$donecount;
						if($donecount == $mybb->settings['subforumsindex'])
						{
							if(count($main) > $donecount)
							{
								$forumlisting .= $comma;
								$forumlisting .= sprintf($lang->more_subforums, (count($main) - $donecount));
							}
							return $forumlisting;
						}
						continue;
					}
					if($forum['type'] == "c")
					{
						$forumcat = "_cat";
					}
					else
					{
						$forumcat = "_forum";
					}
					$hideinfo = 0;
					if($forum['type'] == "f" && $forum['linkto'] == '')
					{
						if($forum['password'] != '' && $_COOKIE['forumpass'][$forum['fid']] != md5($mybb->user['uid'].$forum['password']))
						{
							$hideinfo = 1;
						}
						elseif($forum['lastpost'] == 0 || $forum['lastposter'] == '')
						{
							$lastpost = "<span style=\"text-align: center;\">".$lang->lastpost_never."</span>";
						}
						else
						{
							$lastpostdate = mydate($mybb->settings['dateformat'], $forum['lastpost']);
							$lastposttime = mydate($mybb->settings['timeformat'], $forum['lastpost']);
							$forum['lastpostsubject'] = $parser->parse_badwords($forum['lastpostsubject']);
							$lastposter = $forum['lastposter'];
							$lastposttid = $forum['lastposttid'];
							$lastpostsubject = $fulllastpostsubject = $forum['lastpostsubject'];
							if(strlen($lastpostsubject) > 25)
							{
								$lastpostsubject = substr($lastpostsubject, 0, 25) . "...";
							}
							$lastpostsubject = htmlspecialchars_uni($lastpostsubject);
							$fulllastpostsubject = htmlspecialchars_uni($fulllastpostsubject);
							eval("\$lastpost = \"".$templates->get("forumbit_depth$depth$forumcat"."_lastpost")."\";");

						}
					}
					if($forum['linkto'] != '' || $hideinfo == 1)
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

					// Threads and posts requiring moderation
					$unapproved_threads = $unapproved_posts = '';
					if(ismod($forum['fid']) == "yes")
					{
						if($forum['unapprovedposts'])
						{
							if($forum['unapprovedposts'] > 1)
							{
								$unapproved_posts_count = sprintf($lang->forum_unapproved_posts_count, $forum['unapprovedposts']);
							}
							else
							{
								$unapproved_posts_count = sprintf($lang->forum_unapproved_post_count, 1);
							}
							$unapproved_posts = " <span title=\"{$unapproved_posts_count}\">(".mynumberformat($forum['unapprovedposts']).")</span>";
						}
						if($forum['unapprovedthreads'])
						{
							if($forum['unapprovedthread'] > 1)
							{
								$unapproved_threads_count = sprintf($lang->forum_unapproved_thread_count, $forum['unapprovedthreads']);
							}
							else
							{
								$unapproved_threads_count = sprintf($lang->forum_unapproved_thread_count, 1);
							}
							$unapproved_threads = " <span title=\"{$unapproved_threads_count}\">(".mynumberformat($forum['unapprovedthreads']).")</span>";
						}
					}

					if($mybb->settings['modlist'] != "off")
					{
						$moderators = '';
						$parentlistexploded = explode(",", $forum['parentlist']);
						while(list($key, $mfid) = each($parentlistexploded))
						{
							if($moderatorcache[$mfid])
							{
								reset($moderatorcache[$mfid]);
								while(list($key2, $moderator) = each($moderatorcache[$mfid]))
								{
									$moderators .= "$comma<a href=\"member.php?action=profile&amp;uid=$moderator[uid]\">".$moderator['username']."</a>";
									$comma = ", ";
								}
							}
						}
						$comma = '';
						if($moderators)
						{
							eval("\$modlist = \"".$templates->get("forumbit_moderators")."\";");
						}
						else
						{
							$modlist = '';
						}
					}
					if($mybb->settings['showdescriptions'] == "no")
					{
						$forum['description'] = '';
					}
					$expdisplay = '';
					$cname = "cat_".$forum['fid']."_c";
					if(isset($collapsed[$cname]) && $collapsed[$cname] == "display: show;")
					{
						$expcolimage = "collapse_collapsed.gif";
						$expdisplay = "display: none;";
						$expaltext = "[+]";
					}
					else
					{
						$expcolimage = "collapse.gif";
						$expaltext = "[-]";
					}
					if($bgcolor == "trow2")
					{
						$bgcolor = "trow1";
					}
					else
					{
						$bgcolor = "trow2";
					}

					if(isset($fcache[$forum['fid']]) && $depth < $showdepth)
					{
						$newdepth = $depth + 1;
						$forums = getforums($forum['fid'], $newdepth, $perms);
						if($depth == 2 && $forums)
						{
							eval("\$subforums = \"".$templates->get("forumbit_subforums")."\";");
							$forums = '';
						}
					}
					eval("\$forumlisting .= \"".$templates->get("forumbit_depth$depth$forumcat")."\";");
				}
			}
		}
	}
	return $forumlisting;
}

$plugins->run_hooks("index_end");

eval("\$index = \"".$templates->get("index")."\";");
outputpage($index);
?>