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

require_once MYBB_ROOT."inc/functions_warnings.php";

$page->add_breadcrumb_item("Warning System", "index.php?".SID."&amp;module=config/warning");

if($mybb->input['action'] == "levels" || !$mybb->input['action'])
{
	$sub_tabs['manage_types'] = array(
		'title' => "Warning Types",
		'link' => "index.php?".SID."&amp;module=config/warning",
		'description' => "Here you can manage the list of different warning types staff are allowed to issue to users."
	);
	$sub_tabs['add_type'] = array(
		'title'=> 'Add Warning Type',
		'link' => "index.php?".SID."&amp;module=config/warning&amp;action=add_type"
	);
	$sub_tabs['manage_levels'] = array(
		'title' => "Warning Levels",
		'link' => "index.php?".SID."&amp;module=config/warning&amp;action=levels",
		'description' => "Warning Levels define what happens to a user when they reach a particular warning level (percentage of maximum warning points). You can ban users or suspend their privledges.",
	);
	$sub_tabs['add_level'] = array(
		'title'=> 'Add Warning Level',
		'link' => "index.php?".SID."&amp;module=config/warning&amp;action=add_level"
	);
}

if($mybb->input['action'] == "add_level")
{
	if($mybb->request_method == "post")
	{
		if(!is_numeric($mybb->input['percentage']) || $mybb->input['percentage'] > 100 || $mybb->input['percentage'] < 0)
		{
			$errors[] = "You did not enter a valid percentage value for this warning level. Your percentage value must be between 1 and 100.";
		}

		if(!$errors)
		{
			// Ban
			if($mybb->input['action_type'] == 1)
			{
				$action = array(
					"type" => 1,
					"usergroup" => intval($mybb->input['action_1_usergroup']),
					"length" => fetch_time_length($mybb->input['action_1_time'], $mybb->input['action_1_period'])
				);
			}
			// Suspend posting
			else if($mybb->input['action_type'] == 2)
			{
				$action = array(
					"type" => 2,
					"length" => fetch_time_length($mybb->input['action_2_time'], $mybb->input['action_2_period'])
				);
			}
			// Moderate posts
			else if($mybb->input['action_type'] == 3)
			{
				$action = array(
					"type" => 3,
					"length" => fetch_time_length($mybb->input['action_3_time'], $mybb->input['action_3_period'])
				);
			}
			$new_level = array(
				"percentage" => intval($mybb->input['percentage']),
				"action" => serialize($action)
			);
			
			$db->insert_query("warninglevels", $new_level);
			
			flash_message("The warning level has successfully been created.", 'success');
			admin_redirect("index.php?".SID."&module=config/warning&action=levels");
		}
	}
	
	$page->add_breadcrumb_item("Add Warning Level");
	$page->output_header("Warning Levels - Add Warning Level");
	
	$sub_tabs['add_level'] = array(
		'title' => "Add Warning Level",
		'description' => 'Here you can create a new warning level. Warning levels actions to be taken against users when they reach a specific percentage of the maximum warning level.'
	);
	
	$page->output_nav_tabs($sub_tabs, 'add_level');
	$form = new Form("index.php?".SID."&amp;module=config/warning&amp;action=add_level", "post");
	
	
	if($errors)
	{
		$page->output_inline_error($errors);
		$action_checked[$mybb->input['action_type']] = "checked=\"checked\"";
	}

	$form_container = new FormContainer("Add Warning Level");
	$form_container->output_row("Percentage of Maximum Warning Points", "Please enter a numeric value between 1 and 100.", $form->generate_text_box('percentage', $mybb->input['percentage'], array('id' => 'percentage')), 'percentage');

	$query = $db->simple_select("usergroups", "*", "isbannedgroup='yes'");
	while($group = $db->fetch_array($query))
	{
		$banned_groups[$group['gid']] = $group['title'];
	}
	
	$periods = array(
		"hours" => "Hour(s)",
		"days" => "Day(s)",
		"weeks" => "Week(s)",
		"months" => "Month(s)",
		"never" => "Never"
	);

	$actions = "<script type=\"text/javascript\">
	function checkAction()
	{
		var checked = '';
		document.getElementsByClassName('actions_check').each(function(e)
		{
			if(e.checked == true)
			{
				checked = e.value;
			}
		});
		document.getElementsByClassName('actions').each(function(e)
		{
			Element.hide(e);
		});
		if($('action_'+checked))
		{
			Element.show('action_'+checked);
		}
	}	
	</script>
	<dl style=\"margin-top: 0; margin-bottom: 0;\" width=\"100%\">
		<dt><label style=\"display: block;\"><input type=\"radio\" name=\"action_type\" value=\"1\" {$action_checked[1]} class=\"actions_check\" onclick=\"checkAction();\" style=\"vertical-align: middle;\" /> <strong>Ban User</strong></label></dt>
		<dd style=\"margin-top: 4px;\" id=\"action_1\" class=\"actions\">
			<table cellpadding=\"4\">
				<tr>
					<td class=\"smalltext\">Banned group:</td>
					<td>".$form->generate_select_box('action_1_usergroup', $banned_groups, $mybb->input['action_1_usergroup'])."</td>
				</tr>
				<tr>
					<td class=\"smalltext\">Ban length:</td>
					<td>".$form->generate_text_box('action_1_time', $mybb->input['action_1_time'], array('style' => 'width: 2em;'))." ".$form->generate_select_box('action_1_period', $periods, $mybb->input['action_1_period'])."</td>
				</tr>
			</table>
		</dd>
		<dt><label style=\"display: block;\"><input type=\"radio\" name=\"action_type\" value=\"2\" {$action_checked[2]} class=\"actions_check\" onclick=\"checkAction();\" style=\"vertical-align: middle;\" /> <strong>Suspend Posting Privileges</strong></label></dt>
		<dd style=\"margin-top: 4px;\" id=\"action_2\" class=\"actions\">
			<table cellpadding=\"4\">
				<tr>
					<td class=\"smalltext\">Suspension length:</td>
					<td>".$form->generate_text_box('action_2_time', $mybb->input['action_2_time'], array('style' => 'width: 2em;'))." ".$form->generate_select_box('action_2_period', $periods, $mybb->input['action_2_period'])."</td>
				</tr>
			</table>
		</dd>
		<dt><label style=\"display: block;\"><input type=\"radio\" name=\"action_type\" value=\"3\" {$action_checked[3]} class=\"actions_check\" onclick=\"checkAction();\" style=\"vertical-align: middle;\" /> <strong>Moderate Posts</strong></label></dt>
		<dd style=\"margin-top: 4px;\" id=\"action_3\" class=\"actions\">
			<table cellpadding=\"4\">
				<tr>
					<td class=\"smalltext\">Moderation length:</td>
					<td>".$form->generate_text_box('action_3_time', $mybb->input['action_3_time'], array('style' => 'width: 2em;'))." ".$form->generate_select_box('action_3_period', $periods, $mybb->input['action_3_period'])."</td>
				</tr>
			</table>
		</dd>
	</dl>
	<script type=\"text/javascript\">
	checkAction();
	</script>";
	$form_container->output_row("Action to be Taken", "Select the action you wish to be taken when users reach the above level.", $actions);
	$form_container->end();

	$buttons[] = $form->generate_submit_button("Save Warning Level");

	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

if($mybb->input['action'] == "edit_level")
{
	$query = $db->simple_select("warninglevels", "*", "lid='".intval($mybb->input['lid'])."'");
	$level = $db->fetch_array($query);

	// Does the warning level not exist?
	if(!$level['lid'])
	{
		flash_message('The specified warning level does not exist.', 'error');
		admin_redirect("index.php?".SID."&module=config/warning");
	}

	if($mybb->request_method == "post")
	{
		if(!is_numeric($mybb->input['percentage']) || $mybb->input['percentage'] > 100 || $mybb->input['percentage'] < 0)
		{
			$errors[] = "You did not enter a valid percentage value for this warning level. Your percentage value must be between 1 and 100.";
		}

		if(!$errors)
		{
			// Ban
			if($mybb->input['action_type'] == 1)
			{
				$action = array(
					"type" => 1,
					"usergroup" => intval($mybb->input['action_1_usergroup']),
					"length" => fetch_time_length($mybb->input['action_1_time'], $mybb->input['action_1_period'])
				);
			}
			// Suspend posting
			else if($mybb->input['action_type'] == 2)
			{
				$action = array(
					"type" => 2,
					"length" => fetch_time_length($mybb->input['action_2_time'], $mybb->input['action_2_period'])
				);
			}
			// Moderate posts
			else if($mybb->input['action_type'] == 3)
			{
				$action = array(
					"type" => 3,
					"length" => fetch_time_length($mybb->input['action_3_time'], $mybb->input['action_3_period'])
				);
			}
			$updated_level = array(
				"percentage" => intval($mybb->input['percentage']),
				"action" => serialize($action)
			);
			
			$db->update_query("warninglevels", $updated_level, "lid='{$level['lid']}'");
			
			flash_message("The warning level has successfully been updated.", 'success');
			admin_redirect("index.php?".SID."&module=config/warning&action=levels");
		}
	}
	
	$page->add_breadcrumb_item("Edit Warning Level");
	$page->output_header("Warning Levels - Edit Warning Level");
	
	$sub_tabs['edit_level'] = array(
		'title' => "Edit Warning Level",
		'description' => 'Warning levels actions to be taken against users when they reach a specific percentage of the maximum warning level.'
	);
	
	$page->output_nav_tabs($sub_tabs, 'edit_level');
	$form = new Form("index.php?".SID."&amp;module=config/warning&amp;action=edit_level&amp;lid={$level['lid']}", "post");
	
	if($errors)
	{
		$page->output_inline_error($errors);
	}
	else
	{
		$mybb->input = array(
			"percentage" => $level['percentage'],
		);
		$action = unserialize($level['action']);
		if($action['type'] == 1)
		{
			$mybb->input['action_1_usergroup'] = $action['usergroup'];
			$length = fetch_friendly_expiration($action['length']);
			$mybb->input['action_1_time'] = $length['time'];
			$mybb->input['action_1_period'] = $length['period'];
		}
		else if($action['type'] == 2)
		{
			$length = fetch_friendly_expiration($action['length']);
			$mybb->input['action_2_time'] = $length['time'];
			$mybb->input['action_2_period'] = $length['period'];
		}
		else if($action['type'] == 3)
		{
			$length = fetch_friendly_expiration($action['length']);
			$mybb->input['action_3_time'] = $length['time'];
			$mybb->input['action_3_period'] = $length['period'];
		}
		$action_checked[$action['type']] = "checked=\"checked\"";
	}

	$form_container = new FormContainer("Edit Warning Level");
	$form_container->output_row("Percentage of Maximum Warning Points", "Please enter a numeric value between 1 and 100.", $form->generate_text_box('percentage', $mybb->input['percentage'], array('id' => 'percentage')), 'percentage');

	$query = $db->simple_select("usergroups", "*", "isbannedgroup='yes'");
	while($group = $db->fetch_array($query))
	{
		$banned_groups[$group['gid']] = $group['title'];
	}
	
	$periods = array(
		"hours" => "Hour(s)",
		"days" => "Day(s)",
		"weeks" => "Week(s)",
		"months" => "Month(s)",
		"never" => "Never"
	);

	$actions = "<script type=\"text/javascript\">
	function checkAction()
	{
		var checked = '';
		document.getElementsByClassName('actions_check').each(function(e)
		{
			if(e.checked == true)
			{
				checked = e.value;
			}
		});
		document.getElementsByClassName('actions').each(function(e)
		{
			Element.hide(e);
		});
		if($('action_'+checked))
		{
			Element.show('action_'+checked);
		}
	}	
	</script>
	<dl style=\"margin-top: 0; margin-bottom: 0;\" width=\"100%\">
		<dt><label style=\"display: block;\"><input type=\"radio\" name=\"action_type\" value=\"1\" {$action_checked[1]} class=\"actions_check\" onclick=\"checkAction();\" style=\"vertical-align: middle;\" /> <strong>Ban User</strong></label></dt>
		<dd style=\"margin-top: 4px;\" id=\"action_1\" class=\"actions\">
			<table cellpadding=\"4\">
				<tr>
					<td class=\"smalltext\">Banned group:</td>
					<td>".$form->generate_select_box('action_1_usergroup', $banned_groups, $mybb->input['action_1_usergroup'])."</td>
				</tr>
				<tr>
					<td class=\"smalltext\">Ban length:</td>
					<td>".$form->generate_text_box('action_1_time', $mybb->input['action_1_time'], array('style' => 'width: 2em;'))." ".$form->generate_select_box('action_1_period', $periods, $mybb->input['action_1_period'])."</td>
				</tr>
			</table>
		</dd>
		<dt><label style=\"display: block;\"><input type=\"radio\" name=\"action_type\" value=\"2\" {$action_checked[2]} class=\"actions_check\" onclick=\"checkAction();\" style=\"vertical-align: middle;\" /> <strong>Suspend Posting Privileges</strong></label></dt>
		<dd style=\"margin-top: 4px;\" id=\"action_2\" class=\"actions\">
			<table cellpadding=\"4\">
				<tr>
					<td class=\"smalltext\">Suspension length:</td>
					<td>".$form->generate_text_box('action_2_time', $mybb->input['action_2_time'], array('style' => 'width: 2em;'))." ".$form->generate_select_box('action_2_period', $periods, $mybb->input['action_2_period'])."</td>
				</tr>
			</table>
		</dd>
		<dt><label style=\"display: block;\"><input type=\"radio\" name=\"action_type\" value=\"3\" {$action_checked[3]} class=\"actions_check\" onclick=\"checkAction();\" style=\"vertical-align: middle;\" /> <strong>Moderate Posts</strong></label></dt>
		<dd style=\"margin-top: 4px;\" id=\"action_3\" class=\"actions\">
			<table cellpadding=\"4\">
				<tr>
					<td class=\"smalltext\">Moderation length:</td>
					<td>".$form->generate_text_box('action_3_time', $mybb->input['action_3_time'], array('style' => 'width: 2em;'))." ".$form->generate_select_box('action_3_period', $periods, $mybb->input['action_3_period'])."</td>
				</tr>
			</table>
		</dd>
	</dl>
	<script type=\"text/javascript\">
	checkAction();
	</script>";
	$form_container->output_row("Action to be Taken", "Select the action you wish to be taken when users reach the above level.", $actions);
	$form_container->end();

	$buttons[] = $form->generate_submit_button("Save Warning Level");

	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

if($mybb->input['action'] == "delete_level")
{
	$query = $db->simple_select("warninglevels", "*", "lid='".intval($mybb->input['lid'])."'");
	$level = $db->fetch_array($query);

	// Does the warning level not exist?
	if(!$level['lid'])
	{
		flash_message('The specified warning level does not exist.', 'error');
		admin_redirect("index.php?".SID."&module=config/warning");
	}

	// User clicked no
	if($mybb->input['no'])
	{
		admin_redirect("index.php?".SID."&module=config/warning");
	}

	if($mybb->request_method == "post")
	{
		// Delete the level
		$db->delete_query("warninglevels", "lid='{$level['lid']}'");

		flash_message('The specified warning level has been deleted.', 'success');
		admin_redirect("index.php?".SID."&module=config/warning");
	}
	else
	{
		$page->output_confirm_action("index.php?".SID."&amp;module=config/warning&amp;action=delete_level&amp;lid={$level['lid']}", "Are you sure you wish to delete this warning level?");
	}
}

if($mybb->input['action'] == "add_type")
{
	if($mybb->request_method == "post")
	{
		if(!trim($mybb->input['title']))
		{
			$errors[] = "You did not enter a title for this warning type";
		}

		if(!is_numeric($mybb->input['points']) || $mybb->input['points'] > $mybb->settings['maxwarningpoints'] || $mybb->input['points'] < 0)
		{
			$errors[] = "You did not enter a valid number of points to add when giving warnings of this type. You must enter a numer greater than 0 but less than {$mybb->settings['maxwarningpoints']}";
		}

		if(!$errors)
		{
			$new_type = array(
				"title" => $db->escape_string($mybb->input['title']),
				"points" => intval($mybb->input['points']),
				"expirationtime" =>  fetch_time_length($mybb->input['expire_time'], $mybb->input['expire_period'])
			);
			
			$db->insert_query("warningtypes", $new_type);
			
			flash_message("The warning type has successfully been created.", 'success');
			admin_redirect("index.php?".SID."&module=config/warning");
		}
	}
	else
	{
		$mybb->input = array(
			"points" => "2",
			"expire_time" => 1,
			"expire_period" => "days"
		);
	}
	
	$page->add_breadcrumb_item("Add Warning Type");
	$page->output_header("Warning Types - Add Warning Type");
	
	$sub_tabs['add_type'] = array(
		'title' => "Add Warning Type",
		'description' => 'Here you can create a new predefined warning type. Warning types are selectable when warning users and you can define the number of points to add for this type as well as the time period before warnings of this type expire.'
	);
	
	$page->output_nav_tabs($sub_tabs, 'add_type');
	$form = new Form("index.php?".SID."&amp;module=config/warning&amp;action=add_type", "post");
	
	
	if($errors)
	{
		$page->output_inline_error($errors);
	}

	$form_container = new FormContainer("Add Warning Type");
	$form_container->output_row("Title", "", $form->generate_text_box('title', $mybb->input['title'], array('id' => 'title')), 'title');
	$form_container->output_row("Points to Add", "The number of points to add to a users warning level.", $form->generate_text_box('points', $mybb->input['points'], array('id' => 'points')), 'points');
	$expiration_periods = array(
		"hours" => "Hour(s)",
		"days" => "Day(s)",
		"weeks" => "Week(s)",
		"months" => "Month(s)",
		"never" => "Never"
	);
	$form_container->output_row("Warning Expiry", "How long after this warning is given do you want it to expire?", $form->generate_text_box('expire_time', $mybb->input['expire_time'], array('id' => 'expire_time'))." ".$form->generate_select_box('expire_period', $expiration_periods, $mybb->input['expire_period'], array('id' => 'expire_period')), 'expire_time');
	$form_container->end();

	$buttons[] = $form->generate_submit_button("Save Warning Type");

	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

if($mybb->input['action'] == "edit_type")
{
	$query = $db->simple_select("warningtypes", "*", "tid='".intval($mybb->input['tid'])."'");
	$type = $db->fetch_array($query);

	// Does the warning type not exist?
	if(!$type['tid'])
	{
		flash_message('The specified warning type does not exist.', 'error');
		admin_redirect("index.php?".SID."&module=config/warning");
	}

	if($mybb->request_method == "post")
	{
		if(!trim($mybb->input['title']))
		{
			$errors[] = "You did not enter a title for this warning type";
		}

		if(!is_numeric($mybb->input['points']) || $mybb->input['points'] > $mybb->settings['maxwarningpoints'] || $mybb->input['points'] < 0)
		{
			$errors[] = "You did not enter a valid number of points to add when giving warnings of this type. You must enter a numer greater than 0 but less than {$mybb->settings['maxwarningpoints']}";
		}

		if(!$errors)
		{
			$updated_type = array(
				"title" => $db->escape_string($mybb->input['title']),
				"points" => intval($mybb->input['points']),
				"expirationtime" =>  fetch_time_length($mybb->input['expire_time'], $mybb->input['expire_period'])
			);
			
			$db->update_query("warningtypes", $updated_type, "tid='{$type['tid']}'");
			
			flash_message("The warning type has successfully been updated.", 'success');
			admin_redirect("index.php?".SID."&module=config/warning");
		}
	}
	else
	{
		$expiration = fetch_friendly_expiration($type['expirationtime']);
		$mybb->input = array(
			"title" => $type['title'],
			"points" => $type['points'],
			"expire_time" => $expiration['time'],
			"expire_period" => $expiration['period']
		);
	}
	
	$page->add_breadcrumb_item("Edit Warning Type");
	$page->output_header("Warning Types - Edit Warning Type");
	
	$sub_tabs['edit_type'] = array(
		'title' => "Edit Warning Type",
		'description' => 'Here you can edit this warning type. Warning types are selectable when warning users and you can define the number of points to add for this type as well as the time period before warnings of this type expire.'
	);
	
	$page->output_nav_tabs($sub_tabs, 'edit_type');
	$form = new Form("index.php?".SID."&amp;module=config/warning&amp;action=edit_type&tid={$type['tid']}", "post");
	
	
	if($errors)
	{
		$page->output_inline_error($errors);
	}

	$form_container = new FormContainer("Edit Warning Type");
	$form_container->output_row("Title", "", $form->generate_text_box('title', $mybb->input['title'], array('id' => 'title')), 'title');
	$form_container->output_row("Points to Add", "The number of points to add to a users warning level.", $form->generate_text_box('points', $mybb->input['points'], array('id' => 'points')), 'points');
	$expiration_periods = array(
		"hours" => "Hour(s)",
		"days" => "Day(s)",
		"weeks" => "Week(s)",
		"months" => "Month(s)",
		"never" => "Never"
	);
	$form_container->output_row("Warning Expiry", "How long after this warning is given do you want it to expire?", $form->generate_text_box('expire_time', $mybb->input['expire_time'], array('id' => 'expire_time'))." ".$form->generate_select_box('expire_period', $expiration_periods, $mybb->input['expire_period'], array('id' => 'expire_period')), 'expire_time');
	$form_container->end();

	$buttons[] = $form->generate_submit_button("Save Warning Type");

	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

if($mybb->input['action'] == "delete_type")
{
	$query = $db->simple_select("warningtypes", "*", "tid='".intval($mybb->input['tid'])."'");
	$type = $db->fetch_array($query);

	// Does the warning type not exist?
	if(!$type['tid'])
	{
		flash_message('The specified warning type does not exist.', 'error');
		admin_redirect("index.php?".SID."&module=config/warning");
	}

	// User clicked no
	if($mybb->input['no'])
	{
		admin_redirect("index.php?".SID."&module=config/warning");
	}

	if($mybb->request_method == "post")
	{
		// Delete the type
		$db->delete_query("warningtypes", "tid='{$type['tid']}'");

		flash_message('The specified warning type has been deleted.', 'success');
		admin_redirect("index.php?".SID."&module=config/warning");
	}
	else
	{
		$page->output_confirm_action("index.php?".SID."&amp;module=config/warning&amp;action=delete_type&amp;tid={$type['tid']}", "Are you sure you wish to delete this warning type?");
	}
}

if($mybb->input['action'] == "levels")
{
	$page->output_header("Warning Levels");

	$page->output_nav_tabs($sub_tabs, 'manage_levels');

	$table = new Table;
	$table->construct_header("Percentage", array('width' => '5%', 'class' => 'align_center'));
	$table->construct_header("Action to Take");
	$table->construct_header("Controls", array("class" => "align_center", "colspan" => 2));
	
	$query = $db->simple_select("warninglevels", "*", "", array('order_by' => 'percentage'));
	while($level = $db->fetch_array($query))
	{
		$table->construct_cell($level['percentage']."%", array("class" => "align_center"));
		$action = unserialize($level['action']);
		// Ban user
		if($action['type'] == 1)
		{
			$ban_length = fetch_friendly_expiration($action['length']);
			$lang_str = "expiration_".$ban_length['period'];
			$group_name = $groupscache[$action['usergroup']]['title'];
			$type = "Move to banned group ({$group_name}) for {$ban_length['time']} {$lang->$lang_str}";
		}
		else if($action['type'] == 2)
		{
			$period = fetch_friendly_expiration($action['length']);
			$lang_str = "expiration_".$period['period'];
			$type = "Suspend posting privledges for {$period['time']} {$lang->$lang_str}";
		}
		else if($action['type'] == 3)
		{
			$period = fetch_friendly_expiration($action['length']);
			$lang_str = "expiration_".$period['period'];
			$type = "Moderate new posts for {$period['time']} {$lang->$lang_str}";
		}
		$table->construct_cell($type);
		$table->construct_cell("<a href=\"index.php?".SID."&amp;module=config/warning&amp;action=edit_level&amp;lid={$level['lid']}\">Edit</a>", array("width" => 100, "class" => "align_center"));
		$table->construct_cell("<a href=\"index.php?".SID."&amp;module=config/warning&amp;action=delete_level&amp;lid={$level['lid']}\" onclick=\"return AdminCP.deleteConfirmation(this, 'Are you sure you wish to delete this warning type?')\">Delete</a>", array("width" => 100, "class" => "align_center"));
		$table->construct_row();
	}
	
	if(count($table->rows) == 0)
	{
		$table->construct_cell("There are no warning levels on your forum at this time.", array('colspan' => 4));
		$table->construct_row();
		$no_results = true;
	}
	
	$table->output("Warning Types");

	$page->output_footer();
}

if(!$mybb->input['action'])
{
	$page->output_header("Warning Types");

	$page->output_nav_tabs($sub_tabs, 'manage_types');

	$table = new Table;
	$table->construct_header("Warning Type");
	$table->construct_header("Points", array('width' => '5%', 'class' => 'align_center'));
	$table->construct_header("Expires After", array('width' => '25%', 'class' => 'align_center'));
	$table->construct_header("Controls", array("class" => "align_center", "colspan" => 2));
	
	$query = $db->simple_select("warningtypes", "*", "", array('order_by' => 'title'));
	while($type = $db->fetch_array($query))
	{
		$type['name'] = htmlspecialchars_uni($type['title']);
		$table->construct_cell("<a href=\"index.php?".SID."&amp;module=config/warning&amp;action=edit_type&amp;tid={$type['tid']}\">{$type['title']}</a>");
		$table->construct_cell("{$type['points']}", array("class" => "align_center"));
		$expiration = fetch_friendly_expiration($type['expirationtime']);
		$lang_str = "expiration_".$expiration['period'];
		if($type['expirationtime'] > 0)
		{
			$table->construct_cell("{$expiration['time']} {$lang->$lang_str}", array("class" => "align_center"));
		}
		else
		{
			$table->construct_cell("Never", array("class" => "align_center"));
		}
		$table->construct_cell("<a href=\"index.php?".SID."&amp;module=config/warning&amp;action=edit_type&amp;tid={$type['tid']}\">Edit</a>", array("width" => 100, "class" => "align_center"));
		$table->construct_cell("<a href=\"index.php?".SID."&amp;module=config/warning&amp;action=delete_type&amp;tid={$type['tid']}\" onclick=\"return AdminCP.deleteConfirmation(this, 'Are you sure you wish to delete this warning type?')\">Delete</a>", array("width" => 100, "class" => "align_center"));
		$table->construct_row();
	}
	
	if(count($table->rows) == 0)
	{
		$table->construct_cell("There are no warning types on your forum at this time.", array('colspan' => 5));
		$table->construct_row();
		$no_results = true;
	}
	
	$table->output("Warning Types");

	$page->output_footer();
}

function fetch_time_length($time, $period)
{
		$time = intval($time);
		if($period == "hours")
		{
			$time = $time*3600;
		}
		else if($period == "days")
		{
			$time = $time*86400;
		}
		else if($period == "weeks")
		{
			$time = $time*604800;
		}
		else if($period == "months")
		{
			$time = $time*2592000;
		}
		else
		{
			$time = 0;
		}
		return $time;
}
?>