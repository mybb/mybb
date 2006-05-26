<?php
/**
* Build a list of forum bits.
*
* @param int The parent forum to fetch the child forums for (0 assumes all)
* @param int The depth to return forums with.
* @return array Array of information regarding the child forums of this parent forum
*/

function build_forumbits($pid=0, $depth=1)
{
	global $fcache, $moderatorcache, $forumpermissions, $theme, $mybb, $templates, $bgcolor, $collapsed, $lang, $showdepth, $plugins, $parser;
	$forum_listing = '';
	if(!is_array($fcache[$pid]))
	{
		return;
	}

	foreach($fcache[$pid] as $parent)
	{
		foreach($parent as $forum)
		{
			$forums = $subforums = $sub_forums = '';
			$lastpost_data ='';
			$counters = '';
			$permissions = $forumpermissions[$forum['fid']];
			
			if($permissions['canview'] != "yes" && $mybb->settings['hideprivateforums'] == "yes")
			{
				continue;
			}
		
			$plugins->run_hooks("build_forumbits_forum");
			
			$forum_url = get_forum_link($forum['fid']);
		
			$lastpost_data = array(
				"lastpost" => $forum['lastpost'],
				"lastpostsubject" => $forum['lastpostsubject'],
				"lastposter" => $forum['lastposter'],
				"lastposttid" => $forum['lastposttid'],
				"lastposteruid" => $forum['lastposteruid']
			);
			
			// Fetch subforums of this forum
			if(isset($fcache[$forum['fid']]))
			{
				$forum_info = build_forumbits($forum['fid'], $depth+1);
				$forum['threads'] += $forum_info['counters']['threads'];
				$forum['posts'] += $forum_info['counters']['posts'];
				$forum['unapprovedthreads'] += $forum_info['counters']['unapprovedthreads'];
				$forum['unapprovedposts'] += $forum_info['counters']['unapprovedposts'];
				if($forum_info['lastpost']['lastpost'] > $lastpost_data['lastpost'])
				{
					$lastpost_data = $forum_info['lastpost'];
				}
				
				$sub_forums = $forum_info['forum_list'];
			}
			if($lastpost_data['lastpost'] > $parent_lastpost['lastpost'])
			{
				$parent_lastpost = $lastpost_data;
			}
			$parent_counters['threads'] += $forum['threads'];
			$parent_counters['posts'] += $forum['posts'];
			$parent_counters['unapprovedposts'] += $forum['unapprovedposts'];
			$parent_counters['unapprovedthreads'] += $forum['unapprovedthreads'];
			
			if($depth > $showdepth)
			{
				continue;
			}
	
			$lightbulb = get_forum_lightbulb($forum, $lastpost_data);
			
			$unapproved = get_forum_unapproved($forum);
	
			if($depth == 2 && $sub_forums)
			{
				eval("\$subforums = \"".$templates->get("forumbit_subforums")."\";");
			}
			else if($depth == 3)
			{
				$statusicon = '';
				if($mybb->settings['subforumsstatusicons'] == "yes")
				{
					$lightbulb['folder'] .= "mini";
					eval("\$statusicon = \"".$templates->get("forumbit_depth3_statusicon", 1, 0)."\";");
				}
				eval("\$forum_list .= \"".$templates->get("forumbit_depth3", 1, 0)."\";");
				$comma = ", ";
				++$donecount;
				if($donecount == $mybb->settings['subforumsindex'])
				{
					if(count($main) > $donecount)
					{
						$forum_list .= $comma;
						$forum_list .= sprintf($lang->more_subforums, (count($main) - $donecount));
					}
					break;
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
				else if($forum['lastpost'] == 0 || $forum['lastposter'] == '')
				{
					$lastpost = "<span style=\"text-align: center;\">".$lang->lastpost_never."</span>";
				}
				else
				{
					$lastpost_date = mydate($mybb->settings['dateformat'], $lastpost_data['lastpost']);
					$lastpost_time = mydate($mybb->settings['timeformat'], $lastpost_data['lastpost']);
					
					$lastposter = $lastpost_data['lastposter'];
					$lastposttid = $lastpost_data['lastposttid'];
					$lastpost_subject = $full_lastpost_subject = $parser->parse_badwords($lastpost_data['lastpostsubject']);
					if(strlen($lastpost_subject) > 25)
					{
						$lastpost_subject = substr($lastpost_subject, 0, 25) . "...";
					}
					$lastpost_subject = htmlspecialchars_uni($lastpost_subject);
					$full_lastpost_subject = htmlspecialchars_uni($full_lastpost_subject);
					
					eval("\$lastpost = \"".$templates->get("forumbit_depth$depth$forumcat"."_lastpost")."\";");

				}
			}
			if($forum['linkto'] != '' || $hideinfo == 1)
			{
				$lastpost = "<span style=\"text-align: center;\">-</span>";
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
				$moderators = '';
				$parentlistexploded = explode(",", $forum['parentlist']);
				foreach($parentlistexploded as $mfid)
				{
					if(is_array($moderatorcache[$mfid]))
					{
						foreach($moderatorcache[$mfid] as $moderator)
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
			$collapsed_name = "cat_".$forum['fid']."_c";
			if(isset($collapsed[$collapsed_name]) && $collapsed[$collapsed_name] == "display: show;")
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
		
			eval("\$forum_list .= \"".$templates->get("forumbit_depth$depth$forumcat")."\";");
		}
	}
	return array(
		"forum_list" => $forum_list,
		"counters" => $parent_counters,
		"lastpost" => $parent_lastpost
	);
}

function get_forum_lightbulb($forum, $lastpost)
{
	global $mybb;
	
	$forumread = mygetarraycookie("forumread", $forum['fid']);
	if($lastpost['lastpost'] > $mybb->user['lastvisit'] && $lastpost['lastpost'] > $forumread && $lastpost['lastpost'] != 0)
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
	
	return array(
		"folder" => $folder,
		"altonoff" => $altonoff
	);
}

function get_forum_unapproved($forum)
{
	global $lang;
	
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
	return array(
		"unapproved_posts" => $unapproved_posts,
		"unapproved_threads" => $unapproved_threads
	);
}
?>