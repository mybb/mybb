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


$page->add_breadcrumb_item("User Group Promotions", "index.php?".SID."&amp;module=user/group_promotions");

if($mybb->input['action'] == "disable")
{
	if(!trim($mybb->input['pid']))
	{
		flash_message('You did not enter a promotion id', 'error');
		admin_redirect("index.php?".SID."&module=user/group_promotions");
	}
	
	$query = $db->simple_select("promotions", "COUNT(pid) as promotions", "pid = '{$mybb->input['pid']}'");
	if($db->fetch_field($query, 'promotions') == 0)
	{
		flash_message('You did not enter a valid promotion id', 'error');
		admin_redirect("index.php?".SID."&module=user/group_promotions");
	}

	$promotion = array(
		"enabled" => 0
	);
		
	$db->update_query("promotions", $promotion, "pid = '{$mybb->input['pid']}'");
	flash_message('The promotion has successfully been disabled.', 'success');
	admin_redirect("index.php?".SID."&module=user/group_promotions");
}

if($mybb->input['action'] == "delete")
{
	if($mybb->input['no']) 
	{ 
		admin_redirect("index.php?".SID."&module=user/group_promotions"); 
	} 
	
	if(!trim($mybb->input['pid']))
	{
		flash_message('You did not enter a promotion id', 'error');
		admin_redirect("index.php?".SID."&module=user/group_promotions");
	}
	
	$query = $db->simple_select("promotions", "COUNT(pid) as promotions", "pid = '{$mybb->input['pid']}'");
	if($db->fetch_field($query, 'promotions') == 0)
	{
		flash_message('You did not enter a valid promotion id', 'error');
		admin_redirect("index.php?".SID."&module=user/group_promotions");
	}
	
	if($mybb->request_method == "post")
	{
		$db->delete_query("promotions", "pid = '{$mybb->input['pid']}'");
		flash_message('The promotion has successfully been deleted.', 'success');
		admin_redirect("index.php?".SID."&module=user/group_promotions");
	}
	else
	{
		$page->output_confirm_action("index.php?".SID."&amp;module=user/group_promotions&amp;action=delete&amp;pid={$mybb->input['pid']}", "Are you sure you wish to delete this promotion?"); 
	}
}

if($mybb->input['action'] == "enable")
{
	if(!trim($mybb->input['pid']))
	{
		flash_message('You did not enter a promotion id', 'error');
		admin_redirect("index.php?".SID."&module=user/group_promotions");
	}
	
	$query = $db->simple_select("promotions", "COUNT(pid) as promotions", "pid = '{$mybb->input['pid']}'");
	if($db->fetch_field($query, 'promotions') == 0)
	{
		flash_message('You did not enter a valid promotion id', 'error');
		admin_redirect("index.php?".SID."&module=user/group_promotions");
	}

	$promotion = array(
		"enabled" => 1
	);
		
	$db->update_query("promotions", $promotion, "pid = '{$mybb->input['pid']}'");
	flash_message('The promotion has successfully been enabled.', 'success');
	admin_redirect("index.php?".SID."&module=user/group_promotions");
}

