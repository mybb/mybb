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

function task_threadviews($task)
{
	global $db;
	
	$threadviews = array();

	// Update thread views
	$query = $db->simple_select("threadviews");
	while($threadview = $db->fetch_array($query))
	{
		++$threadviews[$threadview['tid']];
	}
	
	if(!empty($threadviews))
	{
		foreach($threadviews as $tid => $views)
		{
			$db->write_query("UPDATE ".TABLE_PREFIX."threads SET views=views+{$views} WHERE tid='{$tid}' LIMIT 1");
		}
	}
	
	$db->write_query("TRUNCATE TABLE ".TABLE_PREFIX."threadviews");
}
?>