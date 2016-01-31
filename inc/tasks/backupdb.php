<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

function task_backupdb($task)
{
	global $db, $config, $lang, $plugins;
	static $contents;

	@set_time_limit(0);

	if(!defined('MYBB_ADMIN_DIR'))
	{
		if(!isset($config['admin_dir']))
		{
			$config['admin_dir'] = "admin";
		}

		define('MYBB_ADMIN_DIR', MYBB_ROOT.$config['admin_dir'].'/');
	}

	// Check if folder is writable, before allowing submission
	if(!is_writable(MYBB_ADMIN_DIR."/backups"))
	{
		add_task_log($task, $lang->task_backup_cannot_write_backup);
	}
	else
	{
		$db->set_table_prefix('');

		$file = MYBB_ADMIN_DIR.'backups/backup_'.date("_Ymd_His_").random_str(16);

		if(function_exists('gzopen'))
		{
			$fp = gzopen($file.'.incomplete.sql.gz', 'w9');
		}
		else
		{
			$fp = fopen($file.'.incomplete.sql', 'w');
		}

		$tables = $db->list_tables($config['database']['database'], $config['database']['table_prefix']);

		$time = date('dS F Y \a\t H:i', TIME_NOW);
		$contents = "-- MyBB Database Backup\n-- Generated: {$time}\n-- -------------------------------------\n\n";

		if(is_object($plugins))
		{
			$args = array(
				'task' =>  &$task,
				'tables' =>  &$tables,
			);
			$plugins->run_hooks('task_backupdb', $args);
		}

		foreach($tables as $table)
		{
			$field_list = array();
			$fields_array = $db->show_fields_from($table);
			foreach($fields_array as $field)
			{
				$field_list[] = $field['Field'];
			}

			$fields = "`".implode("`,`", $field_list)."`";

			$structure = $db->show_create_table($table).";\n";
			$contents .= $structure;
			clear_overflow($fp, $contents);

			if($db->engine == 'mysqli')
			{
				$query = mysqli_query($db->read_link, "SELECT * FROM {$db->table_prefix}{$table}", MYSQLI_USE_RESULT);
			}
			else
			{
				$query = $db->simple_select($table);
			}

			while($row = $db->fetch_array($query))
			{
				$insert = "INSERT INTO {$table} ($fields) VALUES (";
				$comma = '';
				foreach($field_list as $field)
				{
					if(!isset($row[$field]) || is_null($row[$field]))
					{
						$insert .= $comma."NULL";
					}
					else if($db->engine == 'mysqli')
					{
						$insert .= $comma."'".mysqli_real_escape_string($db->read_link, $row[$field])."'";
					}
					else
					{
						$insert .= $comma."'".$db->escape_string($row[$field])."'";
					}
					$comma = ',';
				}
				$insert .= ");\n";
				$contents .= $insert;
				clear_overflow($fp, $contents);
			}
			$db->free_result($query);
		}

		$db->set_table_prefix(TABLE_PREFIX);

		if(function_exists('gzopen'))
		{
			gzwrite($fp, $contents);
			gzclose($fp);
			rename($file.'.incomplete.sql.gz', $file.'.sql.gz');
		}
		else
		{
			fwrite($fp, $contents);
			fclose($fp);
			rename($file.'.incomplete.sql', $file.'.sql');
		}

		add_task_log($task, $lang->task_backup_ran);
	}
}

// Allows us to refresh cache to prevent over flowing
function clear_overflow($fp, &$contents)
{
	global $mybb;

	if(function_exists('gzopen'))
	{
		gzwrite($fp, $contents);
	}
	else
	{
		fwrite($fp, $contents);
	}

	$contents = '';
}
