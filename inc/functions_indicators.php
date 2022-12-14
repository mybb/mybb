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
 * Mark a particular thread as read for the current user.
 *
 * @param int $tid The thread ID
 * @param int $fid The forum ID of the thread
 */
function mark_thread_read($tid, $fid)
{
	global $mybb, $db;

	// Can only do "true" tracking for registered users
	if($mybb->settings['threadreadcut'] > 0 && $mybb->user['uid'])
	{
		// For registered users, store the information in the database.
		switch($db->type)
		{
			case "pgsql":
			case "sqlite":
				$db->replace_query("threadsread", array('tid' => $tid, 'uid' => $mybb->user['uid'], 'dateline' => TIME_NOW), array("tid", "uid"));
				break;
			default:
				$db->write_query("
					REPLACE INTO ".TABLE_PREFIX."threadsread (tid, uid, dateline)
					VALUES('$tid', '{$mybb->user['uid']}', '".TIME_NOW."')
				");
		}
	}
	// Default back to cookie marking
	else
	{
		my_set_array_cookie("threadread", $tid, TIME_NOW, -1);
	}

	$unread_count = fetch_unread_count($fid);
	if($unread_count == 0)
	{
		mark_forum_read($fid);
	}
}

/**
 * Fetches the number of unread threads for the current user in a particular forum.
 *
 * @param string $fid The forums (CSV list)
 * @return int The number of unread threads
 */
function fetch_unread_count($fid)
{
	global $cache, $db, $mybb;

	$forums_all = $forums_own = array();
	$forums = explode(',', $fid);
	foreach($forums as $forum)
	{
		$permissions = forum_permissions($forum);
		if(!empty($permissions['canonlyviewownthreads']))
		{
			$forums_own[] = $forum;
		}
		else
		{
			$forums_all[] = $forum;
		}
	}
	if(!empty($forums_own))
	{
		$where = "(fid IN (".implode(',', $forums_own).") AND uid = {$mybb->user['uid']})";
		$where2 = "(t.fid IN (".implode(',', $forums_own).") AND t.uid = {$mybb->user['uid']})";
	}
	if(!empty($forums_all))
	{
		if(isset($where))
		{
			$where = "({$where} OR fid IN (".implode(',', $forums_all)."))";
			$where2 = "({$where2} OR t.fid IN (".implode(',', $forums_all)."))";
		}
		else
		{
			$where = 'fid IN ('.implode(',', $forums_all).')';
			$where2 = 't.fid IN ('.implode(',', $forums_all).')';
		}
	}
	$cutoff = TIME_NOW-$mybb->settings['threadreadcut']*60*60*24;

	if(!empty($permissions['canonlyviewownthreads']))
	{
		$onlyview = " AND uid = '{$mybb->user['uid']}'";
		$onlyview2 = " AND t.uid = '{$mybb->user['uid']}'";
	}

	if($mybb->user['uid'] == 0)
	{
		$comma = '';
		$tids = '';
		$threadsread = $forumsread = array();

		if(isset($mybb->cookies['mybb']['threadread']))
		{
			$threadsread = my_unserialize($mybb->cookies['mybb']['threadread'], false);
		}
		if(isset($mybb->cookies['mybb']['forumread']))
		{
			$forumsread = my_unserialize($mybb->cookies['mybb']['forumread'], false);
		}

		if(!empty($threadsread))
		{
			foreach($threadsread as $key => $value)
			{
				$tids .= $comma.(int)$key;
				$comma = ',';
			}
		}

		if(!empty($tids))
		{
			$count = 0;

			// We've read at least some threads, are they here?
			$query = $db->simple_select("threads", "lastpost, tid, fid", "visible=1 AND closed NOT LIKE 'moved|%' AND {$where} AND lastpost > '{$cutoff}'", array("limit" => 100));

			while($thread = $db->fetch_array($query))
			{
				if((!isset($threadsread[$thread['tid']]) || $thread['lastpost'] > (int)$threadsread[$thread['tid']]) && (!isset($forumsread[$thread['fid']]) || $thread['lastpost'] > (int)$forumsread[$thread['fid']]))
				{
					++$count;
				}
			}

			return $count;
		}

		// Not read any threads?
		return false;
	}
	else
	{
		switch($db->type)
		{
			case "pgsql":
				$query = $db->query("
					SELECT COUNT(t.tid) AS unread_count
					FROM ".TABLE_PREFIX."threads t
					LEFT JOIN ".TABLE_PREFIX."threadsread tr ON (tr.tid=t.tid AND tr.uid='{$mybb->user['uid']}')
					LEFT JOIN ".TABLE_PREFIX."forumsread fr ON (fr.fid=t.fid AND fr.uid='{$mybb->user['uid']}')
					WHERE t.visible=1 AND t.closed NOT LIKE 'moved|%' AND {$where2} AND t.lastpost > COALESCE(tr.dateline,$cutoff) AND t.lastpost > COALESCE(fr.dateline,$cutoff) AND t.lastpost>$cutoff
				");
				break;
			default:
				$query = $db->query("
					SELECT COUNT(t.tid) AS unread_count
					FROM ".TABLE_PREFIX."threads t
					LEFT JOIN ".TABLE_PREFIX."threadsread tr ON (tr.tid=t.tid AND tr.uid='{$mybb->user['uid']}')
					LEFT JOIN ".TABLE_PREFIX."forumsread fr ON (fr.fid=t.fid AND fr.uid='{$mybb->user['uid']}')
					WHERE t.visible=1 AND t.closed NOT LIKE 'moved|%' AND {$where2} AND t.lastpost > IFNULL(tr.dateline,$cutoff) AND t.lastpost > IFNULL(fr.dateline,$cutoff) AND t.lastpost>$cutoff
				");
		}
		return $db->fetch_field($query, "unread_count");
	}
}

/**
 * Mark a particular forum as read.
 *
 * @param int $fid The forum ID
 */
function mark_forum_read($fid)
{
	global $mybb, $db;

	// Can only do "true" tracking for registered users
	if($mybb->settings['threadreadcut'] > 0 && $mybb->user['uid'])
	{
		// Experimental setting to mark parent forums as read
		$forums_to_read = array();

		if($mybb->settings['readparentforums'])
		{
			$ignored_forums = array();
			$forums = array_reverse(explode(",", get_parent_list($fid)));

			unset($forums[0]);
			if(!empty($forums))
			{
				$ignored_forums[] = $fid;

				foreach($forums as $forum)
				{
					$fids = array($forum);
					$ignored_forums[] = $forum;

					$children = explode(",", get_parent_list($forum));
					foreach($children as $child)
					{
						if(in_array($child, $ignored_forums))
						{
							continue;
						}

						$fids[] = $child;
						$ignored_forums[] = $child;
					}

					if(fetch_unread_count(implode(",", $fids)) == 0)
					{
						$forums_to_read[] = $forum;
					}
				}
			}
		}

		switch($db->type)
		{
			case "pgsql":
			case "sqlite":
				add_shutdown(array($db, "replace_query"), array("forumsread", array('fid' => $fid, 'uid' => $mybb->user['uid'], 'dateline' => TIME_NOW), array("fid", "uid")));

				if(!empty($forums_to_read))
				{
					foreach($forums_to_read as $forum)
					{
						add_shutdown(array($db, "replace_query"), array("forumsread", array('fid' => $forum, 'uid' => $mybb->user['uid'], 'dateline' => TIME_NOW), array('fid', 'uid')));
					}
				}
				break;
			default:
				$child_sql = '';
				if(!empty($forums_to_read))
				{
					foreach($forums_to_read as $forum)
					{
						$child_sql .= ", ('{$forum}', '{$mybb->user['uid']}', '".TIME_NOW."')";
					}
				}

				$db->shutdown_query("
					REPLACE INTO ".TABLE_PREFIX."forumsread (fid, uid, dateline)
					VALUES('{$fid}', '{$mybb->user['uid']}', '".TIME_NOW."'){$child_sql}
				");
		}
	}
	// Mark in a cookie
	else
	{
		my_set_array_cookie("forumread", $fid, TIME_NOW, -1);
	}
}

/**
 * Marks all forums as read.
 *
 */
function mark_all_forums_read()
{
	global $mybb, $db, $cache;

	// Can only do "true" tracking for registered users
	if($mybb->user['uid'] > 0)
	{
		$db->update_query("users", array('lastvisit' => TIME_NOW), "uid='".$mybb->user['uid']."'");
		require_once MYBB_ROOT."inc/functions_user.php";
		update_pm_count('', 2);

		if($mybb->settings['threadreadcut'] > 0)
		{
			// Need to loop through all forums and mark them as read
			$forums = $cache->read('forums');

			$update_count = ceil(count($forums)/20);

			if($update_count < 15)
			{
				$update_count = 15;
			}

			switch($db->type)
			{
				case "pgsql":
				case "sqlite":
					$mark_query = array();
					break;
				default:
					$mark_query = '';
			}

			$done = 0;
			foreach(array_keys($forums) as $fid)
			{
				switch($db->type)
				{
					case "pgsql":
					case "sqlite":
						$mark_query[] = array('fid' => $fid, 'uid' => $mybb->user['uid'], 'dateline' => TIME_NOW);
						break;
					default:
						if($mark_query != '')
						{
							$mark_query .= ',';
						}
						$mark_query .= "('{$fid}', '{$mybb->user['uid']}', '".TIME_NOW."')";
				}
				++$done;

				// Only do this in loops of $update_count, save query time
				if($done % $update_count)
				{
					switch($db->type)
					{
						case "pgsql":
						case "sqlite":
							foreach($mark_query as $replace_query)
							{
								add_shutdown(array($db, "replace_query"), array("forumsread", $replace_query, array("fid", "uid")));
							}
							$mark_query = array();
							break;
						default:
							$db->shutdown_query("
								REPLACE INTO ".TABLE_PREFIX."forumsread (fid, uid, dateline)
								VALUES {$mark_query}
							");
							$mark_query = '';
					}
				}
			}

			if(!empty($mark_query))
			{
				switch($db->type)
				{
					case "pgsql":
					case "sqlite":
						foreach($mark_query as $replace_query)
						{
							add_shutdown(array($db, "replace_query"), array("forumsread", $replace_query, array("fid", "uid")));
						}
						break;
					default:
						$db->shutdown_query("
							REPLACE INTO ".TABLE_PREFIX."forumsread (fid, uid, dateline)
							VALUES {$mark_query}
						");
				}
			}
		}
	}
	else
	{
		my_setcookie("mybb[readallforums]", 1);
		my_setcookie("mybb[lastvisit]", TIME_NOW);

		my_unsetcookie("mybb[threadread]");
		my_unsetcookie("mybb[forumread]");
	}
}
