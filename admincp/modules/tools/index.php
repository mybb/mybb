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

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$page->add_breadcrumb_item("System Health", "index.php?".SID."&amp;module=tools/index");

if($mybb->input['action'] == "utf8_conversion")
{
	$page->add_breadcrumb_item("UTF-8 Conversion", "index.php?".SID."&amp;module=tools/index&amp;action=utf8_conversion");
	
	$page->output_header("System Health - UTF-8 Conversion");
	
	if($mybb->request_method == "post")
	{
		@set_time_limit(0);
		
		$old_table_prefix = $db->table_prefix;
		$db->set_table_prefix('');
		
		if(!$db->table_exists($db->escape_string($mybb->input['table'])))
		{
			flash_message('The specified table does not exist.', 'error');
			admin_redirect("index.php?".SID."&module=tools/index&action=utf8_conversion");
		}
		
		$sub_tabs['utf8_conversion'] = array(
			'title' => "UTF-8 Conversion",
			'link' => "index.php?".SID."&amp;module=tools/stats&action=utf8_conversion",
			'description' => 'You are currently converting a database table to the UTF-8 format. Be aware that this proccess may take up to several hours depending on the size of your forum and this table. When the process is complete, you will be returned to the UTF-8 Conversion main page.'
		);
		
		$page->output_nav_tabs($sub_tabs, 'utf8_conversion');
		
		$table = new Table;
		
		$table->construct_cell("<strong>MyBB is currently converting \"{$mybb->input['table']}\" table to UTF-8 language encoding from {$charset} encoding.</strong>");
		$table->construct_row();
		
		$table->construct_cell("Please wait...");
		$table->construct_row();
		
		$table->output("Converting Table: {$mybb->input['table']}");
		
		$db->set_table_prefix($old_table_prefix);
		
		$page->output_footer(false);
		
		$old_table_prefix = $db->table_prefix;
		$db->set_table_prefix('');
		
		flush();
		
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
		$db->query("ALTER TABLE {$mybb->input['table']} DEFAULT CHARACTER SET utf8");

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
							$db->query("ALTER TABLE {$mybb->input['table']} DROP INDEX {$matches[1][$key]}");
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
				$convert_to_utf8 .= "{$comma}{$names}{$column['Type']} CHARACTER SET utf8{$attributes}";
				
				$comma = ', ';
			}
		}
		
		if(!empty($convert_to_binary))
		{
			// This converts the columns to UTF-8 while also doing the same for data
			$db->query("ALTER TABLE {$mybb->input['table']} {$convert_to_binary}");
			$db->query("ALTER TABLE {$mybb->input['table']} {$convert_to_utf8}");
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
		
		
		sleep(5);
		
		flash_message('The specified table "'.$mybb->input['table'].'" has been sucessfully converted to UTF-8.', 'success');
		admin_redirect("index.php?".SID."&module=tools/index&action=utf8_conversion");
		
		exit;
	}
	
	$sub_tabs['utf8_conversion'] = array(
		'title' => "UTF-8 Conversion",
		'link' => "index.php?".SID."&amp;module=tools/stats&action=utf8_conversion",
		'description' => 'This tool checks the database tables to make sure they are in the UTF-8 format and allows you to convert them if they are not.'
	);
	
	$page->output_nav_tabs($sub_tabs, 'utf8_conversion');
	
	if($mybb->input['table'])
	{
		$old_table_prefix = $db->table_prefix;
		$db->set_table_prefix('');
		
		if(!$db->table_exists($db->escape_string($mybb->input['table'])))
		{
			$db->set_table_prefix($old_table_prefix);
			flash_message('The specified table does not exist.', 'error');
			admin_redirect("index.php?".SID."&module=tools/index&action=utf8_conversion");
		}
		
		$table = $db->show_create_table($db->escape_string($mybb->input['table']));
        preg_match("#CHARSET=([a-zA-Z0-9_]+)\s?#i", $table, $matches);
		$charset = $matches[1];
		
		$form = new Form("index.php?".SID."&amp;module=tools/index&amp;action=utf8_conversion", "post", "utf8_conversion");
		echo $form->generate_hidden_field("table", $mybb->input['table']);
		
		$table = new Table;
		
		$table->construct_cell("<strong>You are about to convert the \"{$mybb->input['table']}\" table to UTF-8 language encoding from {$charset} encoding.</strong>");
		$table->construct_row();
		
		$table->construct_cell("This proccess may take up to several hours depending on the size of your forum and this table.");
		$table->construct_row();
		
		$table->output("Convert Table: {$mybb->input['table']}");
		
		$buttons[] = $form->generate_submit_button("Convert Database Table");
		$form->output_submit_wrapper($buttons);
		
		$form->end();
		
		$db->set_table_prefix($old_table_prefix);
		
		$page->output_footer();
		
		exit;
	}
	
	$tables = $db->list_tables($config['database']);
	
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
	$table->construct_header("Table");
	$table->construct_header("Status", array("class" => "align_center"));
	
	foreach($mybb_tables as $key => $tablename)
	{
		if(array_key_exists($key, $not_okey))
		{
			$status = "<img src=\"styles/{$page->style}/images/icons/cross.gif\" alt\"X\" /> <a href=\"index.php?".SID."&amp;module=tools/index&amp;action=utf8_conversion&amp;table={$tablename}\">Convert Now</a>";
		}
		else
		{
			$status = "<img src=\"styles/{$page->style}/images/icons/tick.gif\" alt\"OK\" />";
		}
		$table->construct_cell("<strong>{$tablename}</strong>");
		$table->construct_cell($status, array("class" => "align_center", 'width' => '15%'));
		$table->construct_row();
	}
	
	$table->output("UTF-8 Conversion");
	
	$page->output_footer();
}

