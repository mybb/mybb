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


// Allows us to refresh cache to prevent over flowing
function clear_overflow($fp, &$contents) 
{
	global $mybb;
	
	if($mybb->input['method'] == 'disk') 
	{
		if($mybb->input['filetype'] == 'gzip') 
		{
			gzwrite($fp, $contents);
		} 
		else 
		{
			fwrite($fp, $contents);
		}
	} 
	else 
	{
		if($mybb->input['filetype'] == "gzip")
		{
			echo gzencode($contents);
		}
		else
		{
			echo $contents;
		}
	}
		
	$contents = '';	
}

$page->add_breadcrumb_item("Database Backups", "index.php?".SID."&amp;module=tools/backupdb");

if($mybb->input['action'] == "dlbackup")
{
	if(empty($mybb->input['file']))
	{
		flash_message('You did not specify a database backup to download, so your request could not be performed.', 'error');
		admin_redirect("index.php?".SID."&module=tools/backupdb");
	}
	
	$file = basename($mybb->input['file']);
	$ext = get_extension($file);
		
	if(file_exists(MYBB_ADMIN_DIR.'backups/'.$file) && filetype(MYBB_ADMIN_DIR.'backups/'.$file) == 'file' && ($ext == 'gz' || $ext == 'sql'))
	{
		header('Content-disposition: attachment; filename='.$file);
		header("Content-type: ".$ext);
		header("Content-length: ".filesize(MYBB_ADMIN_DIR.'backups/'.$file));
		echo file_get_contents(MYBB_ADMIN_DIR.'backups/'.$file);
	}
	else
	{
		flash_message('An error occured while attempting to download your database backup.', 'error');
		admin_redirect("index.php?".SID."&module=tools/backupdb");
	}
}

if($mybb->input['action'] == "delete")
{
	if($mybb->input['no']) 
	{ 
		admin_redirect("index.php?".SID."&module=tools/backupdb"); 
	}
	
	if(!trim($mybb->input['file']))
	{
		flash_message('You did not enter a file to delete', 'error');
		admin_redirect("index.php?".SID."&module=tools/backupdb");
	}
	
	$file = basename($mybb->input['file']);
	
	if(!file_exists(MYBB_ADMIN_DIR.'backups/'.$file))
	{
		flash_message('You did not enter a valid promotion', 'error');
		admin_redirect("index.php?".SID."&module=tools/backupdb");
	}
	
	if($mybb->request_method == "post")
	{		
		$delete = @unlink(MYBB_ADMIN_DIR.'backups/'.$file);
			
		if($delete)
		{
			flash_message('Backup Delete Successfully', 'success');
			admin_redirect("index.php?".SID."&module=tools/backupdb");
		}
		else
		{
			flash_message('Could not delete selected backup.', 'error');
			admin_redirect("index.php?".SID."&module=tools/backupdb");
		}
	}
	else
	{
		$page->output_confirm_action("index.php?".SID."&amp;module=tools/backupdb&amp;action=delete&amp;file={$mybb->input['file']}", "Are you sure you wish to delete this backup?"); 
	}
}