if($mybb->input['action'] == "edit")
{
	if($mybb->request_method == "post")
	{
		if(!trim($mybb->input['title']))
		{
			$errors[] = "You did not enter a title for this promotion";
		}

		if(!trim($mybb->input['description']))
		{
			$errors[] = "You did not enter a description for this promotion";
		}
		
		if(!trim($mybb->input['requirements']))
		{
			$errors[] = "You did not select at least one requirement for this promotion";
		}

		if(!trim($mybb->input['originalusergroup']))
		{
			$errors[] = "You did not select at least one original user group for this promotion";
		}
		
		if(!trim($mybb->input['newusergroup']))
		{
			$errors[] = "You did not select at least one new usergroup for this promotion";
		}
		
		if(!trim($mybb->input['usergroupchangetype']))
		{
			$errors[] = "You did not select at least one user group change type for this promotion";
		}

		if(!$errors)
		{
			$update_promotion = array(
				"title" => $db->escape_string($mybb->input['title']),
				"description" => $db->escape_string($mybb->input['description']),
				"posts" => intval($mybb->input['postcount']),
				"posttype" => $db->escape_string($mybb->input['posttype']),
				"registered" => intval($mybb->input['timeregistered']),
				"registeredtype" => $db->escape_string($mybb->input['timeregisteredtype']),
				"reputations" => intval($mybb->input['reputationcount']),
				"reputationtype" => $db->escape_string($mybb->input['reputationtype']),
				"requirements" => $db->escape_string(implode(",", $mybb->input['requirements'])),
				"originalusergroup" => $db->escape_string(implode(",", $mybb->input['originalusergroup'])),
				"newusergroup" => intval($mybb->input['newusergroup']),
				"usergrouptype" => $db->escape_string($mybb->input['usergroupchangetype']),
				"enabled" => intval($mybb->input['enabled']),
				"logging" => intval($mybb->input['logging'])
			);
			
			$db->update_query("promotions", $update_promotion, "pid = '{$mybb->input['pid']}'");
			flash_message('The promotion has successfully been updated.', 'success');
			admin_redirect("index.php?".SID."&module=user/group_promotions");
		}
	}
	
	if(!trim($mybb->input['pid']))
	{
		flash_message('You did not enter a promotion id', 'error');
		admin_redirect("index.php?".SID."&module=user/group_promotions");
	}
	
	$query = $db->simple_select("promotions", "*", "pid = '{$mybb->input['pid']}'");
	$promotion = $db->fetch_array($query);
	
	if(!$promotion)
	{
		flash_message('Invalid promotion id specified.', 'error');
		admin_redirect("index.php?".SID."&module=user/group_promotions");
	}
	
	$page->add_breadcrumb_item("Edit Promotion");
	$page->output_header("User Group Promotions - Edit Promotion");

	$sub_tabs['edit_promotion'] = array(
		'title' => "Edit Promotion",
		'link' => "index.php?".SID."&amp;module=user/group_promotions&amp;action=edit",
		'description' => "Here you can edit promotions which are automatically run on your board."
	);

	$page->output_nav_tabs($sub_tabs, 'edit_promotion');
	$form = new Form("index.php?".SID."&amp;module=user/group_promotions&amp;action=edit", "post", "edit");
	$form->generate_hidden_field("pid", $mybb->input['pid']);
	if($errors)
	{
		$page->output_inline_error($errors);
	}
	else
	{	
		$mybb->input['title'] = $promotion['title'];
		$mybb->input['description'] = $promotion['description'];
		$mybb->input['requirements'] = explode(',', $promotion['requirements']);
		$mybb->input['reputationcount'] = $promotion['reputations'];
		$mybb->input['reputationtype'] = $promotion['reputationtype'];
		$mybb->input['postcount'] = $promotion['posts'];
		$mybb->input['posttype'] = $promotion['posttype'];
		$mybb->input['timeregistered'] = $promotion['registered'];
		$mybb->input['timeregisteredtype'] = $promotion['registeredtype'];
		$mybb->input['originalusergroup'] = $promotion['originalusergroup'];
		$mybb->input['usergroupchangetype'] = $promotion['usergrouptype'];
		$mybb->input['newusergroup'] = $promotion['newusergroup'];
		$mybb->input['enabled'] = $promotion['enabled'];
		$mybb->input['logging'] = $promotion['logging'];
	}
	
	$form_container = new FormContainer("Edit Promotion");
	$form_container->output_row("Title <em>*</em>", "", $form->generate_text_box('title', $mybb->input['title'], array('id' => 'title')), 'title');
	$form_container->output_row("Short Description <em>*</em>", "", $form->generate_text_box('description', $mybb->input['description'], array('id' => 'description')), 'description');

	$options = array(
		"postcount" => "Post Count",
		"reputation" => "Reputation",
		"timeregistered" => "Time Registered"
	);
	
	$form_container->output_row("Promotion Requirements <em>*</em>", "Select which values must be met for this promotion. Holding down CTRL selects multiple weekdays. Select 'Every weekday' if you want this task to run each weekday or you have entered a predefined day above. Select the type of comparision for posts.", $form->generate_select_box('requirements', $options, $mybb->input['requirements'], array('id' => 'requirements', 'multiple' => true)), 'requirements');
	
	$options_type = array(
		"greatthanorequalto" => "Greater than or equal to",
		"greaterthank" => "Greater than",
		"equalto" => "Equal to",
		"lessthanorequalto" => "Less than or equal to",
		"lessthan" => "Less than"
	);
	
	$form_container->output_row("Reputation Count", "Enter the amount of reputation to be required. Reputation must be selected as a required value for this to be included. Select the type of comparison for reputation.", $form->generate_text_box('reputationcount', $mybb->input['reputationcount'], array('id' => 'reputationcount'))." ".$form->generate_select_box("reputationtype", $options_type, $mybb->input['reputationtype'], array('id' => 'reputationtype')), 'reputationcount');
	
	$form_container->output_row("Post Count", "Enter the number of posts required. Post count must be selected as a required value for this to be included. Select the type of comparison for posts.", $form->generate_text_box('postcount', $mybb->input['postcount'], array('id' => 'postcount'))." ".$form->generate_select_box("posttype", $options_type, $mybb->input['posttype'], array('id' => 'posttype')), 'postcount');
	
	$options = array(
		"hours" => "Hours",
		"days" => "Days",
		"weeks" => "Weeks",
		"months" => "Months",
		"years" => "Years"
	);	
	
	$form_container->output_row("Time Registered", "Enter the number of hours, days, weeks, months, or years that this user must have been registered for. Time registered must be selected as a required value for this to be included. Select whether the time registered should be counted in hours, days, weeks, months, or years.", $form->generate_text_box('timeregistered', $mybb->input['timeregistered'], array('id' => 'timeregistered'))." ".$form->generate_select_box("timeregisteredtype", $options, $mybb->input['timeregisteredtype'], array('id' => 'timeregisteredtype')), 'timeregistered');
	$options = array(
		'*' => 'All User Groups'
	);
	
	$query = $db->simple_select("usergroups", "gid, title", "gid != '1'");
	while($usergroup = $db->fetch_array($query))
	{
		$options[$usergroup['gid']] = $usergroup['title'];
	}

	$form_container->output_row("Original User Group <em>*</em>", "Select which user group or user groups that the user must be in for the promotion to run. Holding down CTRL selects multiple groups. Select 'All User Groups' if you want this promotion to be available for any user group.", $form->generate_select_box('originalusergroup', $options, $mybb->input['originalusergroup'], array('id' => 'originalusergroup', 'multiple' => true)), 'originalusergroup');

	unset($options['*']);

	$form_container->output_row("New User Group <em>*</em>", "Select the user group that the user will be moved into after this promotion.", $form->generate_select_box('newusergroup', $options, $mybb->input['newusergroup'], array('id' => 'newusergroup')), 'newusergroup');
	
	$options = array(
		'primary' => 'Primary User Group',
		'secondary' => 'Secondary User Group'
	);
	
	$form_container->output_row("User Group Change Type <em>*</em>", "Select 'Primary User Group' if the user should have their primary user group changed to the new user group. Select 'Additional User Group' if the user should have the new user group added as an additional user group to their profile.", $form->generate_select_box('usergroupchangetype', $options, $mybb->input['usergroupchangetype'], array('id' => 'usergroupchangetype')), 'usergroupchangetype');

	$form_container->output_row("Enabled? <em>*</em>", "", $form->generate_yes_no_radio("enabled", $mybb->input['enabled']));
	
	$form_container->output_row("Enable Logging? <em>*</em>", "", $form->generate_yes_no_radio("logging", $mybb->input['logging']));
	$form_container->end();

	$buttons[] = $form->generate_submit_button("Update Promotion");

	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

if($mybb->input['action'] == "add")
{
	if($mybb->request_method == "post")
	{
		if(!trim($mybb->input['title']))
		{
			$errors[] = "You did not enter a title for this promotion";
		}

		if(!trim($mybb->input['description']))
		{
			$errors[] = "You did not enter a description for this promotion";
		}
		
		if(!trim($mybb->input['requirements']))
		{
			$errors[] = "You did not select at least one requirement for this promotion";
		}

		if(!trim($mybb->input['originalusergroup']))
		{
			$errors[] = "You did not select at least one original user group for this promotion";
		}
		
		if(!trim($mybb->input['newusergroup']))
		{
			$errors[] = "You did not select at least one new usergroup for this promotion";
		}
		
		if(!trim($mybb->input['usergroupchangetype']))
		{
			$errors[] = "You did not select at least one user group change type for this promotion";
		}

		if(!$errors)
		{
			$new_promotion = array(
				"title" => $db->escape_string($mybb->input['title']),
				"description" => $db->escape_string($mybb->input['description']),
				"posts" => intval($mybb->input['postcount']),
				"posttype" => $db->escape_string($mybb->input['posttype']),
				"registered" => intval($mybb->input['timeregistered']),
				"registeredtype" => $db->escape_string($mybb->input['timeregisteredtype']),
				"reputations" => intval($mybb->input['reputationcount']),
				"reputationtype" => $db->escape_string($mybb->input['reputationtype']),
				"requirements" => $db->escape_string(implode(",", $mybb->input['requirements'])),
				"originalusergroup" => $db->escape_string(implode(",", $mybb->input['originalusergroup'])),
				"newusergroup" => intval($mybb->input['newusergroup']),
				"usergrouptype" => $db->escape_string($mybb->input['usergroupchangetype']),
				"enabled" => intval($mybb->input['enabled']),
				"logging" => intval($mybb->input['logging'])
			);
			
			$db->insert_query("promotions", $new_promotion);
			flash_message('The promotion has successfully been added.', 'success');
			admin_redirect("index.php?".SID."&module=user/group_promotions");
		}
	}
	$page->add_breadcrumb_item("Add New Promotion");
	$page->output_header("User Group Promotions - Add New Promotion");

	$sub_tabs['add_promotion'] = array(
		'title' => "Add New Promotion",
		'link' => "index.php?".SID."&amp;module=user/group_promotions&amp;action=add",
		'description' => "Here you can create new promotions which are automatically run on your board."
	);

	$page->output_nav_tabs($sub_tabs, 'add_promotion');
	$form = new Form("index.php?".SID."&amp;module=user/group_promotions&amp;action=add", "post", "add");
	if($errors)
	{
		$page->output_inline_error($errors);
	}
	else
	{
		$mybb->input['reputationcount'] = '0';
		$mybb->input['postcount'] = '0';
		$mybb->input['timeregistered'] = '0';
		$mybb->input['timeregisteredtype'] = 'days';
		$mybb->input['originalusergroup'] = '*';
		$mybb->input['newusergroup'] = '2';
		$mybb->input['enabled'] = '1';
		$mybb->input['logging'] = '1';
	}
	$form_container = new FormContainer("Add New Promotion");
	$form_container->output_row("Title <em>*</em>", "", $form->generate_text_box('title', $mybb->input['title'], array('id' => 'title')), 'title');
	$form_container->output_row("Short Description <em>*</em>", "", $form->generate_text_box('description', $mybb->input['description'], array('id' => 'description')), 'description');

	$options = array(
		"postcount" => "Post Count",
		"reputation" => "Reputation",
		"timeregistered" => "Time Registered"
	);
	
	$form_container->output_row("Promotion Requirements <em>*</em>", "Select which values must be met for this promotion. Holding down CTRL selects multiple weekdays. Select 'Every weekday' if you want this task to run each weekday or you have entered a predefined day above. Select the type of comparision for posts.", $form->generate_select_box('requirements', $options, $mybb->input['requirements'], array('id' => 'requirements', 'multiple' => true)), 'requirements');
	
	$options_type = array(
		"greatthanorequalto" => "Greater than or equal to",
		"greaterthank" => "Greater than",
		"equalto" => "Equal to",
		"lessthanorequalto" => "Less than or equal to",
		"lessthan" => "Less than"
	);
	
	$form_container->output_row("Reputation Count", "Enter the amount of reputation to be required. Reputation must be selected as a required value for this to be included. Select the type of comparison for reputation.", $form->generate_text_box('reputationcount', $mybb->input['reputationcount'], array('id' => 'reputationcount'))." ".$form->generate_select_box("reputationtype", $options_type, $mybb->input['reputationtype'], array('id' => 'reputationtype')), 'reputationcount');
	
	$form_container->output_row("Post Count", "Enter the number of posts required. Post count must be selected as a required value for this to be included. Select the type of comparison for posts.", $form->generate_text_box('postcount', $mybb->input['postcount'], array('id' => 'postcount'))." ".$form->generate_select_box("posttype", $options_type, $mybb->input['posttype'], array('id' => 'posttype')), 'postcount');
	
	$options = array(
		"hours" => "Hours",
		"days" => "Days",
		"weeks" => "Weeks",
		"months" => "Months",
		"years" => "Years"
	);
	
	
	$form_container->output_row("Time Registered", "Enter the number of hours, days, weeks, months, or years that this user must have been registered for. Time registered must be selected as a required value for this to be included. Select whether the time registered should be counted in hours, days, weeks, months, or years.", $form->generate_text_box('timeregistered', $mybb->input['timeregistered'], array('id' => 'timeregistered'))." ".$form->generate_select_box("timeregisteredtype", $options, $mybb->input['timeregisteredtype'], array('id' => 'timeregisteredtype')), 'timeregistered');
	$options = array(
		'*' => 'All User Groups'
	);
	
	$query = $db->simple_select("usergroups", "gid, title", "gid != '1'");
	while($usergroup = $db->fetch_array($query))
	{
		$options[$usergroup['gid']] = $usergroup['title'];
	}

	$form_container->output_row("Original User Group <em>*</em>", "Select which user group or user groups that the user must be in for the promotion to run. Holding down CTRL selects multiple groups. Select 'All User Groups' if you want this promotion to be available for any user group.", $form->generate_select_box('originalusergroup', $options, $mybb->input['originalusergroup'], array('id' => 'originalusergroup', 'multiple' => true)), 'originalusergroup');

	unset($options['*']);

	$form_container->output_row("New User Group <em>*</em>", "Select the user group that the user will be moved into after this promotion.", $form->generate_select_box('newusergroup', $options, $mybb->input['newusergroup'], array('id' => 'newusergroup')), 'newusergroup');
	
	$options = array(
		'primary' => 'Primary User Group',
		'secondary' => 'Secondary User Group'
	);
	
	$form_container->output_row("User Group Change Type <em>*</em>", "Select 'Primary User Group' if the user should have their primary user group changed to the new user group. Select 'Additional User Group' if the user should have the new user group added as an additional user group to their profile.", $form->generate_select_box('usergroupchangetype', $options, $mybb->input['usergroupchangetype'], array('id' => 'usergroupchangetype')), 'usergroupchangetype');

	$form_container->output_row("Enabled? <em>*</em>", "", $form->generate_yes_no_radio("enabled", $mybb->input['enabled']));
	
	$form_container->output_row("Enable Logging? <em>*</em>", "", $form->generate_yes_no_radio("logging", $mybb->input['logging']));
	$form_container->end();

	$buttons[] = $form->generate_submit_button("Save New Promotion");

	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

if($mybb->input['action'] == "logs")
{
	if($mybb->input['page'] && $mybb->input['page'] > 1)
	{
		$mybb->input['page'] = intval($mybb->input['page']);
		$start = ($mybb->input['page']*20)-20;
	}
	else
	{
		$mybb->input['page'] = 1;
		$start = 0;
	}
	
	$page->add_breadcrumb_item("Promotion Logs");
	$page->output_header("User Group Promotions - Promotion Logs");
	
	$sub_tabs['promotion_logs'] = array(
		'title' => "View Promotion Logs",
		'link' => "index.php?".SID."&amp;module=user/group_promotions&amp;action=logs",
		'description' => 'Here you can view logs of promotions previously run.'
	);

	$page->output_nav_tabs($sub_tabs, 'promotion_logs');

	$table = new Table;
	$table->construct_header("Promoted User", array("class" => "align_center", "width" => '25%'));
	$table->construct_header("Old User Group", array("class" => "align_center", "width" => '25%'));
	$table->construct_header("New User Group", array("class" => "align_center", "width" => '25%'));
	$table->construct_header("Time Promoted", array("class" => "align_center", "width" => '25%'));

	$query = $db->simple_select("promotionlogs", "*", "", array("order_by" => "dateline", "order_dir" => "desc", "limit_start" => $start, "limit" => "20"));
	while($log = $db->fetch_array($query))
	{
		$log['username'] = "<a href=\"index.php?".SID."&amp;module=user/view&amp;action=edit&amp;uid={$log['uid']}\">".htmlspecialchars_uni($promotion['username'])."</a>";
		$log['oldusergroup'] = htmlspecialchars_uni($log['oldusergroup']);
		$log['newusergroup'] = htmlspecialchars_uni($log['newusergroup']);
		$log['dateline'] = date($mybb->settings['dateformat'], $log['dateline']).", ".date($mybb->settings['timeformat'], $log['dateline']);
		$table->construct_cell($log['username']);
		$table->construct_cell($log['oldusergroup']);
		$table->construct_cell($log['newusergroup']);
		$table->construct_cell($log['dateline']);
		$table->construct_row();
	}
	
	if(count($table->rows) == 0)
	{
		$table->construct_cell("There are currently no promotions logged.", array("colspan" => "4"));
		$table->construct_row();
	}
	
	$table->output("Promotion Logs");
	
	$query = $db->simple_select("promotions", "COUNT(pid) as promotions");
	$total_rows = $db->fetch_field($query, "promotions");
	
	echo "<br />".draw_admin_pagination($mybb->input['page'], "20", $total_rows, "index.php?".SID."&amp;module=user/group_promotions&amp;action=logs&amp;page={page}");
	
	$page->output_footer();
}

if(!$mybb->input['action'])
{	
	$page->output_header("Promotions Manager");
	
	$sub_tabs['usergroup_promotions'] = array(
		'title' => "User Group Promotions",
		'link' => "index.php?".SID."&amp;module=user/group_promotions",
		'description' => "Here you can manage User Group Promotions."
	);

	$sub_tabs['add_promotion'] = array(
		'title' => "Add New Promotion",
		'link' => "index.php?".SID."&amp;module=user/group_promotions&amp;action=add"
	);

	$sub_tabs['promotion_logs'] = array(
		'title' => "View Promotion Logs",
		'link' => "index.php?".SID."&amp;module=user/group_promotions&amp;action=logs"
	);

	$page->output_nav_tabs($sub_tabs, 'usergroup_promotions');

	$table = new Table;
	$table->construct_header("Promotion");
	$table->construct_header("Controls", array("class" => "align_center", "width" => 150));

	$query = $db->simple_select("promotions", "*", "", array("order_by" => "title", "order_dir" => "asc"));
	while($promotion = $db->fetch_array($query))
	{
		$promotion['title'] = htmlspecialchars_uni($promotion['title']);
		$promotion['description'] = htmlspecialchars_uni($promotion['description']);
		$table->construct_cell("<div><strong><a href=\"index.php?".SID."&amp;module=users/group_promotions&amp;action=edit&amp;pid={$promotion['pid']}\">{$promotion['title']}</a></strong><br /><small>{$promotion['description']}</small></div>");

		$popup = new PopupMenu("promotion_{$promotion['pid']}", "Options");
		$popup->add_item("Edit Task", "index.php?".SID."&amp;module=user/group_promotions&amp;action=edit&amp;pid={$promotion['pid']}");
		if($promotion['enabled'] == 1)
		{
			$popup->add_item("Disable Promotion", "index.php?".SID."&amp;module=user/group_promotions&amp;action=disable&amp;pid={$promotion['pid']}");
		}
		else
		{
			$popup->add_item("Enable Promotion", "index.php?".SID."&amp;module=user/group_promotions&amp;action=enable&amp;pid={$promotion['pid']}");
		}
		$popup->add_item("Delete Promotion", "index.php?".SID."&amp;module=user/group_promotions&amp;action=delete&amp;pid={$promotion['pid']}", "return AdminCP.deleteConfirmation(this, 'Are you sure you wish to delete this promotion?')");
		$table->construct_cell($popup->fetch(), array("class" => "align_center"));
		$table->construct_row();
	}
	
	if(count($table->rows) == 0)
	{
		$table->construct_cell("There are currently no set promotions.", array("colspan" => "2"));
		$table->construct_row();
	}
	
	$table->output("User Group Promotions");
	
	$page->output_footer();
}

?>