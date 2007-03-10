<?php
/**
 * MyBB 1.2
 * Copyright © 2007 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.com
 * License: http://www.mybboard.com/license.php
 *
 * $Id: settings.php 2918 2007-03-05 00:50:41Z Tikitiki $
 */

$page->add_breadcrumb_item("Banning", "index.php?".SID."&amp;module=config/banning");

if($mybb->input['action'] == "add" && $mybb->request_method == "post")
{
	if(!trim($mybb->input['filter']))
	{
		$errors[] = "You did not enter a value to ban";
	}


	if(!$errors)
	{
		$new_filter = array(
			"filter" => $db->escape_string($mybb->input['filter']),
			"type" => intval($mybb->input['type']),
			"dateline" => time()
		);
		$db->insert_query("banfilters", $new_filter);

		if($mybb->input['type'] == 1)
		{
			$cache->update_bannedips();
			flash_message('The IP address has successfully been banned.', 'success');
			admin_redirect("index.php?".SID."&module=config/banning");
		}
		else if($mybb->input['type'] == 2)
		{
			flash_message('The username has successfully been disallowed.', 'success');
			admin_redirect("index.php?".SID."&module=config/banning&type=usernames");
		}
		else if($mybb->input['type'] == 3)
		{
			flash_message('The email address has successfully been disallowed.', 'success');
			admin_redirect("index.php?".SID."&module=config/banning&type=emails");
		}		
	}
	else
	{
		if($mybb->input['type'] == 1)
		{
			$mybb->input['type'] = "ips";
		}
		else if($mybb->input['type'] == 2)
		{
			$mybb->input['type'] = "usernames";
		}
		else if($mybb->input['type'] == 3)
		{
			$mybb->input['type'] = "emails";
		}
		$mybb->input['action'] = '';
	}
}

if($mybb->input['action'] == "delete")
{
	$query = $db->simple_select("banfilters", "*", "fid='".intval($mybb->input['fid'])."'");
	$filter = $db->fetch_array($query);

	// Does the filter not exist?
	if(!$filter['fid'])
	{
		flash_message('The specified filter does not exist.', 'error');
		admin_redirect("index.php?".SID."&module=config/banning");
	}

	if($filter['type'] == 3)
	{
		$type = "emails";
	}
	else if($filter['type'] == 2)
	{
		$type = "usernames";
	}
	else
	{
		$type = "ips";
	}
	
	// User clicked no
	if($mybb->input['no'])
	{
		admin_redirect("index.php?".SID."&module=config/banning&type={$type}");
	}

	if($mybb->request_method == "post")
	{
		// Delete the ban filter
		$db->delete_query("banfilters", "fid='{$filter['fid']}'");

		// Banned IP? Rebuild banned IP cache
		if($filter['type'] == 1)
		{
			$cache->update_bannedips();
		}

		flash_message('The specified ban has been deleted.', 'success');
		admin_redirect("index.php?".SID."&module=config/banning&type={$type}");
	}
	else
	{
		$page->output_confirm_action("index.php?".SID."&amp;module=config/banning&amp;action=delete&amp;fid={$filter['fid']}", "Are you sure you wish to delete this ban?");
	}
}

