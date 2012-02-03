<?php
/**
 * MyBB 1.6
 * Copyright 2010 MyBB Group, All Rights Reserved
 *
 * Website: http://mybb.com
 * License: http://mybb.com/about/license
 *
 * $Id: backupdb.php 5297 2010-12-28 22:01:14Z Tomm $
 */

function task_backupdb($task)
{
	global $db, $config, $lang;
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
		
		$file = MYBB_ADMIN_DIR.'backups/backup_'.substr(md5($mybb->user['uid'].TIME_NOW), 0, 10).random_str(54);
		
		if(function_exists('gzopen'))
		{
			$fp = gzopen($file.'.sql.gz', 'w9');
		}
		else
		{
			$fp = fopen($file.'.sql', 'w');
		}
		
		$tables = $db->list_tables($config['database']['database'], $config['database']['table_prefix']);
	
		$time = date('dS F Y \a\t H:i', TIME_NOW);
		$header = "-- MyBB Database Backup\n-- Generated: {$time}\n-- -------------------------------------\n\n";
		$contents = $header;
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
			
			$query = $db->simple_select($table);
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
		}
		
		$db->set_table_prefix(TABLE_PREFIX);
		
		if(function_exists('gzopen'))
		{
			gzwrite($fp, $contents);
			gzclose($fp);
		}
		else
		{
			fwrite($fp, $contents);
			fclose($fp);
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
?>