if($mybb->input['action'] == "backup")
{
	if($mybb->request_method == "post")
	{
		$db->set_table_prefix('');
		
		if(!is_array($mybb->input['tables']))
		{
			$page->output_error("You did not select any tables.");
		}
		
		@set_time_limit(0);
		
		if($mybb->input['method'] == 'disk')
		{
			$file = MYBB_ADMIN_DIR.'backups/backup_'.substr(md5($mybb->user['uid'].time().random_str()), 0, 10);
			
			if($mybb->input['filetype'] == 'gzip')
			{
				if(!function_exists('gzopen')) // check zlib-ness
				{
					$page->output_error("The zlib library for PHP is not enabled, so your request could not be performed.");
				}
				
				$fp = gzopen($file.'.gz', 'w9');
			}
			else
			{
				$fp = fopen($file.'.sql', 'w');
			}
		}
		else
		{
			$file = 'backup_'.substr(md5($mybb->user['uid'].time().random_str()), 0, 10);
			if($mybb->input['filetype'] == 'gzip')
			{
				if(!function_exists('gzopen')) // check zlib-ness
				{
					$page->output_error("The zlib library for PHP is not enabled, so your request could not be performed.");
				}

				// Send headers for gzip file (do ob_start too)
				header('Content-Encoding: x-gzip');
				header('Content-Type: application/x-gzip');
				header('Content-Disposition: attachment; filename="'.$file.'.gz"');
			}
			else
			{
				// Send standard headers for .sql
				header('Content-Type: text/x-sql');
				header('Content-Disposition: attachment; filename="'.$file.'.sql"');
			}
		}
		
		$time = date('dS F Y \a\t H:i', time());
		$header = "-- MyBB Database Backup\n-- Generated: {$time}\n-- -------------------------------------\n\n";
		$contents = $header;
		foreach($mybb->input['tables'] as $table)
		{			
			if($mybb->input['analyzeoptimize'] == "yes")
			{
				$db->optimize_table($table);
				$db->analyze_table($table);
			}
			
			$field_list = array();
			$query = $db->query("SHOW FIELDS FROM ".$table);
			while($row = $db->fetch_array($query))
			{
				$field_list[] = $row['Field'];
			}
			
			$fields = implode(",", $field_list);
			if($mybb->input['contents'] != 'data')
			{
				$structure = $db->show_create_table($table).";\n";
				$contents .= $structure;
				clear_overflow($fp, $contents);
			}
			
			if($mybb->input['contents'] != 'structure')
			{
				$query = $db->simple_select($table);
				while($row = $db->fetch_array($query))
				{
					$insert = "INSERT INTO {$table} ($fields) VALUES (";
					$comma = '';
					foreach($field_list as $field)
					{
						if(!isset($row[$field]) || trim($row[$field]) == "")
						{
							$insert .= $comma."''";
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
		}
		
		if($mybb->input['method'] == 'disk')
		{
			if($mybb->input['filetype'] == 'gzip')
			{
				gzwrite($fp, $contents);
				gzclose($fp);
			}
			else
			{
				fwrite($fp, $contents);
				fclose($fp);
			}
			
			if($mybb->input['filetype'] == 'gzip')
			{
				$ext = '.gz';
			}
			else
			{
				$ext = '.sql';
			}
			
			$db->set_table_prefix(TABLE_PREFIX);
			
			$file_from_admindir = 'index.php?'.SID.'&amp;module=tools/backupdb&amp;action=dlbackup&amp;file='.basename($file).$ext;
			flash_message("Backup generated successfully.<br /><br />The backup was saved to:<br />{$file}{$ext}<br /><br /><a href=\"{$file_from_admindir}\">Download this backup</a>.", 'success');
			admin_redirect("index.php?".SID."&module=tools/backupdb");
		}
		else
		{
			if($mybb->input['filetype'] == 'gzip')
			{
				echo gzencode($contents);
			}
			else
			{
				echo $contents;
			}
		}
		
		exit;
	}
	
	$page->extra_header = "	<script type=\"text/javascript\" language=\"Javascript\">
	function changeSelection(action, prefix)
	{
		var select_box = document.getElementById('table_select');
		
		for(var i = 0; i < select_box.length; i++)
		{
			if(action == 'select')
			{
				document.table_selection.table_select[i].selected = true;
			}
			else if(action == 'deselect')
			{
				document.table_selection.table_select[i].selected = false;
			}
			else if(action == 'forum' && prefix != 0)
			{
				var row = document.table_selection.table_select[i].value;
				var subString = row.substring(prefix.length, 0);
				if(subString == prefix)
				{
					document.table_selection.table_select[i].selected = true;
				}
			}
		}
	}
	</script>\n";
	
	$page->add_breadcrumb_item("New Database Backup");
	$page->output_header("New Database Backup");
	
	$sub_tabs['new_backup'] = array(
		'title' => "New Backup",
		'link' => "index.php?".SID."&amp;module=tools/backupdb&amp;action=backup",
		'description' => 'Here you can make new backups of your database'
	);
	
	$page->output_nav_tabs($sub_tabs, 'new_backup');
	
	// Check if file is writable, before allowing submission
	if(!is_writable(MYBB_ADMIN_DIR."/backups"))
	{
		$lang->update_button = '';
		$page->output_alert("Your backups directory (within the Admin CP directory) is not writable. You cannot save backups on the server.");
		$cannot_write = true;
	}
	
	$table = new Table;
	$table->construct_header("Table Selection");
	$table->construct_header("Backup Options");
	
	$table_selects = array();
	$table_list = $db->list_tables($config['database']);
	foreach($table_list as $id => $table_name)
	{
		$table_selects[$table_name] = $table_name;
	}
	
	$form = new Form("index.php?".SID."&amp;module=tools/backupdb&amp;action=backup", "post", 0, "table_selection", "table_selection");
	
	$table->construct_cell("You may select the database tables you wish to perform this action on here. Hold down CTRL to select multiple tables.\n<br /><br />\n<a href=\"javascript:changeSelection('select', 0);\">Select All</a><br />\n<a href=\"javascript:changeSelection('deselect', 0);\">Deselect All</a><br />\n<a href=\"javascript:changeSelection('forum', '".TABLE_PREFIX."');\">Select Forum Tables</a>\n<br /><br />\n<div class=\"form_row\">".$form->generate_select_box("tables[]", $table_selects, false, array('multiple' => true, 'id' => 'table_select', 'size' => 20))."</div>", array('rowspan' => 5, 'width' => '50%'));
	$table->construct_row();
	
	$table->construct_cell("<strong>File Type</strong><br />\nSelect the file type you would like the database backup saved as.<br />\n<div class=\"form_row\">".$form->generate_radio_button("filetype", "gzip", "GZIP Compressed", array('checked' => 1))."<br />\n".$form->generate_radio_button("filetype", "plain", "Plain Text")."</div>", array('width' => '50%'));
	$table->construct_row();
	$table->construct_cell("<strong>Save Method</strong><br />\nSelect the method you would like to use to save the backup.<br /><div class=\"form_row\">".$form->generate_radio_button("method", "disk", "Backup Directory")."<br />\n".$form->generate_radio_button("method", "download", "Download", array('checked' => 1))."</div>", array('width' => '50%'));
	$table->construct_row();
	$table->construct_cell("<strong>Backup Contents</strong><br />\nSelect the information that you would like included in the backup.<br /><div class=\"form_row\">".$form->generate_radio_button("contents", "both", "Structure and Data", array('checked' => 1))."<br />\n".$form->generate_radio_button("contents", "structure", "Structure Only")."<br />\n".$form->generate_radio_button("contents", "data", "Data only")."</div>", array('width' => '50%'));
	$table->construct_row();
	$table->construct_cell("<strong>Analyze and Optimize Selected Tables</strong><br />\nWould you like the selected tables to be analyzed and optimized during the backup?<br /><div class=\"form_row\">".$form->generate_yes_no_radio("analyzeoptimize")."</div>", array('width' => '50%'));
	$table->construct_row();
		
	$table->output("New Database Backup");
	
	$buttons[] = $form->generate_submit_button("Perform Backup");
	$form->output_submit_wrapper($buttons);
	
	$form->end();
		
	$page->output_footer();
}

if(!$mybb->input['action'])
{	
	$page->add_breadcrumb_item("Backups");
	$page->output_header("Database Backups");
	
	$sub_tabs['database_backup'] = array(
		'title' => "Database Backups",
		'link' => "index.php?".SID."&amp;module=tools/backupdb",
		'description' => "Here you find a listing of the database backups that are currently stored on your web server in the MyBB Backups directory."
	);
	
	$sub_tabs['new_backup'] = array(
		'title' => "New Backup",
		'link' => "index.php?".SID."&amp;module=tools/backupdb&amp;action=backup",
	);
	
	$page->output_nav_tabs($sub_tabs, 'database_backup');
	
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
	ksort($backups);
	
	$table = new Table;
	$table->construct_header("Backup Filename");
	$table->construct_header("File Size");
	$table->construct_header("Creation Date");
	$table->construct_header("Controls");
	
	foreach($backups as $backup)
	{
		if($backup['time'])
		{
			$time = my_date($mybb->settings['dateformat'].", ".$mybb->settings['timeformat'], $backup['time']);
		}
		else
		{
			$time = "-";
		}
		
		$table->construct_cell("<a href=\"index.php?".SID."&amp;module=tools/backupdb&amp;action=dlbackup&amp;file={$backup['file']}\">{$backup['file']}</a>");
		$table->construct_cell(get_friendly_size(filesize(MYBB_ADMIN_DIR.'backups/'.$backup['file'])));
		$table->construct_cell($time);
		$table->construct_cell("<a href=\"index.php?".SID."&amp;module=tools/backupdb&amp;action=backup&amp;action=delete&amp;file={$backup['file']}\" onclick=\"return AdminCP.deleteConfirmation(this, 'Are you sure you wish to delete this backup?')\">Delete</a>");
		$table->construct_row();
	}
	
	if($count == 0)
	{
		$table->construct_cell("There are currently no backups made yet.", array('colspan' => 4));
		$table->construct_row();
	}
	
	
	$table->output("Existing Database Backups");
		
	$page->output_footer();
}

?>