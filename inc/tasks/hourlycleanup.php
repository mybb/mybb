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

function task_hourlycleanup($task)
{
	global $db;
	
	$threads = array();
	$posts = array();

	// Delete moved threads with time limits
	$db->delete_query("threads", "deletetime != '0' AND deletetime < '".TIME_NOW."'");
	
	// Delete old searches
	$cut = TIME_NOW-(60*60*24);
	$db->delete_query("searchlog", "dateline < '{$cut}'");

	// Delete old captcha images
	$cut = TIME_NOW-(60*60*12);
	$db->delete_query("captcha", "dateline < '{$cut}'");

}
?>