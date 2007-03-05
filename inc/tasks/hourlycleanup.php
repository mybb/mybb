<?php
/**
 * MyBB 1.2
 * Copyright © 2007 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.com
 * License: http://www.mybboard.com/license.php
 *
 * $Id$
 */

function task_hourlycleanup($task)
{
	global $db;

	// Expire thread redirects
	$db->delete_query("threads", "deletetime != '0' AND deletetime < '".time()."'");

	// Delete old searches
	$cut = time()-(60*60*24);
	$db->delete_query("searchlog", "dateline < '{$cut}'");

	// Delete old captcha images
	$cut = time()-(60*60*12);
	$db->delete_query("captcha", "dateline < '{$cut}'");

}
?>