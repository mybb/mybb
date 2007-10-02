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

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$page->add_breadcrumb_item($lang->system_health, "index.php?".SID."&amp;module=tools/index");

if($mybb->input['action'] == "utf8_conversion")
{
	$page->add_breadcrumb_item($lang->utf8_conversion, "index.php?".SID."&amp;module=tools/index&amp;action=utf8_conversion");
	
	$page->output_header($lang->system_health." - ".$lang->utf8_conversion);
	
	if($mybb->request_method == "post")
	{
		@set_time_limit(0);
		
		$old_table_prefix = $db->table_prefix;
		$db->set_table_prefix('');
		
		if(!$db->table_exists($db->escape_string($mybb->input['table'])))
		{
			flash_message($lang->error_invalid_table, 'error');
			admin_redirect("index.php?".SID."&module=tools/index&action=utf8_conversion");
		}
		
		$sub_tabs['utf8_conversion'] = array(
			'title' => $lang->utf8_conversion,
			'link' => "index.php?".SID."&amp;module=tools/stats&amp;action=utf8_conversion",
			'description' => $lang->utf8_conversion_desc2
		);
		
		$page->output_nav_tabs($sub_tabs, 'utf8_conversion');
		
		$table = new Table;
		
		$table1 = $db->show_create_table($db->escape_string($mybb->input['table']));
        preg_match("#CHARSET=([a-zA-Z0-9_]+)\s?#i", $table1, $matches);
		$charset = $matches[1];
		
		$table->construct_cell("<strong>".sprintf($lang->converting_to_utf8, $mybb->input['table'], $charset)."</strong>");
		$table->construct_row();
		
		$table->construct_cell($lang->please_wait);
		$table->construct_row();
		
		$table->output($converting_table." {$mybb->input['table']}");
		
		$db->set_table_prefix($old_table_prefix);
		
		$page->output_footer(false);
		
		$old_table_prefix = $db->table_prefix;
		$db->set_table_prefix('');
		
		flush();
		
		// Log admin action
		log_admin_action($mybb->input['table']);

		$types = array(
			'text' => 'blob',
			'mediumtext' => 'mediumblob',
			'longtext' => 'longblob',
			'char' => 'binary',
			'varchar' => 'varbinary',
			'tinytext' => 'tinyblob'			
		);
		
		// Get next table in list
		$convert_to_binary = '';
		$convert_to_utf8 = '';
		$comma = '';
		
		// Set table default charset
		$db->write_query("ALTER TABLE {$mybb->input['table']} DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci");

		// Fetch any fulltext keys
		if($db->supports_fulltext($mybb->input['table']))
		{
			$table_structure = $db->show_create_table($mybb->input['table']);
			switch($db->type)
			{
				case "mysql":
				case "mysqli":
					preg_match_all("#FULLTEXT KEY `?([a-zA-Z0-9_]+)`? \(([a-zA-Z0-9_`,']+)\)#i", $table_structure, $matches);
					if(is_array($matches))
					{
						foreach($matches[0] as $key => $matched)
						{
							$db->write_query("ALTER TABLE {$mybb->input['table']} DROP INDEX {$matches[1][$key]}");
							$fulltext_to_create[$matches[1][$key]] = $matches[2][$key];
						}
					}
			}
		}

		// Find out which columns need converting and build SQL statements
		$query = $db->query("SHOW FULL COLUMNS FROM {$mybb->input['table']}");
		while($column = $db->fetch_array($query))
		{
			list($type) = explode('(', $column['Type']);
			if(array_key_exists($type, $types))
			{
				// Build the actual strings for converting the columns
				$names = "CHANGE {$column['Field']} {$column['Field']} ";
				
				$attributes = " DEFAULT ";
				if($column['Default'] == 'NULL')
				{
					$attributes .= "NULL ";
				}
				else
				{
					$attributes .= "'".$db->escape_string($column['Default'])."' ";
					
					if($column['Null'] == 'YES')
					{
						$attributes .= 'NULL';
					}
					else
					{
						$attributes .= 'NOT NULL';
					}
				}
				
				$convert_to_binary .= $comma.$names.preg_replace('/'.$type.'/i', $types[$type], $column['Type']).$attributes;
				$convert_to_utf8 .= "{$comma}{$names}{$column['Type']} CHARACTER SET utf8 COLLATE utf8_general_ci{$attributes}";
				
				$comma = ', ';
			}
		}
		
		if(!empty($convert_to_binary))
		{
			// This converts the columns to UTF-8 while also doing the same for data
			$db->write_query("ALTER TABLE {$mybb->input['table']} {$convert_to_binary}");
			$db->write_query("ALTER TABLE {$mybb->input['table']} {$convert_to_utf8}");
		}
		
		// Any fulltext indexes to recreate?
		if(is_array($fulltext_to_create))
		{
			foreach($fulltext_to_create as $name => $fields)
			{
				$db->create_fulltext_index($mybb->input['table'], $fields, $name);
			}
		}
		
		
		$db->set_table_prefix($old_table_prefix);
		
		flash_message(sprintf($lang->success_table_converted, $mybb->input['table']), 'success');
		admin_redirect("index.php?".SID."&module=tools/index&action=utf8_conversion");
		
		exit;
	}
	
	$sub_tabs['utf8_conversion'] = array(
		'title' => $lang->utf8_conversion,
		'link' => "index.php?".SID."&amp;module=tools/stats&amp;action=utf8_conversion",
		'description' => $lang->utf8_conversion_desc2
	);
	
	$page->output_nav_tabs($sub_tabs, 'utf8_conversion');
	
	if($mybb->input['table'])
	{
		$old_table_prefix = $db->table_prefix;
		$db->set_table_prefix('');
		
		if(!$db->table_exists($db->escape_string($mybb->input['table'])))
		{
			$db->set_table_prefix($old_table_prefix);
			flash_message($lang->error_invalid_table, 'error');
			admin_redirect("index.php?".SID."&module=tools/index&action=utf8_conversion");
		}
		
		$table = $db->show_create_table($db->escape_string($mybb->input['table']));
        preg_match("#CHARSET=([a-zA-Z0-9_]+)\s?#i", $table, $matches);
		$charset = $matches[1];
		
		$form = new Form("index.php?".SID."&amp;module=tools/index&amp;action=utf8_conversion", "post", "utf8_conversion");
		echo $form->generate_hidden_field("table", $mybb->input['table']);
		
		$table = new Table;
		
		$table->construct_cell("<strong>".sprintf($lang->convert_to_utf8, $mybb->input['table'], $charset)."</strong>");
		$table->construct_row();
		
		$table->construct_cell($lang->notice_proccess_long_time);
		$table->construct_row();
		
		$table->output($lang->convert_table." {$mybb->input['table']}");
		
		$buttons[] = $form->generate_submit_button($lang->convert_database_table);
		$form->output_submit_wrapper($buttons);
		
		$form->end();
		
		$db->set_table_prefix($old_table_prefix);
		
		$page->output_footer();
		
		exit;
	}
	
	if(!$mybb->config['database']['encoding'])
	{
		flash_message($lang->error_db_encoding_not_set, 'error');
		admin_redirect("index.php?".SID."&module=tools/index");
	}
	
	$tables = $db->list_tables($config['database']['database']);
	
	$old_table_prefix = $db->table_prefix;
	$db->set_table_prefix('');
	
	$not_okey_count = 0;
	$not_okey = array();
	
	foreach($tables as $key => $tablename)
	{		
		if(substr($tablename, 0, strlen($old_table_prefix)) == $old_table_prefix)
		{
			$table = $db->show_create_table($tablename);
        	preg_match("#CHARSET=([a-zA-Z0-9_]+)\s?#i", $table, $matches);
			if(fetch_iconv_encoding($matches[1]) != 'utf-8')
			{
				$not_okey[$key] = $tablename;
				++$not_okey_count;
			}
			
			$mybb_tables[$key] = $tablename;		
		}
	}
	
	$db->set_table_prefix($old_table_prefix);
	
	asort($mybb_tables);
	
	$table = new Table;
	$table->construct_header($lang->table);
	$table->construct_header($lang->status, array("class" => "align_center"));
	
	foreach($mybb_tables as $key => $tablename)
	{
		if(array_key_exists($key, $not_okey))
		{
			$status = "<a href=\"index.php?".SID."&amp;module=tools/index&amp;action=utf8_conversion&amp;table={$tablename}\" style=\"background: url(styles/{$page->style}/images/icons/cross.gif) no-repeat; padding-left: 20px;\">{$lang->convert_now}</a>";
		}
		else
		{
			$status = "<img src=\"styles/{$page->style}/images/icons/tick.gif\" alt\"{$lang->ok}\" />";
		}
		$table->construct_cell("<strong>{$tablename}</strong>");
		$table->construct_cell($status, array("class" => "align_center", 'width' => '15%'));
		$table->construct_row();
	}
	
	$table->output($lang->utf8_conversion);
	
	$page->output_footer();
}

