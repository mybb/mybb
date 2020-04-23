<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

/**
* Build a list of forum bits.
*
* @param int $pid The parent forum to fetch the child forums for (0 assumes all)
* @param int $depth The depth to return forums with.
* @return array Array of information regarding the child forums of this parent forum
*/
function build_forumbits($pid=0, $depth=1)
{
	global $db, $fcache, $moderatorcache, $forumpermissions, $theme, $mybb, $templates, $bgcolor, $collapsed, $lang, $showdepth, $plugins, $parser, $forum_viewers;
	static $private_forums;

	$forum_listing = '';

	// If no forums exist with this parent, do nothing
	if(empty($fcache[$pid]) || !is_array($fcache[$pid]))
	{
		return;
	}

	$parent_counters['threads'] = 0;
	$parent_counters['posts'] = 0;
	$parent_counters['unapprovedposts'] = 0;
	$parent_counters['unapprovedthreads'] = 0;
	$parent_counters['viewers'] = 0;
	$forum_list = $comma = '';
	$donecount = 0;
	$more_count = 0;

	// Foreach of the forums in this parent
	foreach($fcache[$pid] as $parent)
	{
		foreach($parent as $forum)
		{
			$subforums = $sub_forums = '';
			$forum['last_post'] = array(
				'lastpost' => 0
			);

			// Get the permissions for this forum
			$permissions = $forumpermissions[$forum['fid']];

			// If this user doesnt have permission to view this forum and we're hiding private forums, skip this forum
			if($permissions['canview'] != 1 && $mybb->settings['hideprivateforums'] == 1)
			{
				continue;
			}

			$forum = $plugins->run_hooks("build_forumbits_forum", $forum);

			// Build the link to this forum
			$forum['url'] = get_forum_link($forum['fid']);

			// This forum has a password, and the user isn't authenticated with it - hide post information
			$hideinfo = $hidecounters = false;
			$hidelastpostinfo = false;
			$showlockicon = 0;
			if(isset($permissions['canviewthreads']) && $permissions['canviewthreads'] != 1)
			{
				$hideinfo = true;
			}

			if(isset($permissions['canonlyviewownthreads']) && $permissions['canonlyviewownthreads'] == 1)
			{
				$hidecounters = true;

				// If we only see our own threads, find out if there's a new post in one of them so the lightbulb shows
				if(!is_array($private_forums))
				{
					$private_forums = $fids = array();
					foreach($fcache as $fcache_p)
					{
						foreach($fcache_p as $parent_p)
						{
							foreach($parent_p as $forum_p)
							{
								if($forumpermissions[$forum_p['fid']]['canonlyviewownthreads'])
								{
									$fids[] = $forum_p['fid'];
								}
							}
						}
					}

					if(!empty($fids))
					{
						$fids = implode(',', $fids);

						$prefix = TABLE_PREFIX;
						$query = <<<SQL
SELECT tid, fid, subject, lastpost, lastposter, lastposteruid, avatar
  FROM {$prefix}threads LEFT JOIN {$prefix}users ON ({$prefix}threads.lastposteruid = {$prefix}users.uid
  WHERE {$prefix}threads.uid = '{$mybb->user['uid']}' AND fid IN ({$fids}) AND visible != '-2'
  ORDER BY lastpost DESC;
SQL;

						$query = $db->query($query);

						while($thread = $db->fetch_array($query))
						{
							if(!$private_forums[$thread['fid']])
							{
								$private_forums[$thread['fid']] = $thread;
							}
						}
					}
				}

				if($private_forums[$forum['fid']]['lastpost'])
				{
					$forum['lastpost'] = $private_forums[$forum['fid']]['lastpost'];

					if(!$private_forums[$forum['fid']]['lastposteruid'] && !$private_forums[$forum['fid']]['lastposter'])
					{
						$private_forums[$forum['fid']]['lastposter'] = $lang->guest; // htmlspecialchars_uni'd when formatted later
					}

					$forum['last_post'] = array(
						"lastpost" => $private_forums[$forum['fid']]['lastpost'],
						"lastpostsubject" => $private_forums[$forum['fid']]['subject'],
						"lastposter" => $private_forums[$forum['fid']]['lastposter'],
						"lastposttid" => $private_forums[$forum['fid']]['tid'],
						"lastposteruid" => $private_forums[$forum['fid']]['lastposteruid']
					);
				}
			}
			else
			{
				if(!$forum['lastposteruid'] && !$forum['lastposter'])
				{
					$forum['lastposter'] = $lang->guest; // htmlspecialchars_uni'd when formatted later
				}

				$forum['last_post'] = array(
					"lastpost" => $forum['lastpost'],
					"lastpostsubject" => $forum['lastpostsubject'],
					"lastposter" => $forum['lastposter'],
					"lastposttid" => $forum['lastposttid'],
					"lastposteruid" => $forum['lastposteruid']
				);
			}

			if(!forum_password_validated($forum, true))
			{
				$hideinfo = true;
				$showlockicon = 1;
			}

			// Fetch subforums of this forum
			if(isset($fcache[$forum['fid']]))
			{
				$forum_info = build_forumbits($forum['fid'], $depth + 1);

				// Increment forum counters with counters from child forums
				$forum['threads'] += $forum_info['counters']['threads'];
				$forum['posts'] += $forum_info['counters']['posts'];
				$forum['unapprovedthreads'] += $forum_info['counters']['unapprovedthreads'];
				$forum['unapprovedposts'] += $forum_info['counters']['unapprovedposts'];

				if(!empty($forum_info['counters']['viewing']))
				{
					$forum['viewers'] += $forum_info['counters']['viewing'];
				}

				// If the child forums' lastpost is greater than the one for this forum, set it as the child forums greatest.
				if($forum_info['lastpost']['lastpost'] > $forum['last_post']['lastpost'])
				{
					$forum['last_post'] = $forum_info['lastpost'];

					/*
					// If our subforum is unread, then so must be our parents. Force our parents to unread as well
					if(strstr($forum_info['lightbulb']['folder'], "on") !== false)
					{
						$forum['lastread'] = 0;
					}
					// Otherwise, if we  have an explicit record in the db, we must make sure that it is explicitly set
					else
					{
						$forum['last_post']['lastpost'] = $forum['lastpost'];
					}*/
				}

				$sub_forums = $forum_info['forum_list'];
			}

			// If we are hiding information (lastpost) because we aren't authenticated against the password for this forum, remove them
			if($hidelastpostinfo == true)
			{
				$forum['last_post'] = array(
					'lastpost' => 0,
					'lastposter' => ''
				);
			}

			// If the current forums lastpost is greater than other child forums of the current parent and forum info isn't hidden, overwrite it
			if((!isset($parent_lastpost) || $forum['last_post']['lastpost'] > $parent_lastpost['lastpost']) && $hideinfo != true)
			{
				$parent_lastpost = $forum['last_post'];
			}

			if(is_array($forum_viewers) && isset($forum_viewers[$forum['fid']]) && $forum_viewers[$forum['fid']] > 0)
			{
				$forum['viewers'] = $forum_viewers[$forum['fid']];
			}

			// Increment the counters for the parent forum (returned later)
			if($hideinfo != true && $hidecounters != true)
			{
				$parent_counters['threads'] += $forum['threads'];
				$parent_counters['posts'] += $forum['posts'];
				$parent_counters['unapprovedposts'] += $forum['unapprovedposts'];
				$parent_counters['unapprovedthreads'] += $forum['unapprovedthreads'];

				if(!empty($forum['viewers']))
				{
					$parent_counters['viewers'] += $forum['viewers'];
				}
			}

			// Done with our math, lets talk about displaying - only display forums which are under a certain depth
			if($depth > $showdepth)
			{
				continue;
			}

			// Get the lightbulb status indicator for this forum based on the lastpost
			$lightbulb = get_forum_lightbulb($forum, $forum['last_post'], $showlockicon);

			if($hideinfo != true && is_moderator($forum['fid'], "canviewunapprove"))
			{
				$forum['showunapproved'] = true;
			}

			// Sanitize name and description of forum.
			$forum['name'] = preg_replace("#&(?!\#[0-9]+;)#si", "&amp;", $forum['name']); // Fix & but allow unicode
			$forum['description'] = preg_replace("#&(?!\#[0-9]+;)#si", "&amp;", $forum['description']); // Fix & but allow unicode
			$forum['name'] = preg_replace("#&([^\#])(?![a-z1-4]{1,10};)#i", "&#038;$1", $forum['name']);
			$forum['description'] = preg_replace("#&([^\#])(?![a-z1-4]{1,10};)#i", "&#038;$1", $forum['description']);

			// If this is a forum and we've got subforums of it, load the subforums list template
			if($depth == 2 && $sub_forums)
			{
				$subforums = \MyBB\template('forumbit/subforums.twig', [
					'sub_forums' => $sub_forums
				]);
			}
			// A depth of three indicates a comma separated list of forums within a forum
			else if($depth == 3)
			{
				if($donecount < $mybb->settings['subforumsindex'])
				{
					// Fetch the template and append it to the list
					$forum_list .= \MyBB\template('forumbit/depth3.twig', [
						'lightbulb' => $lightbulb,
						'forum' => $forum,
						'comma' => $comma
					]);
					$comma = $lang->comma;
				}

				// Have we reached our max visible subforums? put a nice message and break out of the loop
				++$donecount;
				if($donecount == $mybb->settings['subforumsindex'])
				{
					if(subforums_count($fcache[$pid]) > $donecount)
					{
						$more_count = subforums_count($fcache[$pid]) - $donecount;
						$forum_list .= \MyBB\template('forumbit/subforums_more.twig', [
							'more_count' => $more_count
						]);
					}
				}
				continue;
			}

			// Set template type basing on the category/forum type
			$forumcat = ($forum['type'] == 'c') ? 'cat' : 'forum';

			if($forum['linkto'] == '')
			{
				// No posts have been made in this forum - show never text
				if($forum['last_post']['lastpost'] == 0 && $hideinfo != true)
				{
					$lastpost = \MyBB\template('forumbit/depth_2/last_post_never.twig');
				}
				elseif($hideinfo != true)
				{
					// Format lastpost date and time
					$forum['last_post']['date'] = my_date('relative', $forum['last_post']['lastpost']);

					// Set up the last poster, last post thread id, last post subject and format appropriately
					$forum['last_post']['profile_link'] = build_profile_link($forum['last_post']['lastposter'], $forum['last_post']['lastposteruid']);
					$forum['last_post']['link'] = get_thread_link($forum['last_post']['lastposttid'], 0, "lastpost");
					$forum['last_post']['subject'] = $forum['last_post']['full_subject'] = $parser->parse_badwords($forum['last_post']['lastpostsubject']);
					if(my_strlen($forum['last_post']['subject']) > 25)
					{
						$forum['last_post']['subject'] = my_substr($forum['last_post']['subject'], 0, 25)."...";
					}

					// Last poster avatar
					$forum['last_post']['last_poster_avatar_url'] = $forum['avatar'];

					// Call lastpost template
					if($depth != 1)
					{
						$lastpost = \MyBB\template('forumbit/depth_'.$depth.'/last_post.twig', [
							'forum' => $forum
						]);
					}
				}
			}
			// If this forum is a link or is password protected and the user isn't authenticated, set counters to "-"
			if($forum['linkto'] != '' || $hideinfo == true || $hidecounters == true)
			{
				$forum['fposts'] = "-";
				$forum['fthreads'] = "-";
			}
			// Otherwise, format thread and post counts
			else
			{
				$forum['fposts'] = my_number_format($forum['posts']);
				$forum['fthreads'] = my_number_format($forum['threads']);
			}

			// If this forum is a link or is password protected and the user isn't authenticated, set lastpost to "-"
			if($forum['linkto'] != '' || $hideinfo == true || $hidelastpostinfo == true)
			{
				$lastpost = \MyBB\template('forumbit/depth_2/last_post_hidden.twig');
			}

			// Moderator column is not off
			if($mybb->settings['modlist'] != 0)
			{
				$done_moderators = [
					"users" => [],
					"groups" => []
				];
				$moderators = [];
				// Fetch list of moderators from this forum and its parents
				$parentlistexploded = explode(',', $forum['parentlist']);
				foreach($parentlistexploded as $mfid)
				{
					// This forum has moderators
					if(isset($moderatorcache[$mfid]) && is_array($moderatorcache[$mfid]))
					{
						// Fetch each moderator from the cache and format it, appending it to the list
						foreach($moderatorcache[$mfid] as $modtype)
						{
							foreach($modtype as $moderator)
							{
								if($moderator['isgroup'])
								{
									if(in_array($moderator['id'], $done_moderators['groups']))
									{
										continue;
									}
									$moderator['type'] = 'group';
									$moderators[] = $moderator;
									$done_moderators['groups'][] = $moderator['id'];
								}
								else
								{
									if(in_array($moderator['id'], $done_moderators['users']))
									{
										continue;
									}

									$moderator['profilelink'] = get_profile_link($moderator['id']);
									$moderator['type'] = 'user';
									$moderators[] = $moderator;
									$done_moderators['users'][] = $moderator['id'];
								}
							}
						}
					}
				}
			}

			// Descriptions aren't being shown - blank them
			if($mybb->settings['showdescriptions'] == 0)
			{
				$forum['description'] = '';
			}

			// Add the forum to the list
			$forum_list .= \MyBB\template('forumbit/depth_'.$depth.'/'.$forumcat.'.twig', [
				'forum' => $forum,
				'collapsed' => $collapsed,
				'modlist' => $modlist,
				'sub_forums' => $sub_forums,
				'lightbulb' => $lightbulb,
				'lastpost' => $lastpost,
				'moderators' => $moderators,
				'unapproved' => $unapproved
			]);
		}
	}

	if(!isset($parent_lastpost))
	{
		$parent_lastpost = 0;
	}

	if(!isset($lightbulb))
	{
		$lightbulb = '';
	}

	// Return an array of information to the parent forum including child forums list, counters and lastpost information
	return array(
		"forum_list" => $forum_list,
		"counters" => $parent_counters,
		"lastpost" => $parent_lastpost,
		"lightbulb" => $lightbulb,
	);
}

/**
 * Fetch the status indicator for a forum based on its last post and the read date
 *
 * @param array $forum Array of information about the forum
 * @param array $lastpost Array of information about the lastpost date
 * @param int $locked Whether or not this forum is locked or not
 * @return array Array of the folder image to be shown and the alt text
 */
function get_forum_lightbulb($forum, $lastpost, $locked=0)
{
	global $mybb, $lang, $db, $unread_forums;

	// This forum is a redirect, so override the folder icon with the "offlink" icon.
	if($forum['linkto'] != '')
	{
		$folder = "offlink";
		$altonoff = $lang->forum_redirect;
	}
	// This forum is closed, so override the folder icon with the "offclose" icon.
	elseif($forum['open'] == 0 || $locked)
	{
		$folder = "offclose";
		$altonoff = $lang->forum_closed;
	}
	else
	{
		// Fetch the last read date for this forum
		if(!empty($forum['lastread']))
		{
			$forum_read = $forum['lastread'];
		}
		elseif(!empty($mybb->cookies['mybb']['readallforums']))
		{
			// We've hit the read all forums as a guest, so use the lastvisit of the user
			$forum_read = $mybb->cookies['mybb']['lastvisit'];
		}
		else
		{
			$forum_read = 0;
			$threadcut = TIME_NOW - 60*60*24*$mybb->settings['threadreadcut'];

			// If the user is a guest, do they have a forumsread cookie?
			if(!$mybb->user['uid'] && isset($mybb->cookies['mybb']['forumread']))
			{
				// If they've visited us before, then they'll have this cookie - otherwise everything is unread...
				$forum_read = my_get_array_cookie("forumread", $forum['fid']);
			}
			else if($mybb->user['uid'] && $mybb->settings['threadreadcut'] > 0 && $threadcut > $lastpost['lastpost'])
			{
				// We have a user, the forum's unread and we're over our threadreadcut limit for the lastpost - we mark these as read
				$forum_read = $lastpost['lastpost'] + 1;
			}
		}

		//if(!$forum_read)
		//{
		//$forum_read = $mybb->user['lastvisit'];
		//}

		// If the lastpost is greater than the last visit and is greater than the forum read date, we have a new post
		if($lastpost['lastpost'] > $forum_read && $lastpost['lastpost'] != 0)
		{
			$unread_forums++;
			$folder = "on";
			$altonoff = $lang->new_posts;
		}
		// Otherwise, no new posts
		else
		{
			$folder = "off";
			$altonoff = $lang->no_new_posts;
		}
	}

	return array(
		"folder" => $folder,
		"altonoff" => $altonoff
	);
}

/**
 * Fetch the number of unapproved posts, formatted, from a forum
 *
 * @deprecated
 * @param array $forum Array of information about the forum
 * @return array Array containing formatted string for posts and string for threads
 */
function get_forum_unapproved($forum)
{
	global $lang, $templates;

	$unapproved_threads = $unapproved_posts = '';

	// If the user is a moderator we need to fetch the count
	if(is_moderator($forum['fid'], "canviewunapprove"))
	{
		// Forum has one or more unaproved posts, format language string accordingly
		if($forum['unapprovedposts'])
		{
			if($forum['unapprovedposts'] > 1)
			{
				$unapproved_posts_count = $lang->sprintf($lang->forum_unapproved_posts_count, $forum['unapprovedposts']);
			}
			else
			{
				$unapproved_posts_count = $lang->sprintf($lang->forum_unapproved_post_count, 1);
			}

			$forum['unapprovedposts'] = my_number_format($forum['unapprovedposts']);
			eval("\$unapproved_posts = \"".$templates->get("forumbit_depth2_forum_unapproved_posts")."\";");
		}
		// Forum has one or more unapproved threads, format language string accordingly
		if($forum['unapprovedthreads'])
		{
			if($forum['unapprovedthreads'] > 1)
			{
				$unapproved_threads_count = $lang->sprintf($lang->forum_unapproved_threads_count, $forum['unapprovedthreads']);
			}
			else
			{
				$unapproved_threads_count = $lang->sprintf($lang->forum_unapproved_thread_count, 1);
			}

			$forum['unapprovedthreads'] = my_number_format($forum['unapprovedthreads']);
			eval("\$unapproved_threads = \"".$templates->get("forumbit_depth2_forum_unapproved_threads")."\";");
		}
	}
	return array(
		"unapproved_posts" => $unapproved_posts,
		"unapproved_threads" => $unapproved_threads
	);
}
