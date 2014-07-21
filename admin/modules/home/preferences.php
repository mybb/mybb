<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$page->add_breadcrumb_item($lang->preferences_and_personal_notes, "index.php?module=home-preferences");

$plugins->run_hooks("admin_home_preferences_begin");

if(!$mybb->input['action'])
{
	$plugins->run_hooks("admin_home_preferences_start");

	if($mybb->request_method == "post")
	{
		$query = $db->simple_select("adminoptions", "permissions, defaultviews", "uid='{$mybb->user['uid']}'");
		$adminopts = $db->fetch_array($query);

		$sqlarray = array(
			"notes" => $db->escape_string($mybb->input['notes']),
			"cpstyle" => $db->escape_string($mybb->input['cpstyle']),
			"cplanguage" => $db->escape_string($mybb->input['cplanguage']),
			"permissions" => $db->escape_string($adminopts['permissions']),
			"defaultviews" => $db->escape_string($adminopts['defaultviews']),
			"uid" => $mybb->user['uid'],
			"codepress" => (int)$mybb->input['codepress'], // It's actually CodeMirror but for compatibility purposes lets leave it codepress
		);

		$db->replace_query("adminoptions", $sqlarray, "uid");

		$plugins->run_hooks("admin_home_preferences_start_commit");

		flash_message($lang->success_preferences_updated, 'success');
		admin_redirect("index.php?module=home-preferences");
	}

	$page->output_header($lang->preferences_and_personal_notes);

	$sub_tabs['preferences'] = array(
		'title' => $lang->preferences_and_personal_notes,
		'link' => "index.php?module=home-preferences",
		'description' => $lang->prefs_and_personal_notes_description
	);

	$page->output_nav_tabs($sub_tabs, 'preferences');

	$query = $db->simple_select("adminoptions", "notes, cpstyle, cplanguage, codepress", "uid='".$mybb->user['uid']."'", array('limit' => 1));
	$admin_options = $db->fetch_array($query);

	$form = new Form("index.php?module=home-preferences", "post");
	$dir = @opendir(MYBB_ADMIN_DIR."/styles");

	$folders = array();
	while($folder = readdir($dir))
	{
		if($folder != "." && $folder != ".." && @file_exists(MYBB_ADMIN_DIR."/styles/$folder/main.css"))
		{
			$folders[$folder] = ucfirst($folder);
		}
	}
	closedir($dir);
	ksort($folders);
	$setting_code = $form->generate_select_box("cpstyle", $folders, $admin_options['cpstyle']);

	$languages = $lang->get_languages(1);
	$language_code = $form->generate_select_box("cplanguage", $languages, $admin_options['cplanguage']);

	$table = new Table;
	$table->construct_header($lang->global_preferences);

	$table->construct_cell("<strong>{$lang->acp_theme}</strong><br /><small>{$lang->select_acp_theme}</small><br /><br />{$setting_code}");
	$table->construct_row();

	$table->construct_cell("<strong>{$lang->acp_language}</strong><br /><small>{$lang->select_acp_language}</small><br /><br />{$language_code}");
	$table->construct_row();

	$table->construct_cell("<strong>{$lang->codemirror}</strong><br /><small>{$lang->use_codemirror_desc}</small><br /><br />".$form->generate_on_off_radio('codepress', $admin_options['codepress']));
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

