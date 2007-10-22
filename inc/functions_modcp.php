<?php
/**
 * MyBB 1.2
 * Copyright © 2007 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/about/license
 *
 * $Id$
 */

/**
 * Check if the current user has permission to perform a ModCP action on another user
 *
 * @param int The user ID to perform the action on.
 * @return boolean True if the user has necessary permissions
 */
function modcp_can_manage_user($uid)
{
	global $mybb;

	$user_permissions = user_permissions($uid);

	// Current user is only a local moderator or use with ModCP permissions, cannot manage super mods or admins
	if($mybb->usergroup['issupermod'] == 0 && ($user_permissions['issupermod'] == 1 || $user_permissions['canadmincp'] == 1))
	{
		return false;
	}
	// Current user is a super mod or is an administrator
	else if($mybb->usergroup['issupermod'] == 1 && $user_permissions['canadmincp'] == 1 || (is_super_admin($uid) && !is_super_admin($uid)))
	{
		return false;
	}
	return true;
}

function fetch_forum_announcements($pid=0, $depth=1)
{
	global $mybb, $db, $lang, $announcements, $templates, $announcements_forum;
	static $forums_by_parent;

	if(!is_array($forums_by_parent))
	{
		$forum_cache = cache_forums();

		foreach($forum_cache as $forum)
		{
			$forums_by_parent[$forum['pid']][$val['disporder']][$forum['fid']] = $forum;
		}
	}

	if(!is_array($forums_by_parent[$pid]))
	{
		return;
	}

	foreach($forums_by_parent[$pid] as $children)
	{
		foreach($children as $forum)
		{
			if($forum['active'] == 0 || !is_moderator($forum['fid']))
			{
				continue;
			}
			
			$trow = alt_trow();
			
			$padding = 40*($depth-1);
			
			eval("\$announcements_forum .= \"".$templates->get("modcp_announcements_forum")."\";");
				
			if($announcements[$forum['fid']])
			{
				foreach($announcements[$forum['fid']] as $aid => $announcement)
				{
					$trow = alt_trow();
					
					if($announcement['enddate'] < TIME_NOW && $announcement['enddate'] != 0)
					{
						$icon = "<img src=\"images/minioff.gif\" alt=\"({$lang->expired})\" title=\"{$lang->expired_announcement}\"  style=\"vertical-align: middle;\" /> ";
					}
					else
					{
						$icon = "<img src=\"images/minion.gif\" alt=\"({$lang->active})\" title=\"{$lang->active_announcement}\"  style=\"vertical-align: middle;\" /> ";
					}
							
					eval("\$announcements_forum .= \"".$templates->get("modcp_announcements_announcement")."\";");
				}
			}

			// Build the list for any sub forums of this forum
			if($forums_by_parent[$forum['fid']])
			{
				fetch_forum_announcements($forum['fid'], ++$depth);
			}
		}
	}
}

?>