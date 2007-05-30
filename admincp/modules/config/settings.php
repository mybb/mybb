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

$page->add_breadcrumb_item($lang->board_settings, "index.php?".SID."&amp;module=config/settings");

// Creating a new setting group
if($mybb->input['action'] == "addgroup")
{
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
			$errors[] = sprintf($lang->error_duplicate_group_name, $dup_group_title);
		}

		if(!$errors)
		{
			$new_setting_group = array(
				"name" => $db->escape_string($mybb->input['name']),
				"title" => $db->escape_string($mybb->input['title']),
				"description" => $db->escape_string($mybb->input['description']),
				"disporder" => intval($mybb->input['disporder']),
				"isdefault" => 'no'
			);
			
			$db->insert_query("settinggroups", $new_setting_group);
			flash_message($lang->success_setting_group_added, 'success');
			admin_redirect("index.php?".SID."&module=config/settings&action=manage");
		}
	}

	$page->add_breadcrumb_item($lang->add_new_setting_group);
	$page->output_header($lang->board_settings." - ".$lang->add_new_setting_group);
	
	$sub_tabs['add_setting_group'] = array(
		'title' => $lang->add_new_setting_group,
		'link' => "index.php?".SID."&amp;module=config/settings&amp;action=addgroup",
		'description' => $lang->add_new_setting_group_desc
	);

	$page->output_nav_tabs($sub_tabs, 'add_setting_group');

	$form = new Form("index.php?".SID."&amp;module=config/settings&amp;action=addgroup", "post", "add");

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
	$query = $db->simple_select("settinggroups", "*", "gid='".intval($mybb->input['gid'])."'");
	$group = $db->fetch_array($query);

	// Does the setting not exist?
	if(!$group['gid'])
	{
		flash_message($lang->error_invalid_gid2, 'error');
		admin_redirect("index.php?".SID."&module=config/settings&action=manage");
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
			$errors[] = sprintf($lang->error_duplicate_group_name, $dup_group_title);
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
			flash_message($lang->success_setting_group_updated, 'success');
			admin_redirect("index.php?".SID."&module=config/settings&action=manage");
		}
	}

	$page->add_breadcrumb_item($lang->edit_setting_group);
	$page->output_header($lang->board_settings." - ".$lang->edit_setting_group);
	
	$sub_tabs['edit_setting_group'] = array(
		'title' => $lang->edit_setting_group,
		'link' => "index.php?".SID."&amp;module=config/settings&amp;action=editgroup&amp;gid={$group['gid']}",
		'description' => $lang->edit_setting_group_desc
	);

	$page->output_nav_tabs($sub_tabs, 'edit_setting_group');

	$form = new Form("index.php?".SID."&amp;module=config/settings&amp;action=editgroup", "post", "editgroup");

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
	$query = $db->simple_select("settinggroups", "*", "gid='".intval($mybb->input['gid'])."'");
	$group = $db->fetch_array($query);

	// Does the setting group not exist?
	if(!$group['gid'])
	{
		flash_message($lang->error_invalid_gid2, 'error');
		admin_redirect("index.php?".SID."&module=config/settings&action=manage");
	}
	
	// User clicked no
	if($mybb->input['no'])
	{
		admin_redirect("index.php?".SID."&module=config/settings&action=manage");
	}

	if($mybb->request_method == "post")
	{
		// Delete the setting group and its settings
		$db->delete_query("settinggroups", "gid='{$group['gid']}'");
		$db->delete_query("settings", "gid='{$group['gid']}'");
		
		rebuild_settings();

		flash_message($lang->success_setting_group_deleted, 'success');
		admin_redirect("index.php?".SID."&module=config/settings&action=manage");
	}
	else
	{
		$page->output_confirm_action("index.php?".SID."&module=config/settings&amp;action=deletegroup&amp;gid={$group['gid']}", $lang->confirm_setting_group_deletion);
	}
}