if(!$mybb->input['action'])
{
	$page->output_header("System Health");
	
	$sub_tabs['system_health'] = array(
		'title' => "System Health",
		'link' => "index.php?".SID."&amp;module=tools/stats",
		'description' => 'Here you can view information on your system\'s health.'
	);
	
	$sub_tabs['utf8_conversion'] = array(
		'title' => "UTF-8 Conversion",
		'link' => "index.php?".SID."&amp;module=tools/stats&action=utf8_conversion",
		'description' => 'This tool checks the database tables to make sure they are in the UTF-8 format and allows you to convert them if they are not.'
	);
	
	$page->output_nav_tabs($sub_tabs, 'system_health');
	
	$table = new Table;
	$table->construct_header("Totals", array("colspan" => 2));
	$table->construct_header("Attachments", array("colspan" => 2));
	
	$query = $db->simple_select("attachments", "COUNT(*) AS numattachs, SUM(filesize) as spaceused, SUM(downloads) as downloadsused", "visible='1' AND pid > '0'");
	$attachs = $db->fetch_array($query);
	
	$table->construct_cell("<strong>Total Database Size</strong>", array('width' => '25%'));
	$table->construct_cell(get_friendly_size($db->fetch_size()), array('width' => '25%'));
	$table->construct_cell("<strong>Attachment Space used</strong>", array('width' => '200'));
	$table->construct_cell(get_friendly_size($attachs['spaceused']), array('width' => '200'));
	$table->construct_row();
	
	$table->construct_cell("<strong>Total Cache Size</strong>", array('width' => '25%'));
	$table->construct_cell(get_friendly_size($cache->size_of()), array('width' => '25%'));
	$table->construct_cell("<strong>Estimated Attachment Bandwidth Usage</strong>", array('width' => '25%'));
	$table->construct_cell(get_friendly_size(round($attachs['spaceused']*$attachs['downloadsused'])), array('width' => '25%'));
	$table->construct_row();
	
	$table->construct_cell("<strong>Max Upload / POST Size</strong>", array('width' => '200'));
	$table->construct_cell(@ini_get('upload_max_filesize').' / '.@ini_get('post_max_size'), array('width' => '200'));
	$table->construct_cell("<strong>Average Attachment Size</strong>", array('width' => '25%'));
	$table->construct_cell(get_friendly_size(round($attachs['spaceused']/$attachs['numattachs'])), array('width' => '25%'));
	$table->construct_row();
	
	$table->output("Stats");
	
	$table->construct_header("Task");
	$table->construct_header("Run Time", array("width" => 200, "class" => "align_center"));
	
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
	
	$table->output("Next 3 Tasks");
	
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
	$table->construct_header("Name");
	$table->construct_header("Backup Time", array("width" => 200, "class" => "align_center"));
	
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
		$table->construct_cell("There are currently no backups made yet.", array('colspan' => 2));
		$table->construct_row();
	}
	
	
	$table->output("Existing Database Backups");
	
	if(is_writable(MYBB_ROOT.'inc/settings.php'))
	{
		$message_settings = "<span style=\"color: green;\">Writable</span>";
	}
	else
	{
		$message_settings = "<strong><span style=\"color: #C00\">Not Writable</span></strong><br />Please CHMOD to 777.";
	}
	
	if(is_writable('.'.$mybb->settings['uploadspath']))
	{
		$message_upload = "<span style=\"color: green;\">Writable</span>";
	}
	else
	{
		$message_upload = "<strong><span style=\"color: #C00\">Not Writable</span></strong><br />Please CHMOD to 777.";
		++$errors;
	}
	
	if(is_writable('../'.$mybb->settings['avataruploadpath']))
	{
		$message_avatar = "<span style=\"color: green;\">Writable</span>";
	}
	else
	{
		$message_avatar = "<strong><span style=\"color: #C00\">Not Writable</span></strong><br />Please CHMOD to 777.";
		++$errors;
	}
	
	if(is_writable(MYBB_ROOT.'inc/languages/'))
	{
		$message_language = "<span style=\"color: green;\">Writable</span>";
	}
	else
	{
		$message_language = "<strong><span style=\"color: #C00\">Not Writable</span></strong><br />Please CHMOD to 777.";
		++$errors;
	}
	
	if(is_writable(MYBB_ROOT.$config['admin_dir'].'/backups/'))
	{
		$message_backup = "<span style=\"color: green;\">Writable</span>";
	}
	else
	{
		$message_backup = "<strong><span style=\"color: #C00\">Not Writable</span></strong><br />Please CHMOD to 777.";
		++$errors;
	}
	
	
	if($errors)
	{
		$page->output_error("<strong><span style=\"color: #C00\">{$errors} of the required files and directories do not have proper CHMOD settings.</span></strong> Please change the CHMOD settings to the ones specified with the file below. For more information on CHMODing, see the <a href=\"http://wiki.mybboard.net/index.php/HowTo_Chmod\" target=\"_blank\">MyBB Wiki</a>.");
	}
	else
	{
		$page->output_success("<strong><span style=\"color: green;\">All of the required files and directories have the proper CHMOD settings.</span></strong>");
	}
	
	$table = new Table;
	$table->construct_header("File");
	$table->construct_header("Location", array("colspan" => 2, 'width' => 250));
	
	$table->construct_cell("<strong>Settings File</strong>");
	$table->construct_cell("./inc/settings.php");
	$table->construct_cell($message_settings);
	$table->construct_row();
	
	$table->construct_cell("<strong>File Uploads Directory</strong>");
	$table->construct_cell($mybb->settings['uploadspath']);
	$table->construct_cell($message_upload);
	$table->construct_row();
	
	$table->construct_cell("<strong>Avatar Uploads Directory</strong>");
	$table->construct_cell('./'.$mybb->settings['avataruploadpath']);
	$table->construct_cell($message_avatar);
	$table->construct_row();
	
	$table->construct_cell("<strong>Language Files</strong>");
	$table->construct_cell("./inc/languages");
	$table->construct_cell($message_language);
	$table->construct_row();
	
	$table->construct_cell("<strong>Backups Directory</strong>");
	$table->construct_cell('./'.$config['admin_dir'].'/backups');
	$table->construct_cell($message_backup);
	$table->construct_row();
	
	$table->output("CHMOD Files and Directories");
	
	$page->output_footer();
}
?>