<?php
// TODO
//   Manage settings and groups page - lists the groups and has edit/delete for both groups and settings
//   Delete setting page
//   Add / edit group pages

$page->add_breadcrumb_item("Board Settings", "index.php?".SID."&amp;module=config/settings");

// Creating a new setting
if($mybb->input['action'] == "add")
{
	if($mybb->request_method == "post")
	{
		if(!trim($mybb->input['title']))
		{
			$errors[] = "You did not enter a title for this setting";
		}

		$query = $db->simple_select("settinggroups", "gid", "gid='".intval($mybb->input['gid'])."'");
		$gid = $db->fetch_field($query, 'gid');
		if(!$gid)
		{
			$errors[] = "You did not select a valid group to place this setting in";
		}

		if(!trim($mybb->input['name']))
		{
			$errors[] = "You did not enter a name for this setting";
		}

		if(!$mybb->input['type'])
		{
			$errors[] = "You did not select a valid type for this setting";
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
			flash_message('The setting has successfully been created', 'success');
			admin_redirect("index.php?".SID."&module=config/settings");
		}
	}

	$page->add_breadcrumb_item("Add New Setting");
	$page->output_header("Board Settings - Add New Setting");
	
	$sub_tabs['add_setting'] = array(
		'title' => "Add New Setting",
		'link' => "index.php?".SID."&amp;module=config/settings&amp;action=add",
		'description' => "This section allows you to manage all of the various settings relating to your board. To begin, select a group below to manage settings relating to that group."
	);

	$page->output_nav_tabs($sub_tabs, 'add_setting');

	$form = new Form("index.php?".SID."&amp;module=config/settings&amp;action=add", "post", "add");

	if($errors)
	{
		$page->output_inline_error($errors);
	}

	$form_container = new FormContainer("Add New Setting");
	$form_container->output_row("Title <em>*</em>", "", $form->generate_text_box('title', $mybb->input['title'], array('id' => 'title')), 'title');
	$form_container->output_row("Description", "", $form->generate_text_area('description', $mybb->input['description'], array('id' => 'description')), 'description');
	
	$query = $db->simple_select("settinggroups", "*", "", array('order_by' => 'disporder'));
	while($group = $db->fetch_array($query))
	{
		$options[$group['gid']] = $group['title'];
	}
	$form_container->output_row("Group <em>*</em>", "", $form->generate_select_box("gid", $options, $mybb->input['gid'], array('id' => 'gid')), 'gid');
	$form_container->output_row("Display Order", "", $form->generate_text_box('disporder', $mybb->input['disporder'], array('id' => 'disporder')), 'disporder');

	$form_container->output_row("Name <em>*</em>", 'The setting name the key name of the settings array used in scripts and templates.', $form->generate_text_box('name', $mybb->input['name'], array('id' => 'name')), 'name');

	$setting_types = array(
		"text" => "Text",
		"textarea" => "Textarea",
		"yesno" => "Yes / No Choice",
		"onoff" => "On / Off Choice",
		"select" => "Selection Box",
		"radio" => "Radio Buttons",
		"checkbox" => "Checkboxes",
		"language" => "Language Selection Box",
		"adminlanguage" => "Administration Language Selection Box",
		"cpstyle" => "Control Panel Style Selection Box",
		"php" => "Evaluated PHP"
	);

	$form_container->output_row("Type <em>*</em>", "", $form->generate_select_box("type", $setting_types, $mybb->input['type'], array('id' => 'type')), 'type');
	$form_container->output_row("Extra", 'If this setting is a select, radio or check box enter a key paired (key=Item) list of items to show. Separate items with a new line. If PHP, enter the PHP to be evaluated.', $form->generate_text_area('extra', $mybb->input['extra'], array('id' => 'extra')), 'extra');
	$form_container->output_row("Value", "", $form->generate_text_area('value', $mybb->input['value'], array('id' => 'value')), 'value');
	$form_container->end();

	$buttons[] = $form->generate_submit_button("Insert New Setting");
	$form->output_submit_wrapper($buttons);
	$form->end();

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
		flash_message('The specified setting does not exist', 'error');
		admin_redirect("index.php?".SID."&module=config/settings");
	}
	
	if($mybb->request_method == "post")
	{
		if(!trim($mybb->input['title']))
		{
			$errors[] = "You did not enter a title for this setting";
		}

		$query = $db->simple_select("settinggroups", "gid", "gid='".intval($mybb->input['gid'])."'");
		$gid = $db->fetch_field($query, 'gid');
		if(!$gid)
		{
			$errors[] = "You did not select a valid group to place this setting in";
		}

		if(!trim($mybb->input['name']))
		{
			$errors[] = "You did not enter a name for this setting";
		}

		if(!$mybb->input['type'])
		{
			$errors[] = "You did not select a valid type for this setting";
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
			flash_message('The setting has successfully been updated.', 'success');
			admin_redirect("index.php?".SID."&module=config/settings");
		}
	}

	$page->add_breadcrumb_item("Edit Setting");
	$page->output_header("Board Settings - Edit Setting");
	
	$sub_tabs['modify_setting'] = array(
		'title' => "Modify Existing Settings",
		'link' => "index.php?".SID."&amp;module=config/settings&amp;action=edit",
		'description' => "This section allows you to manage all of the various settings relating to your board. To begin, select a group below to manage settings relating to that group."
	);

	$page->output_nav_tabs($sub_tabs, 'modify_setting');

	$form = new Form("index.php?module=config/settings", "post", "edit");

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

	$form_container = new FormContainer("Modify Setting");
	$form_container->output_row("Title <em>*</em>", "", $form->generate_text_box('title', $setting_data['title'], array('id' => 'title')), 'title');
	$form_container->output_row("Description", "", $form->generate_text_area('description', $setting_data['description'], array('id' => 'description')), 'description');
	
	$query = $db->simple_select("settinggroups", "*", "", array('order_by' => 'disporder'));
	while($group = $db->fetch_array($query))
	{
		$options[$group['gid']] = $group['title'];
	}
	$form_container->output_row("Group <em>*</em>", "", $form->generate_select_box("gid", $options, $setting_data['gid'], array('id' => 'gid')), 'gid');
	$form_container->output_row("Display Order", "", $form->generate_text_box('disporder', $setting_data['disporder'], array('id' => 'disporder')), 'disporder');
	$form_container->end();

	$form_container = new FormContainer("Setting Configuration", 1);
	$form_container->output_row("Name <em>*</em>", 'The setting name the key name of the settings array used in scripts and templates.', $form->generate_text_box('name', $setting_data['name'], array('id' => 'name')), 'name');

	$setting_types = array(
		"text" => "Text",
		"textarea" => "Textarea",
		"yesno" => "Yes / No Choice",
		"onoff" => "On / Off Choice",
		"select" => "Selection Box",
		"radio" => "Radio Buttons",
		"checkbox" => "Checkboxes",
		"language" => "Language Selection Box",
		"adminlanguage" => "Administration Language Selection Box",
		"cpstyle" => "Control Panel Style Selection Box",
		"php" => "Evaluated PHP"
	);
	$form_container->output_row("Type <em>*</em>", "", $form->generate_select_box("type", $setting_types, $setting_data['type'], array('id' => 'type')), 'type');
	$form_container->output_row("Extra", 'If this setting is a select, radio or check box enter a key paired (key=Item) list of items to show. Separate items with a new line. If PHP, enter the PHP to be evaluated.', $form->generate_text_area('extra', $setting_data['extra'], array('id' => 'extra')), 'extra');
	$form_container->output_row("Value", '', $form->generate_text_area('value', $setting_data['value'], array('id' => 'value')), 'value');
	$form_container->end();

	$buttons[] = $form->generate_submit_button("Update Setting");
	$form->output_submit_wrapper($buttons);
	$form->end();

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
		flash_message('The settings have successfully been updated.', 'success');
		admin_redirect("index.php?".SID."&module=config/settings");
	}

	$query = $db->query("
		SELECT g.*, COUNT(s.sid) AS settingcount 
		FROM ".TABLE_PREFIX."settinggroups g 
		LEFT JOIN ".TABLE_PREFIX."settings s ON (s.gid=g.gid) 
		WHERE g.gid='".intval($mybb->input['gid'])."' 
		GROUP BY s.gid
	");
	$groupinfo = $db->fetch_array($query);
	
	if(!$groupinfo['gid'])
	{
		$page->error("You have followed a link to an invalid setting group. Please ensure it exists.");
	}
	$page->add_breadcrumb_item($groupinfo['title']);
	$page->output_header("Board Settings - {$groupinfo['title']}");
	
	$form = new Form("index.php?".SID."&amp;module=config/settings", "post", "change");

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
		$title_lang = "setting_".$setting->name;
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

	$buttons[] = $form->generate_submit_button("Save Settings");
	
	$form->output_submit_wrapper($buttons);
	$form->end();
	
	$page->output_footer();
}

