<?php
/**
 * MyBB 1.6
 * Copyright 2010 MyBB Group, All Rights Reserved
 *
 * Website: http://mybb.com
 * License: http://mybb.com/about/license
 *
 */

function task_mods($task)
{
	global $db, $lang;
	
	// Delete old searches
	$cut = TIME_NOW-(60*60*24);
	$db->delete_query("searchlog", "dateline < '{$cut}'");
	
	add_task_log($task, $lang->mods_task_ran);
}
?>