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
 * Return a timestamp from a date.
 */
function modcp_date2timestamp($date, $stamp=0)
{
	if($stamp == 0)
	{
		$stamp = TIME_NOW;
	}
	$d = explode('-', $date);
	$nowdate = date("H-j-n-Y", $stamp);
	$n = explode('-', $nowdate);
	$n[1] += $d[0];
	$n[2] += $d[1];
	$n[3] += $d[2];
	return mktime(date("G"), date("i"), 0, $n[2], $n[1], $n[3]);
}

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