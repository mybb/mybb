<?php
/**
 * MyBB 1.2
 * Copyright © 2007 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/license.php
 *
 * $Id$
 */

function task_usercleanup($task)
{
	global $db;

	// Expire old warnings
	$query = $db->query("
		SELECT w.wid, w.uid, w.points, u.warningpoints
		FROM ".TABLE_PREFIX."warnings w
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=w.uid)
		WHERE expires<".TIME_NOW." AND expires!=0 AND expired!=1
	");
	while($warning = $db->fetch_array($query))
	{
		$updated_warning = array(
			"expired" => 1
		);
		$db->update_query("warnings", $updated_warning, "wid='{$warning['wid']}'");
		$warning['warningpoints'] -= $warning['points'];
		if($warning['warningpoints'] < 0)
		{
			$warning['warningpoints'] = 0;
		}
		$updated_user = array(
			"warningpoints" => intval($warning['warningpoints'])
		);
		$db->update_query("users", $updated_user, "uid='{$warning['uid']}'");
	}

	// Expire any post moderation or suspension limits
	$query = $db->simple_select("users", "uid, moderationtime, suspensiontime", "(moderationtime!=0 AND moderationtime<".TIME_NOW.") OR (suspensiontime!=0 AND suspensiontime<".TIME_NOW.")");
	while($user = $db->fetch_array($query))
	{
		$updated_user = array();
		if($user['moderationtime'] != 0 && $user['moderationtime'] < TIME_NOW)
		{
			$updated_user['moderateposts'] = 0;
			$updated_user['moderationtime'] = 0;
		}
		if($user['suspensiontime'] != 0 && $user['suspensiontime'] < TIME_NOW)
		{
			$updated_user['suspendposting'] = 0;
			$updated_user['suspensiontime'] = 0;
		}
		$db->update_query("users", $updated_user, "uid='{$user['uid']}'");
	}

	// Expire bans
	$query = $db->simple_select("banned", "*", "lifted!=0 AND lifted<".TIME_NOW);
	while($ban = $db->fetch_array($query))
	{
		$updated_user = array(
			"usergroup" => $ban['oldgroup'],
			"additionalgroups" => $ban['oldadditionalgroups'],
			"displaygroup" => $ban['displaygroup']
		);
		$db->update_query("users", $updated_user, "uid='{$ban['uid']}'");
		$db->delete_query("banned", "bid='{$ban['bid']}'");
	}
}
?>