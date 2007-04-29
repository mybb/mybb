<?php
/**
 * MyBB 1.2
 * Copyright © 2007 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/license.php
 *
 * $Id: smilies.php 2992 2007-04-05 14:43:48Z chris $
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$page->add_breadcrumb_item("Calendars", "index.php?".SID."&amp;module=config/calendars");

if($mybb->input['action'] == "add" || $mybb->input['action'] == "permissions" || !$mybb->input['action'])
{
	$sub_tabs['manage_calendars'] = array(
		'title' => "Manage Calendars",
		'link' => "index.php?".SID."&amp;module=config/calendars",
		'description' => "This section allows you to manage the calendars on your board. If you change the display order for one or more calendars make sure you submit the form at the bottom of the page."
	);
	$sub_tabs['add_calendar'] = array(
		'title' => "Add Calendar",
		'link' => "index.php?".SID."&amp;module=config/calendars&amp;action=add",
	);
}

if($mybb->input['action'] == "add")
{
	if($mybb->request_method == "post")
	{
		if(!trim($mybb->input['name']))
		{
			$errors[] = "You did not enter a name for this calendar";
		}

		if(!isset($mybb->input['disporder']))
		{
			$errors[] = "You did not enter a display order for this calendar";
		}

		if(!$errors)
		{
			$calendar = array(
				"name" => $db->escape_string($mybb->input['name']),
				"disporder" => intval($mybb->input['disporder']),
				"startofweek" => intval($mybb->input['startofweek']),
				"eventlimit" => intval($mybb->input['eventlimit']),
				"showbirthdays" => intval($mybb->input['showbirthdays']),
				"moderation" => intval($mybb->input['moderation']),
				"allowhtml" => $db->escape_string($mybb->input['allowhtml']),
				"allowmycode" => $db->escape_string($mybb->input['allowmycode']),
				"allowimgcode" => $db->escape_string($mybb->input['allowimgcode']),
				"allowsmilies" => $db->escape_string($mybb->input['allowsmilies'])
			);
			
			$db->insert_query("calendars", $calendar);
			
			flash_message("The calendar has successfully been created.", 'success');
			admin_redirect("index.php?".SID."&module=config/calendars");
		}
	}
	else
	{
		$mybb->input = array(
			"allowhtml" => "no",
			"eventlimit" => 4,
			"disporder" => 1,
			"moderation" => 0
		);
	}
	
	$page->add_breadcrumb_item("Add Calendar");
	$page->output_header("Calendars - Add Calendar");
	
	$sub_tabs['add_calendar'] = array(
		'title' => "Add Calendar",
		'description' => 'Here you can create a new calendar.'
	);
	
	$page->output_nav_tabs($sub_tabs, 'add_calendar');
	$form = new Form("index.php?".SID."&amp;module=config/calendars&amp;action=add", "post");
	
	
	if($errors)
	{
		$page->output_inline_error($errors);
	}

	$form_container = new FormContainer("Add Calendar");
	$form_container->output_row("Name", "", $form->generate_text_box('name', $mybb->input['name'], array('id' => 'name')), 'name');
	$form_container->output_row("Display Order", "The order this calendar will be shown in the calendar jump menu. Set to 1 to also make this calendar the default.", $form->generate_text_box('disporder', $mybb->input['disporder'], array('id' => 'disporder')), 'disporder');
	$select_list = array("Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday");
	$form_container->output_row("Start of Week", "Here you can set the day weeks should start on for this calendar.", $form->generate_select_box('startofweek', $select_list, $mybb->input['startofweek'], array('id' => 'startofweek')), 'startofweek');
	$form_container->output_row("Event Limit", "The number of events to be shown before a single link to all events on the particular day is shown instead.", $form->generate_text_box('eventlimit', $mybb->input['eventlimit'], array('id' => 'eventlimit')), 'eventlimit');
	$form_container->output_row("Show Birthdays?", "Do you wish to show birthdays of registered users in this calendar?", $form->generate_yes_no_radio('showbirthdays', $mybb->input['showbirthdays'], true), 'showbirthdays');
	$form_container->output_row("Moderate New Events?", "If this option all events will be moderated apart from those created by members with 'Bypass moderation queue' set for their calendar permissions.", $form->generate_yes_no_radio('moderation', $mybb->input['moderation'], true), 'moderation');
	$form_container->output_row("Allow HTML in Events?", "", $form->generate_yes_no_radio('allowhtml', $mybb->input['allowhtml']), 'allowhtml');
	$form_container->output_row("Allow MyCode in Events?", "", $form->generate_yes_no_radio('allowmycode', $mybb->input['allowmycode']), 'allowmycode');
	$form_container->output_row("Allow [IMG] Code in Events?", "", $form->generate_yes_no_radio('allowimgcode', $mybb->input['allowimgcode']), 'allowimgcode');
	$form_container->output_row("Allow Smilies in Events", "", $form->generate_yes_no_radio('allowsmilies', $mybb->input['allowsmilies']), 'allowsmilies');
	$form_container->end();

	$buttons[] = $form->generate_submit_button("Save Calendar");

	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

if($mybb->input['action'] == "permissions")
{
	$query = $db->simple_select("calendars", "*", "cid='".intval($mybb->input['cid'])."'");
	$calendar = $db->fetch_array($query);

	// Does the calendar not exist?
	if(!$calendar['cid'])
	{
		flash_message('The specified calendar does not exist.', 'error');
		admin_redirect("index.php?".SID."&module=config/calendar");
	}

	$query = $db->simple_select("usergroups", "*", "", array("order_dir" => "name"));
	while($usergroup = $db->fetch_array($query))
	{
		$usergroups[$usergroup['gid']] = $usergroup;
	}

	$query = $db->simple_select("calendarpermissions", "*", "cid='{$calendar['cid']}'");
	while($existing = $db->fetch_array($query))
	{
		$existing_permissions[$existing['gid']] = $existing;
	}
	
	if($mybb->request_method == "post")
	{
		foreach($mybb->input['permissions'] as $group_id => $permissions)
		{
			$db->delete_query("calendarpermissions", "cid='{$calendar['cid']}' AND gid='".intval($group_id)."'");

			if(!$mybb->input['default_permissions'][$group_id])
			{
				foreach(array('canviewcalendar','canaddevents','canbypasseventmod','canmoderateevents') as $calendar_permission)
				{
					if($permissions[$calendar_permission] == 1)
					{
						$permissions_array[$calendar_permission] = "yes";
					}
					else
					{
						$permissions_array[$calendar_permission] = "no";
					}
				}
				$permissions_array['gid'] = intval($group_id);
				$permissions_array['cid'] = $calendar['cid'];
				$db->insert_query("calendarpermissions", $permissions_array);
			}
		}
		flash_message("The calendar permissions has successfully been updated.", 'success');
		admin_redirect("index.php?".SID."&module=config/calendars");
	}
	
	$calendar['name'] = htmlspecialchars_uni($calendar['name']);
	$page->add_breadcrumb_item($calendar['name'], "index.php?".SID."&amp;module=config/calendars&action=edit&cid={$calendar['cid']}");
	$page->add_breadcrumb_item("Permissions");
	$page->output_header("Calendars - Edit Calendar Permissions");

	$form = new Form("index.php?".SID."&amp;module=config/calendars&amp;action=permissions", "post");
	echo $form->generate_hidden_field("cid", $calendar['cid']);

	$table = new Table;
	$table->construct_header("Group");
	$table->construct_header("View", array("class" => "align_center", "width" => "10%"));
	$table->construct_header("Post Events", array("class" => "align_center", "width" => "10%"));
	$table->construct_header("Bypass Moderation", array("class" => "align_center", "width" => "10%"));
	$table->construct_header("Moderator Permissions", array("class" => "align_center", "width" => "10%"));
	$table->construct_header("All?", array("class" => "align_center", "width" => "10%"));
	
	foreach($usergroups as $usergroup)
	{
		if($existing_permissions[$usergroup['gid']])
		{
			$perms = $existing_permissions[$usergroup['gid']];
			$default_checked = false;
		}
		else
		{
			$perms = $usergroup;
			$default_checked = true;
		}
		$perm_check = "";
		$all_checked = true;
		foreach(array('canviewcalendar','canaddevents','canbypasseventmod','canmoderateevents') as $calendar_permission)
		{
			if($usergroup[$calendar_permission] == "yes")
			{
				$value = "true";
			}
			else
			{
				$value = "false";
			}
			if($perms[$calendar_permission] != "yes")
			{
				$all_checked = false;
			}
			if($perms[$calendar_permission] == "yes")
			{
				$perms_checked[$calendar_permission] = 1;
			}
			else
			{
				$perms_checked[$calendar_permission] = 0;
			}
			$all_check .= "\$('permissions_{$usergroup['gid']}_{$calendar_permission}').checked = \$('permissions_{$usergroup['gid']}_all').checked;\n";
			$perm_check .= "\$('permissions_{$usergroup['gid']}_{$calendar_permission}').checked = $value;\n";
		}
		$default_click = "if(this.checked == true) { $perm_check }";
		$reset_default = "\$('default_permissions_{$usergroup['gid']}').checked = false; if(this.checked == false) { \$('permissions_{$usergroup['gid']}_all').checked = false; }\n";
		$usergroup['title'] = htmlspecialchars_uni($usergroup['title']);
		$table->construct_cell("<strong>{$usergroup['title']}</strong><br /><small style=\"vertical-align: middle;\">".$form->generate_check_box("default_permissions[{$usergroup['gid']}];", 1, "", array("id" => "default_permissions_{$usergroup['gid']}", "checked" => $default_checked, "onclick" => $default_click))." <label for=\"default_permissions_{$usergroup['gid']}\">Use Group Default</label></small>");
		$table->construct_cell($form->generate_check_box("permissions[{$usergroup['gid']}][canviewcalendar]", 1, "", array("id" => "permissions_{$usergroup['gid']}_canviewcalendar", "checked" => $perms_checked['canviewcalendar'], "onclick" => $reset_default)), array('class' => 'align_center'));
		$table->construct_cell($form->generate_check_box("permissions[{$usergroup['gid']}][canaddevents]", 1, "", array("id" => "permissions_{$usergroup['gid']}_canaddevents", "checked" => $perms_checked['canaddevents'], "onclick" => $reset_default)), array('class' => 'align_center'));
		$table->construct_cell($form->generate_check_box("permissions[{$usergroup['gid']}][canbypasseventmod]", 1, "", array("id" => "permissions_{$usergroup['gid']}_canbypasseventmod", "checked" => $perms_checked['canbypasseventmod'], "onclick" => $reset_default)), array('class' => 'align_center'));
		$table->construct_cell($form->generate_check_box("permissions[{$usergroup['gid']}][canmoderateevents]", 1, "", array("id" => "permissions_{$usergroup['gid']}_canmoderateevents", "checked" => $perms_checked['canmoderateevents'], "onclick" => $reset_default)), array('class' => 'align_center'));
		$table->construct_cell($form->generate_check_box("permissions[{$usergroup['gid']}][all]", 1, "", array("id" => "permissions_{$usergroup['gid']}_all", "checked" => $all_checked, "onclick" => $all_check)), array('class' => 'align_center'));
		$table->construct_row();
	}
	$table->output("Calendar Permissions for {$calendar['name']}");

	if(!$no_results)
	{
		$buttons[] = $form->generate_submit_button("Save Permissions");
		$form->output_submit_wrapper($buttons);
	}

	$form->end();

	$page->output_footer();

}

if($mybb->input['action'] == "edit")
{
	$query = $db->simple_select("calendars", "*", "cid='".intval($mybb->input['cid'])."'");
	$calendar = $db->fetch_array($query);

	// Does the calendar not exist?
	if(!$calendar['cid'])
	{
		flash_message('The specified calendar does not exist.', 'error');
		admin_redirect("index.php?".SID."&module=config/calendar");
	}

	if($mybb->request_method == "post")
	{
		if(!trim($mybb->input['name']))
		{
			$errors[] = "You did not enter a name for this calendar";
		}

		if(!isset($mybb->input['disporder']))
		{
			$errors[] = "You did not enter a display order for this calendar";
		}

		if(!$errors)
		{
			$calendar = array(
				"name" => $db->escape_string($mybb->input['name']),
				"disporder" => intval($mybb->input['disporder']),
				"startofweek" => intval($mybb->input['startofweek']),
				"eventlimit" => intval($mybb->input['eventlimit']),
				"showbirthdays" => intval($mybb->input['showbirthdays']),
				"moderation" => intval($mybb->input['moderation']),
				"allowhtml" => $db->escape_string($mybb->input['allowhtml']),
				"allowmycode" => $db->escape_string($mybb->input['allowmycode']),
				"allowimgcode" => $db->escape_string($mybb->input['allowimgcode']),
				"allowsmilies" => $db->escape_string($mybb->input['allowsmilies'])
			);
			
			$db->update_query("calendars", $calendar, "cid = '".intval($mybb->input['cid'])."'");
			
			flash_message("The calendar has successfully been updated.", 'success');
			admin_redirect("index.php?".SID."&module=config/calendars");
		}
	}
	
	$page->add_breadcrumb_item("Edit Calendar");
	$page->output_header("Calendars - Edit Calendar");
	
	$sub_tabs['edit_calendar'] = array(
		'title' => "Edit Calendar",
		'link' => "index.php?".SID."&amp;module=config/smilies&amp;action=edit",
		'description' => 'Here you can edit the settings for this calendar.'
	);
	
	$page->output_nav_tabs($sub_tabs, 'edit_calendar');
	$form = new Form("index.php?".SID."&amp;module=config/calendars&amp;action=edit", "post");
	
	echo $form->generate_hidden_field("cid", $calendar['cid']);
	
	if($errors)
	{
		$page->output_inline_error($errors);
	}
	else
	{
		$mybb->input = $calendar;
	}

	$form_container = new FormContainer("Edit Calendar");
	$form_container->output_row("Name", "", $form->generate_text_box('name', $mybb->input['name'], array('id' => 'name')), 'name');
	$form_container->output_row("Display Order", "The order this calendar will be shown in the calendar jump menu. Set to 1 to also make this calendar the default.", $form->generate_text_box('disporder', $mybb->input['disporder'], array('id' => 'disporder')), 'disporder');
	$select_list = array("Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday");
	$form_container->output_row("Start of Week", "Here you can set the day weeks should start on for this calendar.", $form->generate_select_box('startofweek', $select_list, $mybb->input['startofweek'], array('id' => 'startofweek')), 'startofweek');
	$form_container->output_row("Event Limit", "The number of events to be shown before a single link to all events on the particular day is shown instead.", $form->generate_text_box('eventlimit', $mybb->input['eventlimit'], array('id' => 'eventlimit')), 'eventlimit');
	$form_container->output_row("Show Birthdays?", "Do you wish to show birthdays of registered users in this calendar?", $form->generate_yes_no_radio('showbirthdays', $mybb->input['showbirthdays'], true), 'showbirthdays');
	$form_container->output_row("Moderate New Events?", "If this option all events will be moderated apart from those created by members with 'Bypass moderation queue' set for their calendar permissions.", $form->generate_yes_no_radio('moderation', $mybb->input['moderation'], true), 'moderation');
	$form_container->output_row("Allow HTML in Events?", "", $form->generate_yes_no_radio('allowhtml', $mybb->input['allowhtml']), 'allowhtml');
	$form_container->output_row("Allow MyCode in Events?", "", $form->generate_yes_no_radio('allowmycode', $mybb->input['allowmycode']), 'allowmycode');
	$form_container->output_row("Allow [IMG] Code in Events?", "", $form->generate_yes_no_radio('allowimgcode', $mybb->input['allowimgcode']), 'allowimgcode');
	$form_container->output_row("Allow Smilies in Events", "", $form->generate_yes_no_radio('allowsmilies', $mybb->input['allowsmilies']), 'allowsmilies');
	$form_container->end();

	$buttons[] = $form->generate_submit_button("Save Calendar");

	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

if($mybb->input['action'] == "delete")
{
	$query = $db->simple_select("calendars", "*", "sid='".intval($mybb->input['cid'])."'");
	$calendar = $db->fetch_array($query);

	// Does the calendar not exist?
	if(!$calendar['cid'])
	{
		flash_message('The specified calendar does not exist.', 'error');
		admin_redirect("index.php?".SID."&module=config/calendar");
	}

	// User clicked no
	if($mybb->input['no'])
	{
		admin_redirect("index.php?".SID."&module=config/calendar");
	}

	if($mybb->request_method == "post")
	{
		// Delete the calendar
		$db->delete_query("calendars", "cid='{$calendar['cid']}'");
		$db->delete_query("events", "cid='{$calendar['cid']}'");

		flash_message('The specified calendar has been deleted.', 'success');
		admin_redirect("index.php?".SID."&module=config/calendars");
	}
	else
	{
		$page->output_confirm_action("index.php?".SID."&amp;module=config/calendar&amp;action=delete&amp;cid={$calendar['cid']}", "Are you sure you wish to delete this calendar?");
	}
}

if($mybb->input['action'] == "update_order" && $mybb->request_method == "post")
{
	if(!is_array($mybb->input['disporder']))
	{
		admin_redirect("index.php?".SID."&module=config/calendars");
	}

	foreach($mybb->input['disporder'] as $cid => $order)
	{
		$update_query = array(
			"disporder" => intval($order)
		);
		$db->update_query("calendars", $update_query, "cid='".intval($cid)."'");
	}

	flash_message('The calendar display orders have been updated.', 'success');
	admin_redirect("index.php?".SID."&module=config/calendars");
}

if(!$mybb->input['action'])
{
	$page->output_header("Manage Calendars");

	$page->output_nav_tabs($sub_tabs, 'manage_calendars');

	$form = new Form("index.php?".SID."&amp;module=config/calendars&amp;action=update_order", "post");
	$table = new Table;
	$table->construct_header("Calendar");
	$table->construct_header("Order", array('width' => '5%', 'class' => 'align_center'));
	$table->construct_header("Controls", array("class" => "align_center", "colspan" => 3, "width" => 300));
	
	$query = $db->simple_select("calendars", "*", "", array('order_by' => 'disporder'));
	while($calendar = $db->fetch_array($query))
	{
		$calendar['name'] = htmlspecialchars_uni($calendar['name']);
		$table->construct_cell("<a href=\"index.php?".SID."&amp;module=config/calendars&amp;action=edit&amp;cid={$calendar['cid']}\">{$calendar['name']}</a>");
		$table->construct_cell($form->generate_text_box("disporder[{$calendar['cid']}]", $calendar['disporder'], array('id' => 'disporder', 'style' => 'width: 80%')));
		$table->construct_cell("<a href=\"index.php?".SID."&amp;module=config/calendars&amp;action=edit&amp;cid={$calendar['cid']}\">Edit</a>", array("width" => 100, "class" => "align_center"));
		$table->construct_cell("<a href=\"index.php?".SID."&amp;module=config/calendars&amp;action=permissions&amp;cid={$calendar['cid']}\">Permissions</a>", array("width" => 100, "class" => "align_center"));
		$table->construct_cell("<a href=\"index.php?".SID."&amp;module=config/calendars&amp;action=delete&amp;cid={$calendar['cid']}\" onclick=\"return AdminCP.deleteConfirmation(this, 'Are you sure you wish to delete this calendar?')\">Delete</a>", array("width" => 100, "class" => "align_center"));
		$table->construct_row();
	}
	
	if(count($table->rows) == 0)
	{
		$table->construct_cell("There are no calendars on your forum at this time.", array('colspan' => 4));
		$table->construct_row();
		$no_results = true;
	}
	
	$table->output("Manage Calendars");

	if(!$no_results)
	{
		$buttons[] = $form->generate_submit_button("Save Calendar Display Order");
		$form->output_submit_wrapper($buttons);
	}

	$form->end();

	$page->output_footer();
}
?>