// Creating a new setting
if($mybb->input['action'] == "add")
{
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
			$errors[] = sprintf($lang->error_duplicate_name, $dup_setting_title);
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
			
			$db->insert_query("settings", $new_setting);
			rebuild_settings();
			flash_message($lang->success_setting_added, 'success');
			admin_redirect("index.php?".SID."&module=config/settings&action=manage");
		}
	}

	$page->add_breadcrumb_item($lang->add_new_setting);
	$page->output_header($lang->board_settings." - ".$lang->add_new_setting);
	
	$sub_tabs['add_setting'] = array(
		'title' => $lang->add_new_setting,
		'link' => "index.php?".SID."&amp;module=config/settings&amp;action=add",
		'description' => $lang->add_new_setting_desc
	);

	$page->output_nav_tabs($sub_tabs, 'add_setting');

	$form = new Form("index.php?".SID."&amp;module=config/settings&amp;action=add", "post", "add");

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
		"language" => $lang->language,
		"adminlanguage" => $lang->adminlanguage,
		"cpstyle" => $lang->cpstyle,
		"php" => $lang->php
	);

	$form_container->output_row($lang->type." <em>*</em>", "", $form->generate_select_box("type", $setting_types, $mybb->input['type'], array('id' => 'type')), 'type');
	$form_container->output_row($lang->extra, $lang->extra_desc, $form->generate_text_area('extra', $mybb->input['extra'], array('id' => 'extra')), 'extra', array(), array('id' => 'row_extra'));
	$form_container->output_row($lang->value, "", $form->generate_text_area('value', $mybb->input['value'], array('id' => 'value')), 'value');
	$form_container->end();

	$buttons[] = $form->generate_submit_button($lang->insert_new_setting);
	$form->output_submit_wrapper($buttons);
	$form->end();
	
	echo '<script type="text/javascript" src="./jscripts/peeker.js"></script>
	<script type="text/javascript">Event.observe(window, "load", function() {var peeker = new Peeker("type", "row_extra", /select|radio|checkbox|php/);});
		// Add a star to the extra row since the "extra" is required if the box is shown
		add_star("row_extra");
	</script>';

	$page->output_footer();
}

// Editing a particular setting
if($mybb->input['action'] == "edit")
{
	$query = $db->simple_select("settings", "*", "sid='".intval($mybb->input['sid'])."'");
	$setting = $db->fetch_array($query);

	// Does the setting not exist?
	if(!$setting['sid'])
	{
		flash_message($lang->error_invalid_sid, 'error');
		admin_redirect("index.php?".SID."&module=config/settings");
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
			$errors[] = sprintf($lang->error_duplicate_name, $dup_setting_title);
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
			flash_message($lang->success_setting_updated, 'success');
			admin_redirect("index.php?".SID."&module=config/settings&action=manage");
		}
	}

	$page->add_breadcrumb_item($lang->edit_setting);
	$page->output_header($lang->board_settings." - ".$lang->edit_setting);
	
	$sub_tabs['modify_setting'] = array(
		'title' => $lang->modify_existing_settings,
		'link' => "index.php?".SID."&amp;module=config/settings&amp;action=edit&amp;sid={$setting['sid']}",
		'description' => $lang->modify_existing_settings_desc
	);

	$page->output_nav_tabs($sub_tabs, 'modify_setting');

	$form = new Form("index.php?".SID."&amp;module=config/settings&amp;action=edit", "post", "edit");

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
		"language" => $lang->language,
		"adminlanguage" => $lang->adminlanguage,
		"cpstyle" => $lang->cpstyle,
		"php" => $lang->php
	);

	$form_container->output_row($lang->type." <em>*</em>", "", $form->generate_select_box("type", $setting_types, $setting_data['type'], array('id' => 'type')), 'type');
	$form_container->output_row($lang->extra, $lang->extra_desc, $form->generate_text_area('extra', $setting_data['extra'], array('id' => 'extra')), 'extra', array(), array('id' => 'row_extra'));
	$form_container->output_row($lang->value, '', $form->generate_text_area('value', $setting_data['value'], array('id' => 'value')), 'value');
	$form_container->end();

	$buttons[] = $form->generate_submit_button($lang->update_setting);
	$form->output_submit_wrapper($buttons);
	$form->end();
	
	echo '<script type="text/javascript" src="./jscripts/peeker.js"></script>
	<script type="text/javascript">Event.observe(window, "load", function() {var peeker = new Peeker("type", "row_extra", /select|radio|checkbox|php/);});
		// Add a star to the extra row since the "extra" is required if the box is shown
		add_star("row_extra");
	</script>';

	$page->output_footer();
}