if(!$mybb->input['action'])
{
	$page->output_header($lang->system_health);
	
	$sub_tabs['system_health'] = array(
		'title' => $lang->system_health,
		'link' => "index.php?".SID."&amp;module=tools/stats",
		'description' => $lang->system_health_desc
	);
	
	$sub_tabs['utf8_conversion'] = array(
		'title' => $lang->utf8_conversion,
		'link' => "index.php?".SID."&amp;module=tools/stats&amp;action=utf8_conversion",
		'description' => $lang->utf8_conversion_desc2
	);
	
	$page->output_nav_tabs($sub_tabs, 'system_health');
	
	$table = new Table;
	$table->construct_header($lang->totals, array("colspan" => 2));
	$table->construct_header($lang->attachments, array("colspan" => 2));
	
	$query = $db->simple_select("attachments", "COUNT(*) AS numattachs, SUM(filesize) as spaceused, SUM(downloads) as downloadsused", "visible='1' AND pid > '0'");
	$attachs = $db->fetch_array($query);
	
	$table->construct_cell("<strong>{$lang->total_database_size}</strong>", array('width' => '25%'));
	$table->construct_cell(get_friendly_size($db->fetch_size()), array('width' => '25%'));
	$table->construct_cell("<strong>{$lang->attachment_space_used}</strong>", array('width' => '200'));
	$table->construct_cell(get_friendly_size($attachs['spaceused']), array('width' => '200'));
	$table->construct_row();
	
	$table->construct_cell("<strong>{$lang->total_cache_size}</strong>", array('width' => '25%'));
	$table->construct_cell(get_friendly_size($cache->size_of()), array('width' => '25%'));
	$table->construct_cell("<strong>{$lang->estimated_attachment_bandwidth_usage}</strong>", array('width' => '25%'));
	$table->construct_cell(get_friendly_size(round($attachs['spaceused']*$attachs['downloadsused'])), array('width' => '25%'));
	$table->construct_row();
	
	$table->construct_cell("<strong>{$lang->max_upload_post_size}</strong>", array('width' => '200'));
	$table->construct_cell(@ini_get('upload_max_filesize').' / '.@ini_get('post_max_size'), array('width' => '200'));
	$table->construct_cell("<strong>{$lang->average_attachment_size}</strong>", array('width' => '25%'));
	$table->construct_cell(get_friendly_size(round($attachs['spaceused']/$attachs['numattachs'])), array('width' => '25%'));
	$table->construct_row();
	
	$table->output($lang->stats);
	
	$table->construct_header($lang->task);
	$table->construct_header($lang->run_time, array("width" => 200, "class" => "align_center"));
	
	$task_cache = $cache->read("tasks");
	$nextrun = $task_cache['nextrun'];
	
	$query = $db->simple_select("tasks", "*", "nextrun >= '{$nextrun}'", array("order_by" => "title", "order_dir" => "asc", 'limit' => 3));
	while($task = $db->fetch_array($query))
	{
		$task['title'] = htmlspecialchars_uni($task['title']);
		$next_run = date($mybb->settings['dateformat'], $task['nextrun']).", ".date($mybb->settings['timeformat'], $task['nextrun']);
		$table->construct_cell("<strong>{$task['title']}</strong>");
		$table->construct_cell($next_run, array("class" => "align_center"));
	
		$table->construct_row();
	}
	
	$table->output($lang->next_3_tasks);
	
	$backups = array();
	$dir = MYBB_ADMIN_DIR.'backups/';
	$handle = opendir($dir);
	while(($file = readdir($handle)) !== false)
	{
		if(filetype(MYBB_ADMIN_DIR.'backups/'.$file) == 'file')
		{
			$ext = get_extension($file);
			if($ext == 'gz' || $ext == 'sql')
			{
				$backups[@filemtime(MYBB_ADMIN_DIR.'backups/'.$file)] = array(
					"file" => $file,
					"time" => @filemtime(MYBB_ADMIN_DIR.'backups/'.$file),
					"type" => $ext
				);
			}
		}
	}
	
	$count = count($backups);
	krsort($backups);
	
	$table = new Table;
	$table->construct_header($lang->name);
	$table->construct_header($lang->backup_time, array("width" => 200, "class" => "align_center"));
	
	$backupscnt = 0;
	foreach($backups as $backup)
	{
		++$backupscnt;
		
		if($backupscnt == 4)
		{
			break;
		}
		
		if($backup['time'])
		{
			$time = my_date($mybb->settings['dateformat'].", ".$mybb->settings['timeformat'], $backup['time']);
		}
		else
		{
			$time = "-";
		}
		
		$table->construct_cell("<a href=\"index.php?".SID."&amp;module=tools/backupdb&amp;action=dlbackup&amp;file={$backup['file']}\">{$backup['file']}</a>");
		$table->construct_cell($time, array("class" => "align_center"));
		$table->construct_row();
	}
	
	if($count == 0)
	{
		$table->construct_cell($lang->no_backups, array('colspan' => 2));
		$table->construct_row();
	}
	
	
	$table->output($lang->existing_db_backups);
	
	if(is_writable(MYBB_ROOT.'inc/settings.php'))
	{
		$message_settings = "<span style=\"color: green;\">{$lang->writable}</span>";
	}
	else
	{
		$message_settings = "<strong><span style=\"color: #C00\">{$lang->not_writable}</span></strong><br />{$lang->please_chmod_777}";
	}
	
	if(is_writable('.'.$mybb->settings['uploadspath']))
	{
		$message_upload = "<span style=\"color: green;\">{$lang->writable}</span>";
	}
	else
	{
		$message_upload = "<strong><span style=\"color: #C00\">{$lang->not_writable}</span></strong><br />{$lang->please_chmod_777}";
		++$errors;
	}
	
	if(is_writable('../'.$mybb->settings['avataruploadpath']))
	{
		$message_avatar = "<span style=\"color: green;\">{$lang->writable}</span>";
	}
	else
	{
		$message_avatar = "<strong><span style=\"color: #C00\">{$lang->not_writable}</span></strong><br />{$lang->please_chmod_777}";
		++$errors;
	}
	
	if(is_writable(MYBB_ROOT.'inc/languages/'))
	{
		$message_language = "<span style=\"color: green;\">{$lang->writable}</span>";
	}
	else
	{
		$message_language = "<strong><span style=\"color: #C00\">{$lang->not_writable}</span></strong><br />{$lang->please_chmod_777}";
		++$errors;
	}
	
	if(is_writable(MYBB_ROOT.$config['admin_dir'].'/backups/'))
	{
		$message_backup = "<span style=\"color: green;\">{$lang->writable}</span>";
	}
	else
	{
		$message_backup = "<strong><span style=\"color: #C00\">{$lang->not_writable}</span></strong><br />{$lang->please_chmod_777}";
		++$errors;
	}
	
	
	if($errors)
	{
		$page->output_error("<strong><span style=\"color: #C00\">{$errors} {$lang->error_chmod}</span></strong> {$lang->chmod_info} <a href=\"http://wiki.mybboard.net/index.php/HowTo_Chmod\" target=\"_blank\">MyBB Wiki</a>.");
	}
	else
	{
		$page->output_success("<strong><span style=\"color: green;\">{$lang->success_chmod}</span></strong>");
	}
	
	$table = new Table;
	$table->construct_header($lang->file);
	$table->construct_header($lang->location, array("colspan" => 2, 'width' => 250));
	
	$table->construct_cell("<strong>{$lang->settings_file}</strong>");
	$table->construct_cell("./inc/settings.php");
	$table->construct_cell($message_settings);
	$table->construct_row();
	
	$table->construct_cell("<strong>{$lang->file_upload_dir}</strong>");
	$table->construct_cell($mybb->settings['uploadspath']);
	$table->construct_cell($message_upload);
	$table->construct_row();
	
	$table->construct_cell("<strong>{$lang->avatar_upload_dir}</strong>");
	$table->construct_cell($mybb->settings['avataruploadpath']);
	$table->construct_cell($message_avatar);
	$table->construct_row();
	
	$table->construct_cell("<strong>{$lang->language_files}</strong>");
	$table->construct_cell("./inc/languages");
	$table->construct_cell($message_language);
	$table->construct_row();
	
	$table->construct_cell("<strong>{$lang->backup_dir}</strong>");
	$table->construct_cell('./'.$config['admin_dir'].'/backups');
	$table->construct_cell($message_backup);
	$table->construct_row();
	
	$table->output($lang->chmod_files_and_dirs);
	
	$page->output_footer();
}
?>