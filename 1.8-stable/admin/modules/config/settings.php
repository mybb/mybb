<?php
/**
 * MyBB 1.6
 * Copyright 2010 MyBB Group, All Rights Reserved
 *
 * Website: http://mybb.com
 * License: http://mybb.com/about/license
 *
 * $Id: settings.php 5617 2011-09-20 13:50:05Z Tomm $
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$page->add_breadcrumb_item($lang->board_settings, "index.php?module=config-settings");

$plugins->run_hooks("admin_config_settings_begin");

/*
// Delete all duplicate settings and setting groups
if($mybb->input['action'] == "delete_duplicates")
{
	$query = $db->query("
		DELETE s1
		FROM ".TABLE_PREFIX."settings s1
		INNER JOIN ".TABLE_PREFIX."settings s2 ON (s2.name=s1.name AND s2.sid!=s1.sid AND s2.sid < s1.sid)
	");
	$query = $db->query("
		DELETE g1
		FROM ".TABLE_PREFIX."settinggroups g1
		INNER JOIN ".TABLE_PREFIX."settinggroups g2 ON (g2.title=g1.title AND g2.gid!=g1.gid AND g2.gid < g1.gid)
	");
	rebuild_settings();
	
	$plugins->run_hooks("admin_config_settings_delete_duplicates_commit");
	
	// Log admin action
	log_admin_action();

	flash_message($lang->success_duplicate_settings_deleted, 'success');
	admin_redirect("index.php?module=config-settings&action=manage");
}*/
// ^^  Code, Please go away. Forever.

