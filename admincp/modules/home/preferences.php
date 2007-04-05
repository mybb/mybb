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

$page->add_breadcrumb_item("Preferences", "index.php?".SID."&amp;module=home/references");

if(!$mybb->input['action'])
{
	if($mybb->request_method == "post")
	{
		$sqlarray = array(
			"notes" => $db->escape_string($mybb->input['notes']),
			"cpstyle" => $db->escape_string($mybb->input['cpstyle']),
		);

		$db->update_query("adminoptions", $sqlarray, "uid='".$mybb->user['uid']."'");

	
		flash_message("The Preferences have been successfully updated.", 'success');
		admin_redirect("index.php?".SID."&module=home/preferences");
	}
	
	$page->output_header("Preferences");
	
	$sub_tabs['preferences'] = array(
		'title' => "Preferences &amp; Personal Notes",
		'link' => "index.php?".SID."&amp;module=home/preferences",
		'description' => "Here you can manage your Admin Control Panel preferences and leave personal notes for yourself."
	);

	$page->output_nav_tabs($sub_tabs, 'preferences');
	
	
	
	$query = $db->simple_select("adminoptions", "cpstyle, notes", "uid='".$mybb->user['uid']."'", array('limit' => 1));
	$admin_options = $db->fetch_array($query);
	
	$form = new Form("index.php?".SID."&amp;module=home/preferences", "post");
	$dir = @opendir(MYBB_ADMIN_DIR."/styles");
	while($folder = readdir($dir))
	{
		if($file != "." && $file != ".." && @file_exists(MYBB_ADMIN_DIR."/styles/$folder/main.css"))
		{
			$folders[$folder] = $folder;
		}
	}
	closedir($dir);
	ksort($folders);
	$setting_code = $form->generate_select_box("cpstyle", $folders, $admin_options['cpstyle']);
	
	$table = new Table;
	$table->construct_header("Admin Control Panel Theme");
	
	$table->construct_cell("Please select a theme to use in the Admin Control Panel<br />{$setting_code}");
	$table->construct_row();
	
	$table->output("Preferences");
	
	$table->construct_header("These notes are not shared with other Administrators.");
	
	$table->construct_cell($form->generate_text_area("notes", $admin_options['notes'], array('style' => 'width: 99%; height: 300px;')));
	$table->construct_row();
	
	$table->output("Personal Notes");	
	
	$buttons[] = $form->generate_submit_button("Save Personal Notes and Preferences");
	$form->output_submit_wrapper($buttons);
	
	$form->end();
	
	$page->output_footer();
}

?>