if(!$mybb->input['action'])
{
	$page->output_header("Board Settings");
	if($message)
	{
		$page->output_inline_message($message);
	}

	$sub_tabs['change_settings'] = array(
		'title' => "Change Settings",
		'link' => "index.php?".SID."&amp;module=config/settings",
		'description' => "This section allows you to manage all of the various settings relating to your board. To begin, select a group below to manage settings relating to that group."
	);
	$sub_tabs['add_setting'] = array(
		'title' => "Add New Setting",
		'link' => "index.php?".SID."&amp;module=config/settings&amp;action=add",
		'description' => "This section allows you to manage all of the various settings relating to your board. To begin, select a group below to manage settings relating to that group."
	);
	
	$sub_tabs['modify_setting'] = array(
		'title' => "Modify Existing Settings",
		'link' => "index.php?".SID."&amp;module=config/settings&amp;action=manage",
		'description' => "This section allows you to manage all of the various settings relating to your board. To begin, select a group below to manage settings relating to that group."
	);
	

	$page->output_nav_tabs($sub_tabs, 'change_settings');

	$table = new Table;
	$table->construct_header("Setting Groups");

	$query = $db->query("
		SELECT g.*, COUNT(s.sid) AS settingcount 
		FROM ".TABLE_PREFIX."settinggroups g 
		LEFT JOIN ".TABLE_PREFIX."settings s ON (s.gid=g.gid) 
		GROUP BY s.gid 
		ORDER BY g.disporder
	");
	while($group = $db->fetch_array($query))
	{
		$table->construct_cell("<strong><a href=\"index.php?".SID."&amp;module=config/settings&amp;action=change&amp;gid={$group['gid']}\">{$group['title']}</a></strong> ({$group['settingcount']} Settings)<br /><small>{$group['description']}</small>");
		$table->construct_row();
	}
	$table->output("Board Settings");

	$page->output_footer();
}
?>