// Creating a new setting group
if($mybb->input['action'] == "addgroup")
{
	$plugins->run_hooks("admin_config_settings_addgroup");
	
	if($mybb->request_method == "post")
	{
		// Validate title
		if(!trim($mybb->input['title']))
		{
			$errors[] = $lang->error_missing_group_title;
		}
		
		// Validate identifier
		if(!trim($mybb->input['name']))
		{
			$errors[] = $lang->error_missing_group_name;
		}
		$query = $db->simple_select("settinggroups", "title", "name='".$db->escape_string($mybb->input['name'])."'");
		if($db->num_rows($query) > 0)
		{
			$dup_group_title = $db->fetch_field($query, 'title');
			$errors[] = $lang->sprintf($lang->error_duplicate_group_name, $dup_group_title);
		}

		if(!$errors)
		{
			$new_setting_group = array(
				"name" => $db->escape_string($mybb->input['name']),
				"title" => $db->escape_string($mybb->input['title']),
				"description" => $db->escape_string($mybb->input['description']),
				"disporder" => intval($mybb->input['disporder']),
				"isdefault" => 0
			);
			$gid = $db->insert_query("settinggroups", $new_setting_group);
			
			$plugins->run_hooks("admin_config_settings_addgroup_commit");

			// Log admin action
			log_admin_action($gid, $mybb->input['name']);

			flash_message($lang->success_setting_group_added, 'success');
			admin_redirect("index.php?module=config-settings&action=manage");
		}
	}

	$page->add_breadcrumb_item($lang->add_new_setting_group);
	$page->output_header($lang->board_settings." - ".$lang->add_new_setting_group);
	
	$sub_tabs['change_settings'] = array(
		'title' => $lang->change_settings,
		'link' => "index.php?module=config-settings"
	);
	
	$sub_tabs['add_setting'] = array(
		'title' => $lang->add_new_setting,
		'link' => "index.php?module=config-settings&amp;action=add"
	);
	
	$sub_tabs['add_setting_group'] = array(
		'title' => $lang->add_new_setting_group,
		'link' => "index.php?module=config-settings&amp;action=addgroup",
		'description' => $lang->add_new_setting_group_desc
	);
	
	$sub_tabs['modify_setting'] = array(
		'title' => $lang->modify_existing_settings,
		'link' => "index.php?module=config-settings&amp;action=manage"
	);

	$page->output_nav_tabs($sub_tabs, 'add_setting_group');

	$form = new Form("index.php?module=config-settings&amp;action=addgroup", "post", "add");

	if($errors)
	{
		$page->output_inline_error($errors);
	}

	$form_container = new FormContainer($lang->add_new_setting_group);
	$form_container->output_row($lang->title." <em>*</em>", "", $form->generate_text_box('title', $mybb->input['title'], array('id' => 'title')), 'title');
	$form_container->output_row($lang->description, "", $form->generate_text_area('description', $mybb->input['description'], array('id' => 'description')), 'description');
	$form_container->output_row($lang->display_order, "", $form->generate_text_box('disporder', $mybb->input['disporder'], array('id' => 'disporder')), 'disporder');
	$form_container->output_row($lang->name." <em>*</em>", $lang->group_name_desc, $form->generate_text_box('name', $mybb->input['name'], array('id' => 'name')), 'name');
	$form_container->end();

	$buttons[] = $form->generate_submit_button($lang->insert_new_setting_group);
	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

// Edit setting group
if($mybb->input['action'] == "editgroup")
{
	$plugins->run_hooks("admin_config_settings_editgroup");
	
	$query = $db->simple_select("settinggroups", "*", "gid='".intval($mybb->input['gid'])."'");
	$group = $db->fetch_array($query);

	// Does the setting not exist?
	if(!$group['gid'])
	{
		flash_message($lang->error_invalid_gid2, 'error');
		admin_redirect("index.php?module=config-settings&action=manage");
	}
	// Prevent editing of default
	if($group['isdefault'] == 1)
	{
		flash_message($lang->error_cannot_edit_default, 'error');
		admin_redirect("index.php?module=config-settings&action=manage");
	}
	
	// Do edit?
	if($mybb->request_method == "post")
	{
		// Validate title
		if(!trim($mybb->input['title']))
		{
			$errors[] = $lang->error_missing_group_title;
		}
		
		// Validate identifier
		if(!trim($mybb->input['name']))
		{
			$errors[] = $lang->error_missing_group_name;
		}
		$query = $db->simple_select("settinggroups", "title", "name='".$db->escape_string($mybb->input['name'])."' AND gid != '{$group['gid']}'");
		if($db->num_rows($query) > 0)
		{
			$dup_group_title = $db->fetch_field($query, 'title');
			$errors[] = $lang->sprintf($lang->error_duplicate_group_name, $dup_group_title);
		}

		if(!$errors)
		{
			$update_setting_group = array(
				"name" => $db->escape_string($mybb->input['name']),
				"title" => $db->escape_string($mybb->input['title']),
				"description" => $db->escape_string($mybb->input['description']),
				"disporder" => intval($mybb->input['disporder']),
			);
			
			$db->update_query("settinggroups", $update_setting_group, "gid='{$group['gid']}'");
			
			$plugins->run_hooks("admin_config_settings_editgroup_commit");

			// Log admin action
			log_admin_action($group['gid'], $mybb->input['name']);

			flash_message($lang->success_setting_group_updated, 'success');
			admin_redirect("index.php?module=config-settings&action=manage");
		}
	}

	$page->add_breadcrumb_item($lang->edit_setting_group);
	$page->output_header($lang->board_settings." - ".$lang->edit_setting_group);
	
	$sub_tabs['edit_setting_group'] = array(
		'title' => $lang->edit_setting_group,
		'link' => "index.php?module=config-settings&amp;action=editgroup&amp;gid={$group['gid']}",
		'description' => $lang->edit_setting_group_desc
	);

	$page->output_nav_tabs($sub_tabs, 'edit_setting_group');

	$form = new Form("index.php?module=config-settings&amp;action=editgroup", "post", "editgroup");

	echo $form->generate_hidden_field("gid", $group['gid']);
	
	if($errors)
	{
		$group_data = $mybb->input;
		$page->output_inline_error($errors);
	}
	else
	{
		$group_data = $group;
	}

	$form_container = new FormContainer($lang->edit_setting_group);
	$form_container->output_row($lang->title." <em>*</em>", "", $form->generate_text_box('title', $group_data['title'], array('id' => 'title')), 'title');
	$form_container->output_row($lang->description, "", $form->generate_text_area('description', $group_data['description'], array('id' => 'description')), 'description');
	$form_container->output_row($lang->display_order, "", $form->generate_text_box('disporder', $group_data['disporder'], array('id' => 'disporder')), 'disporder');
	$form_container->output_row($lang->name." <em>*</em>", $lang->group_name_desc, $form->generate_text_box('name', $group_data['name'], array('id' => 'name')), 'name');
	$form_container->end();

	$buttons[] = $form->generate_submit_button($lang->update_setting_group);
	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

// Delete Setting Group
if($mybb->input['action'] == "deletegroup")
{
	$plugins->run_hooks("admin_config_settings_deletegroup");
	
	$query = $db->simple_select("settinggroups", "*", "gid='".intval($mybb->input['gid'])."'");
	$group = $db->fetch_array($query);

	// Does the setting group not exist?
	if(!$group['gid'])
	{
		flash_message($lang->error_invalid_gid2, 'error');
		admin_redirect("index.php?module=config-settings&action=manage");
	}
	// Prevent deletion of default
	if($group['isdefault'] == 1)
	{
		flash_message($lang->error_cannot_edit_default, 'error');
		admin_redirect("index.php?module=config-settings&action=manage");
	}
	
	// User clicked no
	if($mybb->input['no'])
	{
		admin_redirect("index.php?module=config-settings&action=manage");
	}

	if($mybb->request_method == "post")
	{
		// Delete the setting group and its settings
		$db->delete_query("settinggroups", "gid='{$group['gid']}'");
		$db->delete_query("settings", "gid='{$group['gid']}'");
		
		rebuild_settings();
		
		$plugins->run_hooks("admin_config_settings_deletegroup_commit");

		// Log admin action
		log_admin_action($group['gid'], $group['name']);

		flash_message($lang->success_setting_group_deleted, 'success');
		admin_redirect("index.php?module=config-settings&action=manage");
	}
	else
	{
		$page->output_confirm_action("index.php?module=config-settings&amp;action=deletegroup&amp;gid={$group['gid']}", $lang->confirm_setting_group_deletion);
	}
}

// Creating a new setting
if($mybb->input['action'] == "add")
{
	$plugins->run_hooks("admin_config_settings_add");
	
	if($mybb->request_method == "post")
	{
		if(!trim($mybb->input['title']))
		{
			$errors[] = $lang->error_missing_title;
		}

		$query = $db->simple_select("settinggroups", "gid", "gid='".intval($mybb->input['gid'])."'");
		$gid = $db->fetch_field($query, 'gid');
		if(!$gid)
		{
			$errors[] = $lang->error_invalid_gid;
		}

		if(!trim($mybb->input['name']))
		{
			$errors[] = $lang->error_missing_name;
		}
		$query = $db->simple_select("settings", "title", "name='".$db->escape_string($mybb->input['name'])."'");
		if($db->num_rows($query) > 0)
		{
			$dup_setting_title = $db->fetch_field($query, 'title');
			$errors[] = $lang->sprintf($lang->error_duplicate_name, $dup_setting_title);
		}

		// do some type filtering
		$mybb->input['type'] = str_replace("\n", "", $mybb->input['type']);
		if(strtolower(substr($mybb->input['type'], 0, 3)) == "php")
		{
			$mybb->input['type'] = "";
		}

		if(!$mybb->input['type'])
		{
			$errors[] = $lang->error_invalid_type;
		}

		if(!$errors)
		{
			if($mybb->input['type'] == "custom")
			{
				$options_code = $mybb->input['extra'];
			}
			else if($mybb->input['extra'])
			{
				$options_code = "{$mybb->input['type']}\n{$mybb->input['extra']}";
			}
			else
			{
				$options_code = $mybb->input['type'];
			}
			
			$new_setting = array(
				"name" => $db->escape_string($mybb->input['name']),
				"title" => $db->escape_string($mybb->input['title']),
				"description" => $db->escape_string($mybb->input['description']),
				"optionscode" => $db->escape_string($options_code),
				"value" => $db->escape_string($mybb->input['value']),
				"disporder" => intval($mybb->input['disporder']),
				"gid" => intval($mybb->input['gid'])
			);
			
			$sid = $db->insert_query("settings", $new_setting);
			rebuild_settings();
			
			$plugins->run_hooks("admin_config_settings_add_commit");

			// Log admin action
			log_admin_action($sid, $mybb->input['title']);

			flash_message($lang->success_setting_added, 'success');
			admin_redirect("index.php?module=config-settings&action=manage");
		}
	}

	$page->add_breadcrumb_item($lang->add_new_setting);
	$page->output_header($lang->board_settings." - ".$lang->add_new_setting);
	
	$sub_tabs['change_settings'] = array(
		'title' => $lang->change_settings,
		'link' => "index.php?module=config-settings"
	);
	
	$sub_tabs['add_setting'] = array(
		'title' => $lang->add_new_setting,
		'link' => "index.php?module=config-settings&amp;action=add",
		'description' => $lang->add_new_setting_desc
	);
	
	$sub_tabs['add_setting_group'] = array(
		'title' => $lang->add_new_setting_group,
		'link' => "index.php?module=config-settings&amp;action=addgroup"
	);
	
	$sub_tabs['modify_setting'] = array(
		'title' => $lang->modify_existing_settings,
		'link' => "index.php?module=config-settings&amp;action=manage"
	);

	$page->output_nav_tabs($sub_tabs, 'add_setting');

	$form = new Form("index.php?module=config-settings&amp;action=add", "post", "add");

	if($errors)
	{
		$page->output_inline_error($errors);
	}

	$form_container = new FormContainer($lang->add_new_setting);
	$form_container->output_row($lang->title." <em>*</em>", "", $form->generate_text_box('title', $mybb->input['title'], array('id' => 'title')), 'title');
	$form_container->output_row($lang->description, "", $form->generate_text_area('description', $mybb->input['description'], array('id' => 'description')), 'description');
	
	$query = $db->simple_select("settinggroups", "*", "", array('order_by' => 'disporder'));
	while($group = $db->fetch_array($query))
	{
		$options[$group['gid']] = $group['title'];
	}
	$form_container->output_row($lang->group." <em>*</em>", "", $form->generate_select_box("gid", $options, $mybb->input['gid'], array('id' => 'gid')), 'gid');
	$form_container->output_row($lang->display_order, "", $form->generate_text_box('disporder', $mybb->input['disporder'], array('id' => 'disporder')), 'disporder');

	$form_container->output_row($lang->name." <em>*</em>", $lang->name_desc, $form->generate_text_box('name', $mybb->input['name'], array('id' => 'name')), 'name');

	$setting_types = array(
		"text" => $lang->text,
		"textarea" => $lang->textarea,
		"yesno" => $lang->yesno,
		"onoff" => $lang->onoff,
		"select" => $lang->select,
		"radio" => $lang->radio,
		"checkbox" => $lang->checkbox,
		"language" => $lang->language_selection_box,
		"adminlanguage" => $lang->adminlanguage,
		"cpstyle" => $lang->cpstyle,
		//"php" => $lang->php // Internal Use Only
	);

	$form_container->output_row($lang->type." <em>*</em>", "", $form->generate_select_box("type", $setting_types, $mybb->input['type'], array('id' => 'type')), 'type');
	$form_container->output_row($lang->extra, $lang->extra_desc, $form->generate_text_area('extra', $mybb->input['extra'], array('id' => 'extra')), 'extra', array(), array('id' => 'row_extra'));
	$form_container->output_row($lang->value, "", $form->generate_text_area('value', $mybb->input['value'], array('id' => 'value')), 'value');
	$form_container->end();

	$buttons[] = $form->generate_submit_button($lang->insert_new_setting);
	$form->output_submit_wrapper($buttons);
	$form->end();
	
	echo '<script type="text/javascript" src="./jscripts/peeker.js"></script>
	<script type="text/javascript">Event.observe(window, "load", function() {var peeker = new Peeker($("type"), $("row_extra"), /select|radio|checkbox|php/, false);});
		// Add a star to the extra row since the "extra" is required if the box is shown
		add_star("row_extra");
	</script>';

	$page->output_footer();
}

// Editing a particular setting
if($mybb->input['action'] == "edit")
{
	$plugins->run_hooks("admin_config_settings_edit");
	
	$query = $db->simple_select("settings", "*", "sid='".intval($mybb->input['sid'])."'");
	$setting = $db->fetch_array($query);

	// Does the setting not exist?
	if(!$setting['sid'])
	{
		flash_message($lang->error_invalid_sid, 'error');
		admin_redirect("index.php?module=config-settings");
	}
	
	// Prevent editing of default
	if($setting['isdefault'] == 1)
	{
		flash_message($lang->error_cannot_edit_default, 'error');
		admin_redirect("index.php?module=config-settings&action=manage");
	}
	$type = explode("\n", $setting['optionscode'], 2);
	$type = trim($type[0]);
	if($type == "php")
	{
		flash_message($lang->error_cannot_edit_php, 'error');
		admin_redirect("index.php?module=config-settings&action=manage");
	}
	
	if($mybb->request_method == "post")
	{
		if(!trim($mybb->input['title']))
		{
			$errors[] = $lang->error_missing_title;
		}

		if(!trim($mybb->input['name']))
		{
			$errors[] = $lang->error_missing_name;
		}
		$query = $db->simple_select("settings", "title", "name='".$db->escape_string($mybb->input['name'])."' AND sid != '{$setting['sid']}'");
		if($db->num_rows($query) > 0)
		{
			$dup_setting_title = $db->fetch_field($query, 'title');
			$errors[] = $lang->sprintf($lang->error_duplicate_name, $dup_setting_title);
		}
		
		// do some type filtering
		$mybb->input['type'] = str_replace("\n", "", $mybb->input['type']);
		if(strtolower(substr($mybb->input['type'], 0, 3)) == "php")
		{
			$mybb->input['type'] = "";
		}
		
		if(!$mybb->input['type'])
		{
			$errors[] = $lang->error_invalid_type;
		}

		if(!$errors)
		{
			if($mybb->input['type'] == "custom")
			{
				$options_code = $mybb->input['extra'];
			}
			else if($mybb->input['extra'])
			{
				$options_code = "{$mybb->input['type']}\n{$mybb->input['extra']}";
			}
			else
			{
				$options_code = $mybb->input['type'];
			}
			$updated_setting = array(
				"name" => $db->escape_string($mybb->input['name']),
				"title" => $db->escape_string($mybb->input['title']),
				"description" => $db->escape_string($mybb->input['description']),
				"optionscode" => $db->escape_string($options_code),
				"value" => $db->escape_string($mybb->input['value']),
				"disporder" => intval($mybb->input['disporder']),
				"gid" => intval($mybb->input['gid'])
			);
			$db->update_query("settings", $updated_setting, "sid='{$mybb->input['sid']}'");
			rebuild_settings();
			
			$plugins->run_hooks("admin_config_settings_edit_commit");

			// Log admin action
			log_admin_action($setting['sid'], $mybb->input['title']);

			flash_message($lang->success_setting_updated, 'success');
			admin_redirect("index.php?module=config-settings&action=manage");
		}
	}

	$page->add_breadcrumb_item($lang->edit_setting);
	$page->output_header($lang->board_settings." - ".$lang->edit_setting);
	
	$sub_tabs['change_settings'] = array(
		'title' => $lang->change_settings,
		'link' => "index.php?module=config-settings",
	);
	
	$sub_tabs['add_setting'] = array(
		'title' => $lang->add_new_setting,
		'link' => "index.php?module=config-settings&amp;action=add"
	);
	
	$sub_tabs['add_setting_group'] = array(
		'title' => $lang->add_new_setting_group,
		'link' => "index.php?module=config-settings&amp;action=addgroup"
	);
	
	$sub_tabs['modify_setting'] = array(
		'title' => $lang->modify_existing_settings,
		'link' => "index.php?module=config-settings&amp;action=manage",
		'description' => $lang->modify_existing_settings_desc
	);

	$page->output_nav_tabs($sub_tabs, 'modify_setting');

	$form = new Form("index.php?module=config-settings&amp;action=edit", "post", "edit");

	echo $form->generate_hidden_field("sid", $setting['sid']);
	
	if($errors)
	{
		$setting_data = $mybb->input;
		$page->output_inline_error($errors);
	}
	else
	{
		$setting_data = $setting;
		$type = explode("\n", $setting['optionscode'], 2);
		$setting_data['type'] = trim($type[0]);
		$setting_data['extra'] = trim($type[1]);
	}

	$form_container = new FormContainer($lang->modify_setting);
	$form_container->output_row($lang->title." <em>*</em>", "", $form->generate_text_box('title', $setting_data['title'], array('id' => 'title')), 'title');
	$form_container->output_row($lang->description, "", $form->generate_text_area('description', $setting_data['description'], array('id' => 'description')), 'description');
	
	$query = $db->simple_select("settinggroups", "*", "", array('order_by' => 'disporder'));
	while($group = $db->fetch_array($query))
	{
		$options[$group['gid']] = $group['title'];
	}
	$form_container->output_row($lang->group." <em>*</em>", "", $form->generate_select_box("gid", $options, $setting_data['gid'], array('id' => 'gid')), 'gid');
	$form_container->output_row($lang->display_order, "", $form->generate_text_box('disporder', $setting_data['disporder'], array('id' => 'disporder')), 'disporder');
	$form_container->end();

	$form_container = new FormContainer($lang->setting_configuration, 1);
	$form_container->output_row($lang->name." <em>*</em>", $lang->name_desc, $form->generate_text_box('name', $setting_data['name'], array('id' => 'name')), 'name');

	$setting_types = array(
		"text" => $lang->text,
		"textarea" => $lang->textarea,
		"yesno" => $lang->yesno,
		"onoff" => $lang->onoff,
		"select" => $lang->select,
		"radio" => $lang->radio,
		"checkbox" => $lang->checkbox,
		"language" => $lang->language_selection_box,
		"adminlanguage" => $lang->adminlanguage,
		"cpstyle" => $lang->cpstyle,
		//"php" => $lang->php // Internal Use Only
	);

	$form_container->output_row($lang->type." <em>*</em>", "", $form->generate_select_box("type", $setting_types, $setting_data['type'], array('id' => 'type')), 'type');
	$form_container->output_row($lang->extra, $lang->extra_desc, $form->generate_text_area('extra', $setting_data['extra'], array('id' => 'extra')), 'extra', array(), array('id' => 'row_extra'));
	$form_container->output_row($lang->value, '', $form->generate_text_area('value', $setting_data['value'], array('id' => 'value')), 'value');
	$form_container->end();

	$buttons[] = $form->generate_submit_button($lang->update_setting);
	$form->output_submit_wrapper($buttons);
	$form->end();
	
	echo '<script type="text/javascript" src="./jscripts/peeker.js"></script>
	<script type="text/javascript">Event.observe(window, "load", function() {var peeker = new Peeker($("type"), $("row_extra"), /select|radio|checkbox|php/, false);});
		// Add a star to the extra row since the "extra" is required if the box is shown
		add_star("row_extra");
	</script>';

	$page->output_footer();
}

// Delete Setting
if($mybb->input['action'] == "delete")
{
	$plugins->run_hooks("admin_config_settings_delete");
	
	$query = $db->simple_select("settings", "*", "sid='".intval($mybb->input['sid'])."'");
	$setting = $db->fetch_array($query);

	// Does the setting not exist?
	if(!$setting['sid'])
	{
		flash_message($lang->error_invalid_sid, 'error');
		admin_redirect("index.php?module=config-settings&action=manage");
	}
	
	
	// Prevent editing of default
	if($setting['isdefault'] == 1)
	{
		flash_message($lang->error_cannot_edit_default, 'error');
		admin_redirect("index.php?module=config-settings&action=manage");
	}
	
	// User clicked no
	if($mybb->input['no'])
	{
		admin_redirect("index.php?module=config-settings&action=manage");
	}

	if($mybb->request_method == "post")
	{
		// Delete the setting
		$db->delete_query("settings", "sid='{$setting['sid']}'");
		
		rebuild_settings();
		
		$plugins->run_hooks("admin_config_settings_delete_commit");

		// Log admin action
		log_admin_action($setting['sid'], $setting['title']);

		flash_message($lang->success_setting_deleted, 'success');
		admin_redirect("index.php?module=config-settings&action=manage");
	}
	else
	{
		$page->output_confirm_action("index.php?module=config-settings&amp;action=delete&amp;sid={$setting['sid']}", $lang->confirm_setting_deletion);
	}
}

// Modify Existing Settings
if($mybb->input['action'] == "manage")
{
	$plugins->run_hooks("admin_config_settings_manage");
	
	// Update orders
	if($mybb->request_method == "post")
	{
		if(is_array($mybb->input['group_disporder']))
		{
			foreach($mybb->input['group_disporder'] as $gid => $new_order)
			{
				$gid = intval($gid);
				$update_group = array('disporder' => intval($new_order));
				$db->update_query("settinggroups", $update_group, "gid={$gid}");
			}
		}
		
		if(is_array($mybb->input['setting_disporder']))
		{
			foreach($mybb->input['setting_disporder'] as $sid => $new_order)
			{
				$sid = intval($sid);
				$update_setting = array('disporder' => intval($new_order));
				$db->update_query("settings", $update_setting, "sid={$sid}");
			}
		}
		
		$plugins->run_hooks("admin_config_settings_manage_commit");

		// Log admin action
		log_admin_action();
		
		flash_message($lang->success_display_orders_updated, 'success');
		admin_redirect("index.php?module=config-settings&action=manage");
	}
	
	$page->add_breadcrumb_item($lang->modify_existing_settings);
	$page->output_header($lang->board_settings." - ".$lang->modify_existing_settings);
	
	$sub_tabs['change_settings'] = array(
		'title' => $lang->change_settings,
		'link' => "index.php?module=config-settings",
	);
	
	$sub_tabs['add_setting'] = array(
		'title' => $lang->add_new_setting,
		'link' => "index.php?module=config-settings&amp;action=add"
	);
	
	$sub_tabs['add_setting_group'] = array(
		'title' => $lang->add_new_setting_group,
		'link' => "index.php?module=config-settings&amp;action=addgroup"
	);
	
	$sub_tabs['modify_setting'] = array(
		'title' => $lang->modify_existing_settings,
		'link' => "index.php?module=config-settings&amp;action=manage",
		'description' => $lang->modify_existing_settings_desc
	);

	$page->output_nav_tabs($sub_tabs, 'modify_setting');
	
	// Cache settings
	$settings_cache = array();
	$query = $db->simple_select("settings", "sid, name, title, disporder, gid, isdefault", "", array('order_by' => 'disporder', 'order_dir' => 'asc'));
	while($setting = $db->fetch_array($query))
	{
		$settings_cache[$setting['gid']][] = $setting;
	}

	$form = new Form("index.php?module=config-settings&amp;action=manage", "post", "edit");
	
	$table = new Table;

	$table->construct_header($lang->setting_group_setting);
	$table->construct_header($lang->order, array('class' => 'align_center', 'style' => 'width: 5%'));
	$table->construct_header($lang->controls, array('class' => 'align_center', 'style' => 'width: 200px'));
	
	// Generate table
	$query = $db->simple_select("settinggroups", "*", "", array('order_by' => 'disporder', 'order_dir' => 'asc'));
	while($group = $db->fetch_array($query))
	{
		// Make setting group row
		// Translated?
		$group_lang_var = "setting_group_{$group['name']}";
		if($lang->$group_lang_var)
		{
			$group_title = htmlspecialchars_uni($lang->$group_lang_var);
		}
		else
		{
			$group_title = htmlspecialchars_uni($group['title']);
		}
		$table->construct_cell("<strong>{$group_title}</strong>", array('id' => "group{$group['gid']}"));
		$table->construct_cell($form->generate_text_box("group_disporder[{$group['gid']}]", $group['disporder'], array('style' => 'width: 80%; font-weight: bold', 'class' => 'align_center')));
		// Only show options if not a default setting group
		if($group['isdefault'] != 1)
		{
			$popup = new PopupMenu("group_{$group['gid']}", $lang->options);
			$popup->add_item($lang->edit_setting_group, "index.php?module=config-settings&amp;action=editgroup&amp;gid={$group['gid']}");
			$popup->add_item($lang->delete_setting_group, "index.php?module=config-settings&amp;action=deletegroup&amp;gid={$group['gid']}&amp;my_post_key={$mybb->post_code}", "return AdminCP.deleteConfirmation(this, '{$lang->confirm_setting_group_deletion}')");
			$table->construct_cell($popup->fetch(), array('class' => 'align_center'));
		}
		else
		{
			$table->construct_cell('');
		}
		$table->construct_row(array('class' => 'alt_row', 'no_alt_row' => 1));
		
		// Make rows for each setting in the group
		if(is_array($settings_cache[$group['gid']]))
		{
			foreach($settings_cache[$group['gid']] as $setting)
			{
				$setting_lang_var = "setting_{$setting['name']}";
				if($lang->$setting_lang_var)
				{
					$setting_title = htmlspecialchars_uni($lang->$setting_lang_var);
				}
				else
				{
					$setting_title = htmlspecialchars_uni($setting['title']);
				}
				$table->construct_cell($setting_title, array('style' => 'padding-left: 40px;'));
				$table->construct_cell($form->generate_text_box("setting_disporder[{$setting['sid']}]", $setting['disporder'], array('style' => 'width: 80%', 'class' => 'align_center')));
				// Only show options if not a default setting group or is a custom setting
				if($group['isdefault'] != 1 || $setting['isdefault'] != 1)
				{
					$popup = new PopupMenu("setting_{$setting['sid']}", $lang->options);
					$popup->add_item($lang->edit_setting, "index.php?module=config-settings&amp;action=edit&amp;sid={$setting['sid']}");
					$popup->add_item($lang->delete_setting, "index.php?module=config-settings&amp;action=delete&amp;sid={$setting['sid']}&amp;my_post_key={$mybb->post_code}", "return AdminCP.deleteConfirmation(this, '{$lang->confirm_setting_deletion}')");
					$table->construct_cell($popup->fetch(), array('class' => 'align_center'));
				}
				else
				{
					$table->construct_cell('');
				}
				$table->construct_row(array('no_alt_row' => 1, 'class' => "group{$group['gid']}"));
			}
		}
	}
	
	$table->output($lang->modify_existing_settings);
	
	$buttons[] = $form->generate_submit_button($lang->save_display_orders);
	$form->output_submit_wrapper($buttons);
	$form->end();
	
	/*echo '<script type="text/javascript" src="./jscripts/config_settings.js"></script><script type="text/javascript">Event.observe(window, "load", ManageSettings.init);</script>';*/
	
	$page->output_footer();
}

// Change settings for a specified group.
if($mybb->input['action'] == "change")
{
	$plugins->run_hooks("admin_config_settings_change");
	
	if($mybb->request_method == "post")
	{
		if(!is_writable(MYBB_ROOT.'inc/settings.php'))
		{
			flash_message($lang->error_chmod_settings_file, 'error');
			admin_redirect("index.php?module=config-settings");
		}
		
		if(is_array($mybb->input['upsetting']))
		{
			foreach($mybb->input['upsetting'] as $name => $value)
			{
				$value = $db->escape_string($value);
				$db->update_query("settings", array('value' => $value), "name='".$db->escape_string($name)."'");
			}
		}
		
		// Check if we need to create our fulltext index after changing the search mode
		if($mybb->settings['searchtype'] != $mybb->input['upsetting']['searchtype'] && $mybb->input['upsetting']['searchtype'] == "fulltext")
		{
			if(!$db->is_fulltext("posts") && $db->supports_fulltext_boolean("posts"))
			{
				$db->create_fulltext_index("posts", "message");
			}
			if(!$db->is_fulltext("posts") && $db->supports_fulltext("threads"))
			{
				$db->create_fulltext_index("threads", "subject");
			}
		}

		// If the delayedthreadviews setting was changed, enable or disable the tasks for it.
		if(isset($mybb->input['upsetting']['delayedthreadviews']) && $mybb->settings['delayedthreadviews'] != $mybb->input['upsetting']['delayedthreadviews'])
		{
			if($mybb->input['upsetting']['delayedthreadviews'] == 0)
			{
				$updated_task = array(
					"enabled" => 0
				);
			}
			else
			{
				$updated_task = array(
					"enabled" => 1
				);
			}
			$db->update_query("tasks", $updated_task, "file='threadviews'");
		}
		
		// Have we changed our cookie prefix? If so, update our adminsid so we're not logged out
		if($mybb->input['upsetting']['cookieprefix'] && $mybb->input['upsetting']['cookieprefix'] != $mybb->settings['cookieprefix'])
		{
			my_unsetcookie("adminsid");
			$mybb->settings['cookieprefix'] = $mybb->input['upsetting']['cookieprefix'];
			my_setcookie("adminsid", $admin_session['sid']);
		}

		// Have we opted for a reCAPTCHA and not set a public/private key?
		if($mybb->input['upsetting']['captchaimage'] == 2 && !$mybb->input['upsetting']['captchaprivatekey'] && !$mybb->input['upsetting']['captchapublickey'])
		{
			$db->update_query("settings", array("value" => 1), "name = 'captchaimage'");
		}

		rebuild_settings();
		
		$plugins->run_hooks("admin_config_settings_change_commit");
			
		// Log admin action
		log_admin_action();

		flash_message($lang->success_settings_updated, 'success');
		admin_redirect("index.php?module=config-settings");
	}
	
	// What type of page
	$cache_groups = $cache_settings = array();
	if($mybb->input['search'])
	{
		// Search
		
		// Search for settings
		$search = $db->escape_string($mybb->input['search']);
		$query = $db->query("
			SELECT s.* 
			FROM ".TABLE_PREFIX."settings s
			LEFT JOIN ".TABLE_PREFIX."settinggroups g ON(s.gid=g.gid)
			WHERE s.name LIKE '%{$search}%' OR s.title LIKE '%{$search}%' OR s.description LIKE '%{$search}%' OR g.name LIKE '%{$search}%' OR g.title LIKE '%{$search}%' OR g.description LIKE '%{$search}%'
			ORDER BY s.disporder
		");
		while($setting = $db->fetch_array($query))
		{
			$cache_settings[$setting['gid']][$setting['sid']] = $setting;
		}
		
		if(!$db->num_rows($query))
		{
			if(isset($mybb->input['ajax_search']))
			{
				echo("<error>{$lang->error_no_settings_found}</error>");
				exit;
			}
			else
			{
				flash_message($lang->error_no_settings_found, 'error');
				admin_redirect("index.php?module=config-settings");	
			}
		}
		
		// Cache groups
		$groups = array_keys($cache_settings);
		$groups = implode(',', $groups);
		$query = $db->simple_select("settinggroups", "*", "gid IN ({$groups})", array('order_by' => 'disporder'));
		while($group = $db->fetch_array($query))
		{
			$cache_groups[$group['gid']] = $group;
		}
		
		// Page header only if not AJAX
		if(!isset($mybb->input['ajax_search']))
		{
			$page->add_breadcrumb_item($lang->settings_search);
			$page->output_header($lang->board_settings." - {$lang->settings_search}");
		}
		
		$form = new Form("index.php?module=config-settings&amp;action=change", "post", "change");
	
		echo $form->generate_hidden_field("gid", $group['gid']);
	}
	elseif($mybb->input['gid'])
	{
		// Group listing
		// Cache groups
		$query = $db->simple_select("settinggroups", "*", "gid = '".intval($mybb->input['gid'])."'");
		$groupinfo = $db->fetch_array($query);
		$cache_groups[$groupinfo['gid']] = $groupinfo;
		
		if(!$db->num_rows($query))
		{
			$page->output_error($lang->error_invalid_gid2);
		}
		
		// Cache settings
		$query = $db->simple_select("settings", "*", "gid='".intval($mybb->input['gid'])."'", array('order_by' => 'disporder'));
		while($setting = $db->fetch_array($query))
		{
			$cache_settings[$setting['gid']][$setting['sid']] = $setting;
		}
		
		if(!$db->num_rows($query))
        {
            flash_message($lang->error_no_settings_found, 'error');
            admin_redirect("index.php?module=config-settings");    
        }
		
		$group_lang_var = "setting_group_{$groupinfo['name']}";
		if($lang->$group_lang_var)
		{
			$groupinfo['title'] = $lang->$group_lang_var;
		}
		
		// Page header
		$page->add_breadcrumb_item($groupinfo['title']);
		$page->output_header($lang->board_settings." - {$groupinfo['title']}");
		
		$form = new Form("index.php?module=config-settings&amp;action=change", "post", "change");
	
		echo $form->generate_hidden_field("gid", $groupinfo['gid']);
	}
	else
	{
		// All settings list
		// Cache groups
		$query = $db->simple_select("settinggroups", "*", "", array('order_by' => 'disporder'));
		while($group = $db->fetch_array($query))
		{
			$cache_groups[$group['gid']] = $group;
		}
		
		if(!$db->num_rows($query))
		{
			$page->output_error($lang->error_invalid_gid2);
		}
		
		// Cache settings
		$query = $db->simple_select("settings", "*", "", array('order_by' => 'disporder'));
		while($setting = $db->fetch_array($query))
		{
			$cache_settings[$setting['gid']][$setting['sid']] = $setting;
		}
		
		// Page header
		$page->add_breadcrumb_item($lang->show_all_settings);
		$page->output_header($lang->board_settings." - {$lang->show_all_settings}");
		
		$form = new Form("index.php?module=config-settings&amp;action=change", "post", "change");
	}

	// Build rest of page
	$buttons[] = $form->generate_submit_button($lang->save_settings);
	foreach($cache_groups as $groupinfo)
	{
		$group_lang_var = "setting_group_{$groupinfo['name']}";
		if($lang->$group_lang_var)
		{
			$groupinfo['title'] = $lang->$group_lang_var;
		}
		
		$form_container = new FormContainer($groupinfo['title']);
		
		if(empty($cache_settings[$groupinfo['gid']]))
        {
            $form_container->output_cell($lang->error_no_settings_found);
            $form_container->construct_row();
            
            $form_container->end();
            echo '<br />';
			
            continue;
        }
		
		foreach($cache_settings[$groupinfo['gid']] as $setting)
		{
			$options = "";
			$type = explode("\n", $setting['optionscode']);
			$type[0] = trim($type[0]);
			$element_name = "upsetting[{$setting['name']}]";
			$element_id = "setting_{$setting['name']}";
			if($type[0] == "text" || $type[0] == "")
			{
				$setting_code = $form->generate_text_box($element_name, $setting['value'], array('id' => $element_id));
			}
			else if($type[0] == "textarea")
			{
				$setting_code = $form->generate_text_area($element_name, $setting['value'], array('id' => $element_id));
			}
			else if($type[0] == "yesno")
			{
				$setting_code = $form->generate_yes_no_radio($element_name, $setting['value'], true, array('id' => $element_id.'_yes', 'class' => $element_id), array('id' => $element_id.'_no', 'class' => $element_id));
			}
			else if($type[0] == "onoff")
			{
				$setting_code = $form->generate_on_off_radio($element_name, $setting['value'], true, array('id' => $element_id.'_on', 'class' => $element_id), array('id' => $element_id.'_off', 'class' => $element_id));
			}
			else if($type[0] == "cpstyle")
			{
				$dir = @opendir(MYBB_ROOT.$config['admin_dir']."/styles");
				while($folder = readdir($dir))
				{
					if($file != "." && $file != ".." && @file_exists(MYBB_ROOT.$config['admin_dir']."/styles/$folder/main.css"))
					{
						$folders[$folder] = ucfirst($folder);
					}
				}
				closedir($dir);
				ksort($folders);
				$setting_code = $form->generate_select_box($element_name, $folders, $setting['value'], array('id' => $element_id));
			}
			else if($type[0] == "language") 
			{
				$languages = $lang->get_languages();
				$setting_code = $form->generate_select_box($element_name, $languages, $setting['value'], array('id' => $element_id));
			}
			else if($type[0] == "adminlanguage") 
			{
				$languages = $lang->get_languages(1);
				$setting_code = $form->generate_select_box($element_name, $languages, $setting['value'], array('id' => $element_id));
			}
			else if($type[0] == "passwordbox")
			{
				$setting_code = $form->generate_password_box($element_name, $setting['value'], array('id' => $element_id));
			}
			else if($type[0] == "php")
			{
				$setting['optionscode'] = substr($setting['optionscode'], 3);
				eval("\$setting_code = \"".$setting['optionscode']."\";");
			}
			else
			{
				for($i=0; $i < count($type); $i++)
				{
					$optionsexp = explode("=", $type[$i]);
					if(!$optionsexp[1])
					{
						continue;
					}
					$title_lang = "setting_{$setting['name']}_{$optionsexp[0]}";
					if($lang->$title_lang)
					{
						$optionsexp[1] = $lang->$title_lang;
					}
					
					if($type[0] == "select")
					{
						$option_list[$optionsexp[0]] = htmlspecialchars_uni($optionsexp[1]);
					}
					else if($type[0] == "radio")
					{
						if($setting['value'] == $optionsexp[0])
						{
							$option_list[$i] = $form->generate_radio_button($element_name, $optionsexp[0], htmlspecialchars_uni($optionsexp[1]), array('id' => $element_id.'_'.$i, "checked" => 1, 'class' => $element_id));
						}
						else
						{
							$option_list[$i] = $form->generate_radio_button($element_name, $optionsexp[0], htmlspecialchars_uni($optionsexp[1]), array('id' => $element_id.'_'.$i, 'class' => $element_id));
						}
					}
					else if($type[0] == "checkbox")
					{
						if($setting['value'] == $optionsexp[0])
						{
							$option_list[$i] = $form->generate_check_box($element_name, $optionsexp[0], htmlspecialchars_uni($optionsexp[1]), array('id' => $element_id.'_'.$i, "checked" => 1, 'class' => $element_id));
						}
						else
						{
							$option_list[$i] = $form->generate_check_box($element_name, $optionsexp[0], htmlspecialchars_uni($optionsexp[1]), array('id' => $element_id.'_'.$i, 'class' => $element_id));
						}
					}
				}
				if($type[0] == "select")
				{
					$setting_code = $form->generate_select_box($element_name, $option_list, $setting['value'], array('id' => $element_id));
				}
				else
				{
					$setting_code = implode("<br />", $option_list);
				}
				$option_list = array();
			}
			// Do we have a custom language variable for this title or description?
			$title_lang = "setting_".$setting['name'];
			$desc_lang = $title_lang."_desc";
			if($lang->$title_lang)
			{
				$setting['title'] = $lang->$title_lang;
			}
			if($lang->$desc_lang)
			{
				$setting['description'] = $lang->$desc_lang;
			}
			$form_container->output_row(htmlspecialchars_uni($setting['title']), $setting['description'], $setting_code, '', array(), array('id' => 'row_'.$element_id));
		}
		$form_container->end();
		
		$form->output_submit_wrapper($buttons);
		echo '<br />';
	}
	$form->end();
	
	print_setting_peekers();
	
	if(!isset($mybb->input['ajax_search']))
	{
		$page->output_footer();
	}
}

if(!$mybb->input['action'])
{
	$plugins->run_hooks("admin_config_settings_start");
	
	$page->output_header($lang->board_settings);
	if($message)
	{
		$page->output_inline_message($message);
	}

	$sub_tabs['change_settings'] = array(
		'title' => $lang->change_settings,
		'link' => "index.php?module=config-settings",
		'description' => $lang->change_settings_desc
	);
	
	$sub_tabs['add_setting'] = array(
		'title' => $lang->add_new_setting,
		'link' => "index.php?module=config-settings&amp;action=add"
	);
	
	$sub_tabs['add_setting_group'] = array(
		'title' => $lang->add_new_setting_group,
		'link' => "index.php?module=config-settings&amp;action=addgroup"
	);
	
	$sub_tabs['modify_setting'] = array(
		'title' => $lang->modify_existing_settings,
		'link' => "index.php?module=config-settings&amp;action=manage",
	);	

	$page->output_nav_tabs($sub_tabs, 'change_settings');
	
	// Search form
	echo "<div style=\"text-align: right; margin-bottom: 3px;\">";
	$search = new Form("index.php", 'get', 'settings_search', 0, 'settings_search');
	echo $search->generate_hidden_field('module', 'config/settings');
	echo $search->generate_hidden_field('action', 'change');
	echo $search->generate_text_box('search', $lang->settings_search, array('id' => 'search', 'class' => 'search_default field150 field_small'));
	echo "<input type=\"submit\" class=\"search_button\" value=\"{$lang->search}\" />";
	$search->end();
	echo "</div>\n";

	echo '<div id="search_results">&nbsp;</div><div id="group_list">';
	$table = new Table;
	$table->construct_header($lang->setting_groups);

	switch($db->type)
	{
		case "pgsql":
			$query = $db->query("
				SELECT g.*, COUNT(s.sid) AS settingcount 
				FROM ".TABLE_PREFIX."settinggroups g 
				LEFT JOIN ".TABLE_PREFIX."settings s ON (s.gid=g.gid) 
				GROUP BY ".$db->build_fields_string("settinggroups", "g.")."
				ORDER BY g.disporder
			");
			break;
		default:
			$query = $db->query("
				SELECT g.*, COUNT(s.sid) AS settingcount 
				FROM ".TABLE_PREFIX."settinggroups g 
				LEFT JOIN ".TABLE_PREFIX."settings s ON (s.gid=g.gid) 
				GROUP BY g.gid
				ORDER BY g.disporder
			");
	}
	while($group = $db->fetch_array($query))
	{
		$group_lang_var = "setting_group_{$group['name']}";
		if($lang->$group_lang_var)
		{
			$group_title = htmlspecialchars_uni($lang->$group_lang_var);
		}
		else
		{
			$group_title = htmlspecialchars_uni($group['title']);
		}
		
		$group_desc_lang_var = "setting_group_{$group['name']}_desc";
		if($lang->$group_desc_lang_var)
		{
			$group_desc = htmlspecialchars_uni($lang->$group_desc_lang_var);
		}
		else
		{
			$group_desc = htmlspecialchars_uni($group['description']);
		}
				
		$table->construct_cell("<strong><a href=\"index.php?module=config-settings&amp;action=change&amp;gid={$group['gid']}\">{$group_title}</a></strong> ({$group['settingcount']} {$lang->bbsettings})<br /><small>{$group_desc}</small>");
		$table->construct_row();
	}
	$table->output("<span style=\"float: right;\"><small><a href=\"index.php?module=config-settings&amp;action=change\">{$lang->show_all_settings}</a></small></span>{$lang->board_settings}");
	
	echo '</div>';

	echo '
<script type="text/javascript">
//<!--
var SettingSearch = Class.create();
SettingSearch.prototype = {
	
	spinner: null,
	form: null,
	result_div: null,
	hide_div: null,
	search_box: null,

	initialize: function(form, search_box, result_div, hide_div)
	{
		Event.observe(form, "submit", this.onSubmit.bind(this));
		this.form = form;
		this.result_div = result_div;
		this.hide_div = hide_div;
		result_div.style.display = "none";
		this.search_box = search_box;
		Event.observe(search_box, "focus", function() {
			if($("search").value == "'.$lang->settings_search.'")
			{
				$("search").removeClassName("search_default");
				$("search").value = "";
			}
		});
		Event.observe(search_box, "blur", function() {
			if($("search").value == "")
			{
				$("search").addClassName("search_default");
				$("search").value = "'.$lang->settings_search.'";
				$("search_results").style.display = "none";
				$("group_list").style.display = "";
			}
		});
	},
	
	onSubmit: function(e)
	{
		Event.stop(e);
		if(this.search_box.value != "")
		{
			this.spinner = new ActivityIndicator("body", {image: "../images/spinner_big.gif"});
			pars = "module=config-settings&action=change&ajax_search=1&search="+encodeURIComponent(this.search_box.value);
			new Ajax.Request("index.php", {
			    method: "get",
				parameters: pars,
			    onComplete: this.onComplete.bind(this)
			});
		}
	},

	onComplete: function(request)
	{
		if(request.responseText.match(/<error>(.*)<\/error>/) || request.responseText == "")
		{
			message = request.responseText.match(/<error>(.*)<\/error>/);
			if(!message[1])
			{
				message[1] = "'.$lang->error_ajax_unknown.'";
			}
			alert(message[1]);
		}
		else if(request.responseText)
		{
			this.result_div.style.display = "";
			this.hide_div.style.display = "none";
			this.result_div.innerHTML = request.responseText;
			loadPeekers();
		}
		this.spinner.destroy();
	}
}
new SettingSearch($("settings_search"), $("search"), $("search_results"), $("group_list"));
//-->
</script>';
	
	print_setting_peekers();
	$page->output_footer();
}

function print_setting_peekers()
{
	echo '<script type="text/javascript" src="./jscripts/peeker.js"></script>
	<script type="text/javascript">
		Event.observe(window, "load", function() {
			loadPeekers();			
		});
		function loadPeekers()
		{
			new Peeker($$(".setting_boardclosed"), $("row_setting_boardclosed_reason"), /1/, true);
			new Peeker($$(".setting_gzipoutput"), $("row_setting_gziplevel"), /1/, true);
			new Peeker($$(".setting_useerrorhandling"), $("row_setting_errorlogmedium"), /1/, true);
			new Peeker($$(".setting_useerrorhandling"), $("row_setting_errortypemedium"), /1/, true);
			new Peeker($$(".setting_useerrorhandling"), $("row_setting_errorloglocation"), /1/, true);
			new Peeker($("setting_subforumsindex"), $("row_setting_subforumsstatusicons"), /[^0]/, false);
			new Peeker($$(".setting_showsimilarthreads"), $("row_setting_similarityrating"), /1/, true);
			new Peeker($$(".setting_showsimilarthreads"), $("row_setting_similarlimit"), /1/, true);
			new Peeker($$(".setting_disableregs"), $("row_setting_regtype"), /0/, true);
			new Peeker($$(".setting_hiddencaptchaimage"), $("row_setting_hiddencaptchaimagefield"), /1/, true);
			new Peeker($$(".setting_showsimilarthreads"), $("row_setting_similarlimit"), /1/, true);
			new Peeker($("setting_failedlogincount"), $("row_setting_failedlogintime"), /[^0]/, false);
			new Peeker($("setting_failedlogincount"), $("row_setting_failedlogintext"), /[^0]/, false);
			new Peeker($$(".setting_postfloodcheck"), $("row_setting_postfloodsecs"), /1/, true);
			new Peeker($("setting_postmergemins"), $("row_setting_postmergefignore"), /[^0]/, false);
			new Peeker($("setting_postmergemins"), $("row_setting_postmergeuignore"), /[^0]/, false);
			new Peeker($("setting_postmergemins"), $("row_setting_postmergesep"), /[^0]/, false);
			new Peeker($$(".setting_enablememberlist"), $("row_setting_membersperpage"), /1/, true);
			new Peeker($$(".setting_enablememberlist"), $("row_setting_default_memberlist_sortby"), /1/, true);
			new Peeker($$(".setting_enablememberlist"), $("row_setting_default_memberlist_order"), /1/, true);
			new Peeker($$(".setting_enablereputation"), $("row_setting_repsperpage"), /1/, true);
			new Peeker($$(".setting_enablewarningsystem"), $("row_setting_allowcustomwarnings"), /1/, true);
			new Peeker($$(".setting_enablewarningsystem"), $("row_setting_canviewownwarning"), /1/, true);
			new Peeker($$(".setting_enablewarningsystem"), $("row_setting_maxwarningpoints"), /1/, true);
			new Peeker($$(".setting_enablepms"), $("row_setting_pmsallowhtml"), /1/, true);
			new Peeker($$(".setting_enablepms"), $("row_setting_pmsallowmycode"), /1/, true);
			new Peeker($$(".setting_enablepms"), $("row_setting_pmsallowsmilies"), /1/, true);
			new Peeker($$(".setting_enablepms"), $("row_setting_pmsallowimgcode"), /1/, true);
			new Peeker($$(".setting_enablepms"), $("row_setting_pmsallowvideocode"), /1/, true);
			new Peeker($$(".setting_smilieinserter"), $("row_setting_smilieinsertertot"), /1/, true);
			new Peeker($$(".setting_smilieinserter"), $("row_setting_smilieinsertercols"), /1/, true);
			new Peeker($("setting_mail_handler"), $("row_setting_smtp_host"), /smtp/, false);
			new Peeker($("setting_mail_handler"), $("row_setting_smtp_port"), /smtp/, false);
			new Peeker($("setting_mail_handler"), $("row_setting_smtp_user"), /smtp/, false);
			new Peeker($("setting_mail_handler"), $("row_setting_smtp_pass"), /smtp/, false);
			new Peeker($("setting_mail_handler"), $("row_setting_secure_smtp"), /smtp/, false);
			new Peeker($("setting_mail_handler"), $("row_setting_mail_parameters"), /mail/, false);
			new Peeker($("setting_captchaimage"), $("row_setting_captchapublickey"), 2, false);
			new Peeker($("setting_captchaimage"), $("row_setting_captchaprivatekey"), 2, false);
		}
	</script>';
}
?>