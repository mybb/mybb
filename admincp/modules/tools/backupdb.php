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


// TODO
// * Generate Backup
// * Delete Backups

$page->add_breadcrumb_item("Database Backups", "index.php?".SID."&amp;module=tools/backupdb");

if($mybb->input['action'] == "backup")
{
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
	$table->construct_cell("<strong>Save Method</strong><br />\nSelect the method you would like to use to save the backup.<br /><div class=\"form_row\">".$form->generate_radio_button("method", "directory", "Backup Directory")."<br />\n".$form->generate_radio_button("method", "download", "Download", array('checked' => 1))."</div>", array('width' => '50%'));
	$table->construct_row();
	$table->construct_cell("<strong>Backup Contents</strong><br />\nSelect the information that you would like included in the backup.<br /><div class=\"form_row\">".$form->generate_radio_button("contents", "both", "Structure and Data", array('checked' => 1))."<br />\n".$form->generate_radio_button("contents", "structure", "Structure Only")."<br />\n".$form->generate_radio_button("contents", "data", "Data only")."</div>", array('width' => '50%'));
	$table->construct_row();
	$table->construct_cell("<strong>Analyze and Optimize Selected Tables</strong><br />\nWould you like the databases to be analyzed and optimized during the backup?<br /><div class=\"form_row\">".$form->generate_yes_no_radio("analyzeoptimize")."</div>", array('width' => '50%'));
	$table->construct_row();
		
	$table->output("New Database Backup");
	
	$buttons[] = $form->generate_submit_button("Perform Backup");
	$form->output_submit_wrapper($buttons);
	
	$form->end();
		
	$page->output_footer();
}

if(!$mybb->input['action'])
{	
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
		
		$table->construct_cell("<a href=\"index.php?".SID."&amp;module=tools/backupdb&amp;action=dlbackup&amp;file={$backup['file']}\">{$filename}</a>");
		$table->construct_cell(get_friendly_size(filesize(MYBB_ADMIN_DIR.'backups/'.$backup['file'])));
		$table->construct_cell($time);
		$table->construct_cell("<a href=\"index.php?".SID."&amp;module=tools/backupdb&amp;action=backup&amp;action=delete&amp;file={$backup['file']}\">Delete</a>");
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