// Delete Setting
if($mybb->input['action'] == "delete")
{
	$query = $db->simple_select("settings", "*", "sid='".intval($mybb->input['sid'])."'");
	$setting = $db->fetch_array($query);

	// Does the setting not exist?
	if(!$setting['sid'])
	{
		flash_message($lang->error_invalid_sid, 'error');
		admin_redirect("index.php?".SID."&module=config/settings&action=manage");
	}
	
	// User clicked no
	if($mybb->input['no'])
	{
		admin_redirect("index.php?".SID."&module=config/settings&action=manage");
	}

	if($mybb->request_method == "post")
	{
		// Delete the setting
		$db->delete_query("settings", "sid='{$setting['sid']}'");
		
		rebuild_settings();

		flash_message($lang->success_setting_deleted, 'success');
		admin_redirect("index.php?".SID."&module=config/settings&action=manage");
	}
	else
	{
		$page->output_confirm_action("index.php?".SID."&module=config/settings&amp;action=delete&amp;sid={$setting['sid']}", $lang->confirm_setting_deletion);
	}
}

// Modify Existing Settings
if($mybb->input['action'] == "manage")
{
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
		
		flash_message($lang->success_display_orders_updated, 'success');
		admin_redirect("index.php?".SID."&module=config/settings&action=manage");
	}
	
	$page->add_breadcrumb_item($lang->modify_existing_settings);
	$page->output_header($lang->board_settings." - ".$lang->modify_existing_settings);
	
	$sub_tabs['modify_setting'] = array(
		'title' => $lang->modify_existing_settings,
		'link' => "index.php?".SID."&amp;module=config/settings&amp;action=manage",
		'description' => $lang->modify_existing_settings_desc
	);

	$page->output_nav_tabs($sub_tabs, 'modify_setting');
	
	// Cache settings
	$settings_cache = array();
	$query = $db->simple_select("settings", "sid, name, title, disporder, gid", "", array('order_by' => 'disporder', 'order_dir' => 'asc'));
	while($setting = $db->fetch_array($query))
	{
		$settings_cache[$setting['gid']][] = $setting;
	}

	$form = new Form("index.php?".SID."&amp;module=config/settings&amp;action=manage", "post", "edit");
	
	$table = new Table;

	$table->construct_header($lang->setting_group_setting, array('width' => '80%'));
	$table->construct_header($lang->display_order, array('width' => '10%', 'class' => 'align_center'));
	$table->construct_header($lang->controls, array('width' => '10%', 'class' => 'align_center'));
	
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
		$table->construct_cell($form->generate_text_box("group_disporder[{$group['gid']}]", $group['disporder'], array('style' => 'width: 25px; font-weight: bold', 'class' => 'align_center')), array('class' => 'align_center'));
		// Only show options if not a default setting group
		if($group['isdefault'] != "yes")
		{
			$popup = new PopupMenu("group_{$group['gid']}", $lang->options);
			$popup->add_item($lang->edit_setting_group, "index.php?".SID."&amp;module=config/settings&amp;action=editgroup&amp;gid={$group['gid']}");
			$popup->add_item($lang->delete_setting_group, "index.php?".SID."&amp;module=config/settings&amp;action=deletegroup&amp;gid={$group['gid']}", "return AdminCP.deleteConfirmation(this, '{$lang->confirm_setting_group_deletion}')");
			$table->construct_cell($popup->fetch(), array('class' => 'align_center'));
		}
		else
		{
			$table->construct_cell('');
		}
		$table->construct_row(array('class' => 'alt_row', 'no_alt_row' => 1));
		
		// Make rows for each setting in the group
		foreach($settings_cache[$group['gid']] as $setting)
		{
			$setting_lang_var = "setting_group_{$group['name']}";
			if($lang->$setting_lang_var)
			{
				$group_title = htmlspecialchars_uni($lang->$setting_lang_var);
			}
			else
			{
				$group_title = htmlspecialchars_uni($setting['title']);
			}
			$table->construct_cell($setting['title'], array('style' => 'padding-left: 30px;'));
			$table->construct_cell($form->generate_text_box("setting_disporder[{$setting['sid']}]", $setting['disporder'], array('style' => 'width: 25px', 'class' => 'align_center')), array('class' => 'align_center'));
			// Only show options if not a default setting group
			if($group['isdefault'] != "yes")
			{
				$popup = new PopupMenu("setting_{$setting['sid']}", $lang->options);
				$popup->add_item($lang->edit_setting, "index.php?".SID."&amp;module=config/settings&amp;action=edit&amp;sid={$setting['sid']}");
				$popup->add_item($lang->delete_setting, "index.php?".SID."&amp;module=config/settings&amp;action=delete&amp;sid={$setting['sid']}", "return AdminCP.deleteConfirmation(this, '{$lang->confirm_setting_deletion}')");
				$table->construct_cell($popup->fetch(), array('class' => 'align_center'));
			}
			else
			{
				$table->construct_cell('');
			}
			$table->construct_row(array('no_alt_row' => 1, 'class' => "group{$group['gid']}"));
		}
	}
	
	$table->output($lang->modify_existing_settings);
	
	$buttons[] = $form->generate_submit_button($lang->save_display_orders);
	$form->output_submit_wrapper($buttons);
	$form->end();
	
	echo '<script type="text/javascript" src="./jscripts/config_settings.js"></script><script type="text/javascript">Event.observe(window, "load", ManageSettings.init);</script>';
	
	$page->output_footer();
}