if(!$mybb->input['action'])
{
	switch($mybb->input['type'])
	{
		case "emails":
			$type = "3";
			$title = "Disallowed Email Addresses";
			break;
		case "usernames":
			$type = "2";
			$title = "Disallowed Usernames";
			break;
		default:
			$type = "1";
			$title = "Banned IP Addresses";
			$mybb->input['type'] = "ips";
	}

	$page->output_header($title);

	$sub_tabs['ips'] = array(
		'title' => "Banned IPs",
		'link' => "index.php?".SID."&amp;module=config/banning",
		'description' => "Here you can manage IP addresses which are banned from accessing your board."
	);

	$sub_tabs['users'] = array(
		'title' => "Banned Accounts",
		'link' => "index.php?".SID."&amp;module=users/banning"
	);

	$sub_tabs['usernames'] = array(
		'title' => "Disallowed Usernames",
		'link' => "index.php?".SID."&amp;module=config/banning&amp;type=usernames",
		'description' => "Here you manage a list of usernames which cannot be registered or used by users. This feature is also particularly useful for reserving usernames."
	);

	$sub_tabs['emails'] = array(
		'title' => "Disallowed Email Addresses",
		'link' => "index.php?".SID."&amp;module=config/banning&amp;type=emails",
		'description' => "Here you manage a list of email addresses which cannot be registered or used by users."
	);

	$page->output_nav_tabs($sub_tabs, $mybb->input['type']);

	$table = new Table;
	if($mybb->input['type'] == "usernames")
	{
		$table->construct_header("Username");
		$table->construct_header("Date Disallowed", array("class" => "align_center", "width" => 200));
		$table->construct_header("Last Attempted Use", array("class" => "align_center", "width" => 200));
	}
	else if($mybb->input['type'] == "emails")
	{
		$table->construct_header("Email Address");
		$table->construct_header("Date Disallowed", array("class" => "align_center", "width" => 200));
		$table->construct_header("Last Attempted Use", array("class" => "align_center", "width" => 200));
	}
	else
	{
		$table->construct_header("IP Address");
		$table->construct_header("Ban Date", array("class" => "align_center", "width" => 200));
		$table->construct_header("Last Access", array("class" => "align_center", "width" => 200));
	}
	$table->construct_header("&nbsp;", array("width" => 1));

	$query = $db->simple_select("banfilters", "*", "type='{$type}'", array("order_by" => "filter", "order_dir" => "asc"));
	while($filter = $db->fetch_array($query))
	{
		$filter['filter'] = htmlspecialchars_uni($filter['filter']);
		if($filter['lastuse'] > 0)
		{
			$last_use = my_date($mybb->settings['dateformat'], $filter['lastuse']).", ".my_date($mybb->settings['timeformat'], $filter['lastuse']);
		}
		else
		{
			$last_use = "Never";
		}
		$date = my_date($mybb->settings['dateformat'], $filter['dateline']).", ".my_date($mybb->settings['timeformat'], $filter['dateline']);
		$table->construct_cell($filter['filter']);
		$table->construct_cell($date, array("class" => "align_center"));
		$table->construct_cell($last_use, array("class" => "align_center"));
		$table->construct_cell("<a href=\"index.php?".SID."&amp;module=config/banning&amp;action=delete&amp;fid={$filter['fid']}\" onclick=\"return AdminCP.deleteConfirmation(this, 'Are you sure you wish to delete this ban?');\"><img src=\"styles/{$page->style}/images/icons/delete.gif\" title=\"Delete\" alt=\"Delete\" /></a>", array("class" => "align_center"));
		$table->construct_row();
	}
	$table->output($title);

	$form = new Form("index.php?".SID."&amp;module=config/banning&amp;action=add", "post", "add");
	if($errors)
	{
		$page->output_inline_error($errors);
	}
	if($mybb->input['type'] == "usernames")
	{
		$form_container = new FormContainer("Add a Disallowed Username");
		$form_container->output_row("Username <em>*</em>", "Note: To indicate a wildcard match, use *", $form->generate_text_box('filter', $mybb->input['filter'], array('id' => 'filter')), 'filter');
		$buttons[] = $form->generate_submit_button("Disallow Username");
	}
	else if($mybb->input['type'] == "emails")
	{
		$form_container = new FormContainer("Add a Disallowed Email Address");
		$form_container->output_row("Email Address <em>*</em>", "Note: To indicate a wildcard match, use *", $form->generate_text_box('filter', $mybb->input['filter'], array('id' => 'filter')), 'filter');
		$buttons[] = $form->generate_submit_button("Disallow Email Address");
	}
	else
	{
		$form_container = new FormContainer("Ban an IP Address");
		$form_container->output_row("IP Address <em>*</em>", "Note: To ban a range of IP addresses use * (Ex: 127.0.0.*)", $form->generate_text_box('filter', $mybb->input['filter'], array('id' => 'filter')), 'filter');
		$buttons[] = $form->generate_submit_button("Ban IP Address");
	}
	$form_container->end();
	echo $form->generate_hidden_field("type", $type);
	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
 }
