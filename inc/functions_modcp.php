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
	if($mybb->usergroup['issupermod'] == "no" && ($user_permissions['issupermod'] == "yes" || $user_permissions['canadmincp'] == "yes"))
	{
		return false;
	}
	// Current user is a super mod or is an administrator
	else if($mybb->usergroup['issupermod'] == "yes" && $user_permissions['canadmincp'] == "yes" || (is_super_admin($uid) && !is_super_admin($uid)))
	{
		return false;
	}
	return true;
}

?>