// Change settings for a specified group.
if($mybb->input['action'] == "change")
{
	if($mybb->request_method == "post")
	{
		if(is_array($mybb->input['upsetting']))
		{
			foreach($mybb->input['upsetting'] as $sid => $value)
			{
				$value = $db->escape_string($value);
				$sid = intval($sid);
				$db->update_query("settings", array('value' => $value), "sid='$sid'");
			}
		}
		
		rebuild_settings();
		// Check if we need to create our fulltext index after changing the search mode
		if($mybb->settings['searchtype'] == "fulltext")
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
		flash_message($lang->success_settings_updated, 'success');
		admin_redirect("index.php?".SID."&module=config/settings");
	}

	$query = $db->simple_select("settinggroups", "*", "gid = '".intval($mybb->input['gid'])."'");
	$groupinfo = $db->fetch_array($query);
	
	if(!$groupinfo['gid'])
	{
		$page->output_error($lang->error_invalid_gid2);
	}
	$page->add_breadcrumb_item($groupinfo['title']);
	$page->output_header($lang->board_settings." - {$groupinfo['title']}");
	
	$form = new Form("index.php?".SID."&amp;module=config/settings&amp;action=change", "post", "change");

	echo $form->generate_hidden_field("gid", $groupinfo['gid']);
	
	$form_container = new FormContainer($groupinfo['title']);

	$query = $db->simple_select("settings", "*", "gid='".intval($mybb->input['gid'])."'", array('order_by' => 'disporder'));
	while($setting = $db->fetch_array($query))
	{
		$options = "";
		$type = explode("\n", $setting['optionscode']);
		$type[0] = trim($type[0]);
		if($type[0] == "text" || $type[0] == "")
		{
			$setting_code = $form->generate_text_box("upsetting[{$setting['sid']}]", $setting['value']);
		}
		else if($type[0] == "textarea")
		{
			$setting_code = $form->generate_text_area("upsetting[{$setting['sid']}]", $setting['value']);
		}
		else if($type[0] == "yesno")
		{
			$setting_code = $form->generate_yes_no_radio("upsetting[{$setting['sid']}]", $setting['value']);
		}
		else if($type[0] == "onoff")
		{
			$setting_code = $form->generate_on_off_radio("upsetting[{$setting['sid']}]", $setting['value']);
		}
		else if($type[0] == "cpstyle")
		{
			$dir = @opendir($config['admindir']."/styles");
			while($folder = readdir($dir))
			{
				if($file != "." && $file != ".." && @file_exists($config['admindir']."/styles/$folder/main.css"))
				{
					$folders[$folder] = $folder;
				}
			}
			closedir($dir);
			ksort($folders);
			$setting_code = $form->generate_select_box("upsetting[{$setting['sid']}]", $folders, $setting['value']);
		}
		else if($type[0] == "language") 
		{
			$languages = $lang->get_languages();
			$setting_code = $form->generate_select_box("upsetting[{$setting['sid']}]", $languages, $setting['value']);
		}
		else if($type[0] == "adminlanguage") 
		{
			$languages = $lang->get_languages(1);
			$setting_code = $form->generate_select_box("upsetting[{$setting['sid']}]", $languages, $setting['value']);
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
				if($type[0] == "select")
				{
					$option_list[$optionsexp[0]] = $optionsexp[1];
				}
				else if($type[0] == "radio")
				{
					if($setting['value'] == $optionsexp[0])
					{
						$option_list[$i] = $form->generate_radio_button("upsetting[{$setting['sid']}]", $optionsexp[0], $optionsexp[1], array("checked" => 1));
					}
					else
					{
						$option_list[$i] = $form->generate_radio_button("upsetting[{$setting['sid']}]", $optionsexp[0], $optionsexp[1]);
					}
				}
				else if($type[0] == "checkbox")
				{
					if($setting['value'] == $optionsexp[0])
					{
						$option_list[$i] = $form->generate_checkbox_input("upsetting[{$setting['sid']}]", $optionsexp[0], $optionsexp[1], array("checked" => 1));
					}
					else
					{
						$option_list[$i] = $form->generate_checkbox_input("upsetting[{$setting['sid']}]", $optionsexp[0], $optionsexp[1]);
					}
				}
			}
			if($type[0] == "select")
			{
				$setting_code = $form->generate_select_box("upsetting[{$setting['sid']}]", $option_list, $setting['value']);
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
		$form_container->output_row($setting['title'], $setting['description'], $setting_code);
	}
	$form_container->end();

	$buttons[] = $form->generate_submit_button($lang->save_settings);
	
	$form->output_submit_wrapper($buttons);
	$form->end();
	
	$page->output_footer();
}

if(!$mybb->input['action'])
{
	$page->output_header($lang->board_settings);
	if($message)
	{
		$page->output_inline_message($message);
	}

	$sub_tabs['change_settings'] = array(
		'title' => $lang->change_settings,
		'link' => "index.php?".SID."&amp;module=config/settings",
		'description' => $lang->change_settings_desc
	);
	
	$sub_tabs['add_setting'] = array(
		'title' => $lang->add_new_setting,
		'link' => "index.php?".SID."&amp;module=config/settings&amp;action=add"
	);
	
	$sub_tabs['add_setting_group'] = array(
		'title' => $lang->add_new_setting_group,
		'link' => "index.php?".SID."&amp;module=config/settings&amp;action=addgroup"
	);
	
	$sub_tabs['modify_setting'] = array(
		'title' => $lang->modify_existing_settings,
		'link' => "index.php?".SID."&amp;module=config/settings&amp;action=manage",
	);
	

	$page->output_nav_tabs($sub_tabs, 'change_settings');

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
		$table->construct_cell("<strong><a href=\"index.php?".SID."&amp;module=config/settings&amp;action=change&amp;gid={$group['gid']}\">{$group['title']}</a></strong> ({$group['settingcount']} {$lang->settings})<br /><small>{$group['description']}</small>");
		$table->construct_row();
	}
	$table->output($lang->board_settings);

	$page->output_footer();
}
?>