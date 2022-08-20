<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

function task_checktables($task)
{
	global $db, $mybb, $lang, $plugins;

	if(!in_array($db->type, array('mysql', 'mysqli')))
	{
		return;
	}

	@set_time_limit(0);

	$ok = array(
		"The storage engine for the table doesn't support check",
		"Table is already up to date",
		"OK"
	);

	$comma = "";
	$tables_list = "";
	$repaired = array();
	$setting_done = false;

	$tables = $db->list_tables($mybb->config['database']['database'], $mybb->config['database']['table_prefix']);
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
				if($table['Table'] != $mybb->config['database']['database'].".".TABLE_PREFIX."settings" && $setting_done != true)
				{
					$boardclosed = $mybb->settings['boardclosed'];
					$boardclosed_reason = $mybb->settings['boardclosed_reason'];

					$db->update_query("settings", array('value' => 1), "name='boardclosed'", 1);
					$db->update_query("settings", array('value' => $db->escape_string($lang->error_database_repair)), "name='boardclosed_reason'", 1);
					rebuild_settings();

					$setting_done = true;
				}

				$db->query("REPAIR TABLE {$table['Table']}");
				$repaired[] = $table['Table'];
			}
		}

		if($setting_done == true)
		{
			$db->update_query("settings", array('value' => (int)$boardclosed), "name='boardclosed'", 1);
			$db->update_query("settings", array('value' => $db->escape_string($boardclosed_reason)), "name='boardclosed_reason'", 1);

			rebuild_settings();
		}

	}

	if(is_object($plugins))
	{
		$plugins->run_hooks('task_checktables', $task);
	}

	if(!empty($repaired))
	{
		add_task_log($task, $lang->sprintf($lang->task_checktables_ran_found, implode(', ', $repaired)));
	}
	else
	{
		add_task_log($task, $lang->task_checktables_ran);
	}
}
