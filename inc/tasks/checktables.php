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

function task_checktables($task)
{
	global $db, $mybb;
	
	@set_time_limit(0);
	
	$ok = array(
		"The storage engine for the table doesn't support check",
		"Table is already up to date",
		"OK"
	);
	
	$comma = "";
	$tables_list = "";

	$tables = $db->list_tables($mybb->config['database'], $mybb->config['table_prefix']);
	foreach($tables as $key => $table)
	{
		$tables_list .= "{$comma}{$table} ";		
		$comma = ",";
	}
	
	if($tables_list)
	{
		$query = $db->query("CHECK TABLE {$tables_list}CHANGED;");
		while($table = $db->fetch_array($query))
		{
			if(!in_array($table['Msg_text'], $ok))
			{
				if($table['Table'] != $mybb->config['database'].".".TABLE_PREFIX."settings" && $setting_done != true)
				{
					$boardclosed = $mybb->settings['boardclosed'];
					$boardclosed_reason = $mybb->settings['boardclosed_reason'];
					
					$db->update_query("settings", array('value' => 'yes'), "name='boardclosed'", 1);
					$db->update_query("settings", array('value' => $lang->error_database_repair), "name='boardclosed_reason'", 1);
					rebuild_settings();
					
					$setting_done = true;
				}
				
				$db->query("REPAIR TABLE {$table['Table']}");
			}
		}
		
		if($table['Table'] != $mybb->config['table_prefix'].".".TABLE_PREFIX."settings" && $setting_done == true)
		{
			$db->update_query("settings", array('value' => $boardclosed), "name='boardclosed'", 1);
			$db->update_query("settings", array('value' => $boardclosed_reason), "name='boardclosed_reason'", 1);
					
			rebuild_settings();
		}
		
	}
}
?>