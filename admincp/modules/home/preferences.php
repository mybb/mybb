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

$page->add_breadcrumb_item($lang->preferences_and_personal_notes, "index.php?".SID."&amp;module=home/preferences");

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
	
	$page->output_header($lang->preferences_and_personal_notes);
	
	$sub_tabs['preferences'] = array(
		'title' => $lang->preferences_and_personal_notes,
		'link' => "index.php?".SID."&amp;module=home/preferences",
		'description' => $lang->prefs_and_personal_notes_description
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
	$table->construct_header($lang->acp_theme);
	
	$table->construct_cell($lang->select_acp_theme."<br />{$setting_code}");
	$table->construct_row();
	
	$table->output($lang->preferences);
	
	$table->construct_header($lang->notes_not_shared);
	
	$table->construct_cell($form->generate_text_area("notes", $admin_options['notes'], array('style' => 'width: 99%; height: 300px;')));
	$table->construct_row();
	
	$table->output($lang->personal_notes);	
	
	$buttons[] = $form->generate_submit_button($lang->save_notes_and_prefs);
	$form->output_submit_wrapper($buttons);
	
	$form->end();
	
	$page->output_footer();
}

?>